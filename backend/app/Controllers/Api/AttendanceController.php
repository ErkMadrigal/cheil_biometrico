<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\AuthContext;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeFaceEmbeddingModel;
use App\Models\EmployeeModel;
use App\Models\GeoZoneModel;

class AttendanceController extends BaseController
{
    protected AttendanceRecordModel $attendance;

    public function __construct()
    {
        $this->attendance = new AttendanceRecordModel();
    }

    /**
     * GET /api/v1/attendance?employee_id=&department_id=&date_from=&date_to=&source_type=&page=&per_page=
     * Usado por el web panel para listar/filtrar registros.
     */
    public function index()
    {
        $filters = [
            'employee_id'   => $this->request->getGet('employee_id'),
            'department_id' => $this->request->getGet('department_id'),
            'date_from'     => $this->request->getGet('date_from'),
            'date_to'       => $this->request->getGet('date_to'),
            'source_type'   => $this->request->getGet('source_type'),
        ];
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);

        return $this->ok($this->attendance->search($filters, $page, $perPage));
    }

    /**
     * GET /api/v1/attendance/today-map
     * "Donde esta cada quien ahorita": ultima ubicacion de hoy de cada empleado que
     * ya checo, mas su recorrido completo del dia (para dibujar la ruta en el mapa).
     */
    public function todayMap()
    {
        return $this->ok($this->attendance->todayLocations());
    }

    /**
     * POST /api/v1/attendance/checkin  (requiere JWT de empleado, desde la app movil)
     * body JSON:
     * {
     *   "latitude": 19.4326, "longitude": -99.1332, "accuracy": 12.3,
     *   "location_label": "Presidente Masaryk 111, Polanco" (opcional, reverse geocode en la app),
     *   "photo_base64": "data:image/jpeg;base64,....",
     *   "descriptor": [128 floats]  (opcional, para doble verificacion server-side)
     * }
     */
    public function checkin()
    {
        $employeeId = AuthContext::employeeId();
        if (!$employeeId) {
            return $this->fail('Este usuario no tiene un empleado asociado.', 422);
        }

        $body = $this->request->getJSON(true) ?? [];

        $rules = [
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ];
        if (!$this->validateInput($body, $rules)) {
            return $this->fail('Datos invalidos.', 422, $this->validator->getErrors());
        }

        $employee = (new EmployeeModel())->find($employeeId);
        if (!$employee) {
            return $this->fail('Este usuario no tiene un empleado asociado.', 422);
        }

        $zoneModel = new GeoZoneModel();
        if (!$zoneModel->isCheckinAllowedForEmployee($employee, (float) $body['latitude'], (float) $body['longitude'])) {
            return $this->fail(
                'Tu ubicacion esta fuera de las zonas permitidas para registrar asistencia.',
                422,
                ['reason' => 'outside_geo_zone']
            );
        }

        $matchScore = null;
        if (!empty($body['descriptor']) && is_array($body['descriptor']) && count($body['descriptor']) === 128) {
            $embeddingModel = new EmployeeFaceEmbeddingModel();
            $reference = $embeddingModel->activeForEmployee($employeeId);

            if ($reference) {
                $refDescriptor = json_decode($reference['descriptor'], true);
                $distance = EmployeeFaceEmbeddingModel::euclideanDistance($refDescriptor, $body['descriptor']);
                $matchScore = round($distance, 4);

                $threshold = (float) env('FACE_MATCH_THRESHOLD', 0.6);
                if ($distance > $threshold) {
                    return $this->fail(
                        'El rostro no coincide con el registrado para este empleado.',
                        422,
                        ['face_distance' => $matchScore, 'threshold' => $threshold]
                    );
                }
            }
        }

        $photoPath = null;
        if (!empty($body['photo_base64'])) {
            $photoPath = $this->storeBase64Photo($body['photo_base64'], 'attendance');
        }

        $id = $this->attendance->insert([
            'employee_id'      => $employeeId,
            'source_type'      => 'mobile_app',
            'latitude'         => $body['latitude'],
            'longitude'        => $body['longitude'],
            'accuracy_meters'  => $body['accuracy'] ?? null,
            'location_label'   => $body['location_label'] ?? null,
            'photo_path'       => $photoPath,
            'face_match_score' => $matchScore,
            'verify_mode'      => 'face',
            'recorded_at'      => date('Y-m-d H:i:s'),
            'raw_payload'      => json_encode(['accuracy' => $body['accuracy'] ?? null]),
        ], true);

        return $this->ok($this->attendance->find($id), 'Registro guardado.', 201);
    }

    /**
     * GET /api/v1/attendance/mine?date_from=&date_to=  (app movil: historial propio)
     */
    public function mine()
    {
        $employeeId = AuthContext::employeeId();
        if (!$employeeId) {
            return $this->fail('Este usuario no tiene un empleado asociado.', 422);
        }

        $filters = [
            'employee_id' => $employeeId,
            'date_from'   => $this->request->getGet('date_from'),
            'date_to'     => $this->request->getGet('date_to'),
        ];

        return $this->ok($this->attendance->search($filters, 1, 100));
    }

    /**
     * GET /api/v1/attendance/day-detail/{employeeId}/{date}
     * Detalle de un dia especifico marcando entrada/salida/checkpoints.
     */
    public function dayDetail($employeeId = null, $date = null)
    {
        if (!$employeeId || !$date) {
            return $this->fail('Faltan parametros.', 422);
        }

        return $this->ok($this->attendance->dayDetail((int) $employeeId, $date));
    }

    private function storeBase64Photo(string $base64, string $subfolder): ?string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            $ext = strtolower($type[1]) === 'jpeg' ? 'jpg' : strtolower($type[1]);
            $base64 = substr($base64, strpos($base64, ',') + 1);
        } else {
            $ext = 'jpg';
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return null;
        }

        $dir = WRITEPATH . 'uploads/' . $subfolder;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        file_put_contents($dir . '/' . $filename, $decoded);

        return 'uploads/' . $subfolder . '/' . $filename;
    }
}
