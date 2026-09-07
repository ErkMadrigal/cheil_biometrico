<?php

namespace App\Controllers\Zkteco;

use App\Controllers\BaseController;
use App\Models\AttendanceRecordModel;
use App\Models\DeviceModel;
use App\Models\EmployeeModel;
use App\Models\ZktecoRawLogModel;

/**
 * Implementa el protocolo ADMS que usan los checadores ZKTeco (el mismo que usan
 * BioTime/ZKBioSecurity) para EMPUJAR registros de asistencia al servidor por HTTP,
 * sin que nosotros tengamos que ir a jalar nada al dispositivo.
 *
 * Configuracion en el checador (menu Comm. > Cloud Server / ADMS):
 *   - Habilitar "ADMS" / "Cloud Server"
 *   - Server Address: IP o dominio de este backend
 *   - Server Port: el puerto donde corre este backend (80/443 detras de tu proxy)
 *   - Enable Domain Name: si usas dominio en vez de IP
 *   - Enable Proxy Server: apagado (a menos que lo necesites)
 *
 * El dispositivo entonces llamara a estas rutas solo:
 *   GET  /iclock/cdata        -> handshake / opciones
 *   POST /iclock/cdata        -> empuja tablas (ATTLOG = checadas, OPERLOG = eventos de admin, etc)
 *   GET  /iclock/getrequest   -> el dispositivo pregunta si hay comandos pendientes
 *   POST /iclock/devicecmd    -> el dispositivo reporta el resultado de un comando
 *
 * Estas rutas van SIN JwtAuthFilter (el checador no puede mandar Bearer tokens).
 * En produccion se recomienda restringir el acceso a estas rutas por IP/firewall/VPN
 * a la red donde vive el checador.
 */
class AdmsController extends BaseController
{
    /**
     * GET|POST /iclock/cdata
     */
    public function cdata()
    {
        $sn = $this->request->getGet('SN') ?? $this->request->getGet('sn');
        if (!$sn) {
            return $this->plain('SN requerido', 400);
        }

        $deviceModel = new DeviceModel();
        $deviceModel->autoRegister($sn, $this->request->getIPAddress());

        if ($this->request->getMethod() === 'get') {
            return $this->handleOptionsHandshake($sn);
        }

        // POST: el dispositivo esta empujando datos de una tabla (ATTLOG, OPERLOG, etc)
        $table = $this->request->getGet('table');
        $body  = (string) $this->request->getBody();

        if (strtoupper((string) $table) === 'ATTLOG') {
            $processed = $this->processAttlog($sn, $body);
            return $this->plain('OK: ' . $processed);
        }

        // Otras tablas (OPERLOG, BIODATA, etc): las guardamos como referencia pero no las procesamos aun.
        $this->logRaw($sn, (string) $table ?: 'UNKNOWN', $body);

        return $this->plain('OK');
    }

    /**
     * GET /iclock/getrequest
     * El dispositivo pregunta si hay comandos pendientes (ej. borrar usuario, sincronizar hora).
     * Por ahora no encolamos comandos, siempre respondemos "OK" (sin comandos).
     */
    public function getrequest()
    {
        $sn = $this->request->getGet('SN') ?? $this->request->getGet('sn');
        if ($sn) {
            (new DeviceModel())->autoRegister($sn, $this->request->getIPAddress());
        }

        return $this->plain('OK');
    }

    /**
     * POST /iclock/devicecmd
     * El dispositivo reporta el resultado de un comando que le mandamos antes.
     */
    public function devicecmd()
    {
        return $this->plain('OK');
    }

    private function handleOptionsHandshake(string $sn)
    {
        $lines = [
            'GET OPTION FROM: SN=' . $sn,
            'Stamp=9999',
            'OpStamp=9999',
            'ErrorDelay=30',
            'Delay=30',
            'TransTimes=00:00;14:05',
            'TransInterval=1',
            'TransFlag=1111000000',
            'Realtime=1',
            'Encrypt=0',
        ];

        return $this->plain(implode("\n", $lines));
    }

    /**
     * Parsea lineas tipo ATTLOG:
     * PIN\tTIME\tSTATUS\tVERIFY\tWORKCODE\t...
     * PIN = el ID/PIN con el que el empleado esta enrolado en el checador (employees.zkteco_pin)
     * TIME = "2026-08-08 09:03:12"
     * VERIFY = metodo usado (1 = huella, 15 = rostro, etc, varia por modelo)
     */
    private function processAttlog(string $sn, string $body): int
    {
        $device       = (new DeviceModel())->findBySerial($sn);
        $employeeModel = new EmployeeModel();
        $attendance    = new AttendanceRecordModel();
        $rawLogModel   = new ZktecoRawLogModel();

        $lines = preg_split('/\r\n|\r|\n/', trim($body));
        $processed = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $hash = sha1($sn . '|' . $line);
            if ($rawLogModel->alreadyProcessed($hash)) {
                continue; // el dispositivo reintento un push que ya guardamos
            }

            $cols = preg_split('/\t/', $line);
            $pin      = $cols[0] ?? null;
            $time     = $cols[1] ?? null;
            $verify   = $cols[3] ?? null;

            $logId = $rawLogModel->insert([
                'device_serial' => $sn,
                'table_name'    => 'ATTLOG',
                'raw_line'      => $line,
                'line_hash'     => $hash,
                'processed'     => 0,
                'created_at'    => date('Y-m-d H:i:s'),
            ], true);

            if (!$pin || !$time) {
                $rawLogModel->update($logId, ['error_message' => 'Linea incompleta']);
                continue;
            }

            $employee = $employeeModel->findByZktecoPin($pin);
            if (!$employee) {
                $rawLogModel->update($logId, ['error_message' => 'PIN no asociado a ningun empleado: ' . $pin]);
                continue;
            }

            $verifyMode = match ((string) $verify) {
                '1' => 'fingerprint',
                '15' => 'face',
                '2' => 'card',
                default => 'device',
            };

            $attendanceId = $attendance->insert([
                'employee_id'      => $employee['id'],
                'source_type'      => 'zkteco_device',
                'device_id'        => $device['id'] ?? null,
                'latitude'         => $device['latitude'] ?? null,
                'longitude'        => $device['longitude'] ?? null,
                'location_label'   => $device['location_label'] ?? null,
                'verify_mode'      => $verifyMode,
                'recorded_at'      => date('Y-m-d H:i:s', strtotime($time)),
                'raw_payload'      => $line,
                'created_at'       => date('Y-m-d H:i:s'),
            ], true);

            $rawLogModel->update($logId, ['processed' => 1, 'attendance_record_id' => $attendanceId]);
            $processed++;
        }

        return $processed;
    }

    private function logRaw(string $sn, string $table, string $body): void
    {
        $rawLogModel = new ZktecoRawLogModel();
        $hash = sha1($sn . '|' . $table . '|' . $body);

        if ($rawLogModel->alreadyProcessed($hash)) {
            return;
        }

        $rawLogModel->insert([
            'device_serial' => $sn,
            'table_name'    => $table,
            'raw_line'      => $body,
            'line_hash'     => $hash,
            'processed'     => 0,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    private function plain(string $text, int $status = 200)
    {
        return $this->response
            ->setStatusCode($status)
            ->setContentType('text/plain')
            ->setBody($text);
    }
}
