<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AttendanceRecordModel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends BaseController
{
    protected AttendanceRecordModel $attendance;

    public function __construct()
    {
        $this->attendance = new AttendanceRecordModel();
    }

    /**
     * GET /api/v1/reports/daily?employee_id=&department_id=&date_from=&date_to=
     * Si no se manda employee_id, regresa el resumen de TODOS los empleados (el "reporteador" general).
     * Por cada dia y empleado: entrada (primer registro), salida (ultimo registro), total de checkpoints,
     * is_late (retardo: entrada despues de las 9:15am). Se puede filtrar por departamento.
     */
    public function daily()
    {
        $employeeId   = $this->request->getGet('employee_id');
        $departmentId = $this->request->getGet('department_id');
        $dateFrom     = $this->request->getGet('date_from') ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo       = $this->request->getGet('date_to') ?? date('Y-m-d');

        $rows = $this->attendance->dailySummary(
            $employeeId ? (int) $employeeId : null,
            $dateFrom,
            $dateTo,
            $departmentId ? (int) $departmentId : null
        );

        return $this->ok([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'rows'      => $rows,
        ]);
    }

    /**
     * GET /api/v1/reports/daily/export?employee_id=&department_id=&date_from=&date_to=
     * Mismo reporte que arriba pero descargable en CSV para Excel.
     */
    public function exportDaily()
    {
        $employeeId   = $this->request->getGet('employee_id');
        $departmentId = $this->request->getGet('department_id');
        $dateFrom     = $this->request->getGet('date_from') ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo       = $this->request->getGet('date_to') ?? date('Y-m-d');

        $rows = $this->attendance->dailySummary(
            $employeeId ? (int) $employeeId : null,
            $dateFrom,
            $dateTo,
            $departmentId ? (int) $departmentId : null
        );

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Numero empleado', 'Nombre completo', 'Departamento', 'Fecha', 'Entrada', 'Salida', 'Total de registros', 'Retardo']);

        foreach ($rows as $row) {
            $fullName = trim($row['first_name'] . ' ' . $row['paternal_last_name'] . ' ' . ($row['maternal_last_name'] ?? ''));
            fputcsv($stream, [
                $row['employee_number'],
                $fullName,
                $row['department_name'] ?? '',
                $row['work_date'],
                $row['entrada'],
                $row['salida'],
                $row['total_checkpoints'],
                $row['is_late'] ? 'Si' : 'No',
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="reporte_asistencia_' . $dateFrom . '_' . $dateTo . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $csv); // BOM para que Excel muestre bien los acentos
    }

    /**
     * GET /api/v1/reports/payroll-export?employee_id=&date_from=&date_to=
     * Exporta un .xlsx con el formato EXACTO que pide el sistema de nomina externo:
     * Employee ID, Employee Name, Work date, Year&Date, Time In, Time Out.
     *
     * - Employee ID  = numero de empleado.
     * - Work date    = dia LABORAL (ventana 5:00am-4:59am, ver AttendanceRecordModel),
     *                  el mismo dia para entrada y salida aunque la salida cruce medianoche.
     * - Year&Date    = fecha de CALENDARIO real de la salida (puede ser un dia despues del
     *                  "Work date" si el turno cruzo medianoche, ej. entro 23:00 del dia 1 y
     *                  salio 02:00 -ya del dia 2-).
     * - Time In/Out  = hora de entrada/salida en formato HHMMSS (24h).
     * Todo en formato compacto YYYYMMDD/HHMMSS, como texto (no numero) para no perder ceros
     * a la izquierda.
     */
    public function payrollExport()
    {
        $employeeId   = $this->request->getGet('employee_id');
        $departmentId = $this->request->getGet('department_id');
        $dateFrom     = $this->request->getGet('date_from');
        $dateTo       = $this->request->getGet('date_to');

        if (!$dateFrom || !$dateTo) {
            return $this->fail('date_from y date_to son obligatorios.', 422);
        }

        $rows = $this->attendance->dailySummary(
            $employeeId ? (int) $employeeId : null,
            $dateFrom,
            $dateTo,
            $departmentId ? (int) $departmentId : null
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Asistencia');
        // Se agregan Departamento y Retardo al final para no romper el formato EXACTO
        // que espera el sistema de nomina externo en las primeras 6 columnas (A-F).
        $headers = ['Employee ID', 'Employee Name', 'Work date', 'Year&Date', 'Time In', 'Time Out', 'Department', 'Late'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($rows as $row) {
            $fullName = trim($row['first_name'] . ' ' . $row['paternal_last_name'] . ' ' . ($row['maternal_last_name'] ?? ''));
            $workDate = str_replace('-', '', (string) $row['work_date']);
            $yearDate = $row['salida'] ? str_replace('-', '', substr((string) $row['salida'], 0, 10)) : $workDate;
            $timeIn   = $row['entrada'] ? str_replace(':', '', substr((string) $row['entrada'], 11, 8)) : '';
            $timeOut  = $row['salida'] ? str_replace(':', '', substr((string) $row['salida'], 11, 8)) : '';

            $sheet->setCellValueExplicit("A{$rowNum}", $row['employee_number'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$rowNum}", $fullName, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowNum}", $workDate, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$rowNum}", $yearDate, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$rowNum}", $timeIn, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$rowNum}", $timeOut, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("G{$rowNum}", $row['department_name'] ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("H{$rowNum}", $row['is_late'] ? 'Si' : 'No', DataType::TYPE_STRING);
            $rowNum++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmpPath = WRITEPATH . 'uploads/reporte_nomina_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);
        $binary = file_get_contents($tmpPath);
        @unlink($tmpPath);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="reporte_nomina_' . $dateFrom . '_' . $dateTo . '.xlsx"')
            ->setBody($binary);
    }

    /**
     * GET /api/v1/reports/day-detail/{employeeId}/{date}
     * Detalle punto por punto de un dia: entrada, visitas intermedias (cliente en Polanco,
     * cliente en Pedregal, etc) y salida, con hora y coordenadas de cada una.
     */
    public function dayDetail($employeeId = null, $date = null)
    {
        if (!$employeeId || !$date) {
            return $this->fail('Faltan parametros employeeId/date.', 422);
        }

        return $this->ok($this->attendance->dayDetail((int) $employeeId, $date));
    }
}
