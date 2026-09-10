<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;
use App\Models\EmployeeModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Carga masiva de empleados por XLSX: en vez de dar de alta uno por uno desde el
 * formulario, RH llena una plantilla de Excel y la sube aqui. Hace upsert por
 * numero_empleado (si ya existe, actualiza sus datos; si no, lo da de alta) y
 * regresa un detalle fila por fila para que se vea exactamente que paso con
 * cada quien -- un renglon con error NO tumba la carga completa.
 */
class EmployeeImportController extends BaseController
{
    /** Orden de columnas esperado en la plantilla (fila 1 = encabezados, se ignora el texto exacto). */
    private const COLUMNS = [
        'numero_empleado', 'pin_checador', 'nombre', 'apellido_paterno', 'apellido_materno',
        'curp', 'rfc', 'correo', 'telefono', 'puesto', 'departamento', 'fecha_ingreso', 'estatus',
    ];

    /**
     * GET /api/v1/employees/import/template
     * Descarga una plantilla .xlsx vacia (encabezados + una fila de ejemplo)
     * para que RH la llene y la vuelva a subir en POST /employees/import.
     */
    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Empleados');

        $sheet->fromArray(self::COLUMNS, null, 'A1');
        $sheet->fromArray([
            'EMP-0100', '100', 'Juana', 'Perez', 'Lopez',
            'PELJ900101MDFXXX01', 'PELJ900101AB1', 'juana.perez@empresa.com', '5512345678',
            'Analista', 'Sistemas', '2026-01-15', 'active',
        ], null, 'A2');

        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);

        $tmpPath = WRITEPATH . 'uploads/plantilla_empleados_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);
        $binary = file_get_contents($tmpPath);
        @unlink($tmpPath);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="plantilla_empleados.xlsx"')
            ->setBody($binary);
    }

    /**
     * POST /api/v1/employees/import   (multipart, campo "file")
     * Sube un .xlsx con la misma estructura de la plantilla. Upsert por
     * numero_empleado. Cada fila se procesa de forma independiente.
     */
    public function import()
    {
        $file = $this->request->getFile('file');
        if (!$file || !$file->isValid()) {
            return $this->fail('Sube un archivo .xlsx valido en el campo "file".', 422);
        }

        $ext = strtolower($file->getClientExtension() ?: pathinfo($file->getClientName(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->fail('El archivo debe ser .xlsx o .xls.', 422);
        }

        try {
            $spreadsheet = IOFactory::load($file->getTempName());
        } catch (\Throwable $e) {
            return $this->fail('No se pudo leer el archivo. Verifica que sea un Excel valido.', 422);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if (count($rows) < 2) {
            return $this->fail('El archivo no tiene filas de datos (solo encabezado o esta vacio).', 422);
        }

        // La fila 1 es encabezado; se ignora el texto exacto y se usa la POSICION
        // de columna (igual que la plantilla), para tolerar acentos/mayusculas distintas.
        array_shift($rows);

        $employeeModel = new EmployeeModel();
        $results = [];
        $created = 0;
        $updated = 0;
        $failed  = 0;

        // El departamento en el xlsx viene como texto (columna "departamento") pero
        // department_id ahora es obligatorio y se elige del catalogo -- lo resolvemos
        // aqui por nombre (sin importar mayusculas/espacios). Si el texto no matchea
        // ningun departamento existente, esa fila truena con un mensaje claro en vez
        // de crear departamentos nuevos "a lo tonto" por typos.
        $departmentsByName = [];
        foreach ((new DepartmentModel())->findAll() as $dept) {
            $departmentsByName[mb_strtolower(trim($dept['name']))] = $dept['id'];
        }

        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2; // fila 1 = encabezado, $i arranca en 0

            // Renglon totalmente vacio (Excel deja "fantasmas" al final a veces) -> se ignora sin contar error.
            if (empty(array_filter($row, static fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $statusRaw = strtolower(trim((string) ($row[12] ?? '')));
            $departmentText = $this->nullIfEmpty($row[10] ?? null);

            $data = [
                'employee_number'    => trim((string) ($row[0] ?? '')),
                'zkteco_pin'         => $this->nullIfEmpty($row[1] ?? null),
                'first_name'         => trim((string) ($row[2] ?? '')),
                'paternal_last_name' => trim((string) ($row[3] ?? '')),
                'maternal_last_name' => $this->nullIfEmpty($row[4] ?? null),
                'curp'               => $this->nullIfEmpty($row[5] ?? null),
                'rfc'                => $this->nullIfEmpty($row[6] ?? null),
                'email'              => $this->nullIfEmpty($row[7] ?? null),
                'phone'              => $this->nullIfEmpty($row[8] ?? null),
                'position'           => $this->nullIfEmpty($row[9] ?? null),
                'department'         => $departmentText,
                'department_id'      => $departmentText !== null ? ($departmentsByName[mb_strtolower(trim($departmentText))] ?? null) : null,
                'hire_date'          => $this->normalizeDate($row[11] ?? null),
                'status'             => in_array($statusRaw, ['active', 'inactive'], true) ? $statusRaw : 'active',
            ];

            if ($data['employee_number'] === '' || $data['first_name'] === '' || $data['paternal_last_name'] === '') {
                $failed++;
                $results[] = [
                    'row'             => $rowNumber,
                    'employee_number' => $data['employee_number'] ?: null,
                    'status'          => 'error',
                    'message'         => 'Faltan campos obligatorios (numero_empleado, nombre, apellido_paterno).',
                ];
                continue;
            }

            if ($departmentText !== null && $data['department_id'] === null) {
                $failed++;
                $results[] = [
                    'row' => $rowNumber, 'employee_number' => $data['employee_number'],
                    'status' => 'error',
                    'message' => "El departamento \"{$departmentText}\" no existe en el catalogo. Creelo primero en Departamentos o corrige el nombre en el archivo.",
                ];
                continue;
            }

            $existing = $employeeModel->where('employee_number', $data['employee_number'])->first();

            if ($existing) {
                $merged = array_merge($existing, $data, ['id' => $existing['id']]);
                if (!$employeeModel->validate($merged)) {
                    $failed++;
                    $results[] = [
                        'row' => $rowNumber, 'employee_number' => $data['employee_number'],
                        'status' => 'error', 'message' => implode(' ', $employeeModel->errors()),
                    ];
                    continue;
                }
                // Igual que EmployeeController::update(): ya validamos con $merged (trae "id"
                // para el placeholder {id}); dejar que update() revalide solo con $data lo
                // rechaza/no-opea en silencio, por eso se guarda con skipValidation.
                $employeeModel->skipValidation(true)->update($existing['id'], $data);
                $updated++;
                $results[] = ['row' => $rowNumber, 'employee_number' => $data['employee_number'], 'status' => 'updated', 'message' => 'Actualizado.'];
            } else {
                // Igual que EmployeeController::create(): "id" => 0 se deja DENTRO de $data
                // (no en una copia aparte) porque insert() vuelve a validar internamente con
                // $data tal cual se le pasa; si "id" faltara ahi, truena con el mismo
                // "No validation rules for the placeholder: id" que ya vimos en update().
                $data['id'] = 0;
                if (!$employeeModel->validate($data)) {
                    $failed++;
                    $results[] = [
                        'row' => $rowNumber, 'employee_number' => $data['employee_number'],
                        'status' => 'error', 'message' => implode(' ', $employeeModel->errors()),
                    ];
                    continue;
                }
                $employeeModel->insert($data, true);
                $created++;
                $results[] = ['row' => $rowNumber, 'employee_number' => $data['employee_number'], 'status' => 'created', 'message' => 'Creado.'];
            }
        }

        return $this->ok([
            'created' => $created,
            'updated' => $updated,
            'failed'  => $failed,
            'total'   => count($results),
            'details' => $results,
        ], 'Importacion procesada.');
    }

    private function nullIfEmpty($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;
        return ($value === null || $value === '') ? null : (string) $value;
    }

    /**
     * Excel a veces guarda fechas como numero serial (ej. 45678) en vez de texto.
     * Normaliza a 'Y-m-d' o regresa null si no se puede interpretar.
     */
    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        $ts = strtotime((string) $value);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
