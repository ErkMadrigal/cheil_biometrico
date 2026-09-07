<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeFaceEmbeddingModel;
use App\Models\EmployeeModel;
use App\Models\GeoZoneModel;

/**
 * "Registro biometrico" del web panel: en vez de comprar otro checador fisico,
 * cualquier compu/tablet con camara (en recepcion, por ejemplo) puede correr esta
 * pantalla. El empleado teclea su numero/CURP/RFC (no hay password) y su identidad
 * la confirma la CAMARA con TensorFlow.js (face-api.js) comparando contra el rostro
 * que se enrolo desde el modulo de Empleados. Por eso estas rutas van SIN JwtAuthFilter
 * -las usa el propio empleado sin sesion- pero el checkin exige match facial real.
 */
class KioskController extends BaseController
{
    /**
     * POST /api/v1/kiosk/lookup
     * body: { "query": "EMP-0001" }  (numero de empleado, CURP o RFC)
     * Regresa SOLO datos de confirmacion visual. Nunca el descriptor facial.
     */
    public function lookup()
    {
        $body = $this->request->getJSON(true) ?? [];
        $query = trim((string) ($body['query'] ?? ''));

        if ($query === '') {
            return $this->fail('Escribe tu numero de empleado, CURP o RFC.', 422);
        }

        $employeeModel = new EmployeeModel();
        $employee = $employeeModel
            ->where('status', 'active')
            ->groupStart()
                ->where('employee_number', $query)
                ->orWhere('curp', strtoupper($query))
                ->orWhere('rfc', strtoupper($query))
            ->groupEnd()
            ->first();

        if (!$employee) {
            return $this->fail('No encontramos a nadie activo con ese dato. Revisa e intenta de nuevo.', 404);
        }

        $embeddingModel = new EmployeeFaceEmbeddingModel();
        $hasFace = (bool) $embeddingModel->activeForEmployee($employee['id']);

        return $this->ok([
            'id'                => $employee['id'],
            'employee_number'   => $employee['employee_number'],
            'first_name'        => $employee['first_name'],
            'paternal_last_name' => $employee['paternal_last_name'],
            'maternal_last_name' => $employee['maternal_last_name'],
            'position'          => $employee['position'],
            'photo_path'        => $employee['photo_path'],
            'has_face_enrolled' => $hasFace,
        ]);
    }

    /**
     * POST /api/v1/kiosk/checkin
     * body: { "employee_id": 1, "descriptor": [128 floats], "latitude": .., "longitude": .., "location_label": ".." }
     * El match facial es OBLIGATORIO aqui (a diferencia del checkin de la app movil,
     * donde es una segunda verificacion): es la unica prueba de identidad que hay.
     */
    public function checkin()
    {
        $body = $this->request->getJSON(true) ?? [];
        $employeeId = (int) ($body['employee_id'] ?? 0);
        $descriptor = $body['descriptor'] ?? null;

        if (!$employeeId || !is_array($descriptor) || count($descriptor) !== 128) {
            return $this->fail('Faltan datos para registrar la checada.', 422);
        }

        $employeeModel = new EmployeeModel();
        $employee = $employeeModel->where('status', 'active')->find($employeeId);
        if (!$employee) {
            return $this->fail('Empleado no encontrado o inactivo.', 404);
        }

        $embeddingModel = new EmployeeFaceEmbeddingModel();
        $reference = $embeddingModel->activeForEmployee($employeeId);
        if (!$reference) {
            return $this->fail('Este empleado todavia no tiene un rostro enrolado. Pide a RH que lo enrole desde el panel.', 422);
        }

        $refDescriptor = json_decode($reference['descriptor'], true);
        $distance = EmployeeFaceEmbeddingModel::euclideanDistance($refDescriptor, $descriptor);
        $matchScore = round($distance, 4);
        $threshold = (float) env('FACE_MATCH_THRESHOLD', 0.6);

        if ($distance > $threshold) {
            // 422 y no 401 a proposito: el 401 lo intercepta el web panel como "sesion
            // expirada" y aqui no hay sesion, es solo que el rostro no hizo match.
            return $this->fail(
                'No pudimos verificar tu identidad con la camara. Intenta de nuevo con mejor luz.',
                422,
                ['face_distance' => $matchScore, 'threshold' => $threshold]
            );
        }

        // Si el kiosko manda coordenadas, deben caer dentro de alguna zona geografica
        // activa (ver GeoZoneController/GeoZoneModel). Si no hay zonas configuradas
        // todavia, o el kiosko no manda coordenadas, no se bloquea nada.
        $latitude  = $body['latitude'] ?? null;
        $longitude = $body['longitude'] ?? null;
        if ($latitude !== null && $longitude !== null) {
            $zoneModel = new GeoZoneModel();
            if (!$zoneModel->isWithinAnyActiveZone((float) $latitude, (float) $longitude)) {
                return $this->fail(
                    'Tu ubicacion esta fuera de las zonas permitidas para registrar asistencia.',
                    422,
                    ['reason' => 'outside_geo_zone']
                );
            }
        }

        $attendance = new AttendanceRecordModel();

        $isFirstOfDay = !$attendance->hasCheckedInToday($employeeId);

        $id = $attendance->insert([
            'employee_id'      => $employeeId,
            'source_type'      => 'web_kiosk',
            'latitude'         => $body['latitude'] ?? null,
            'longitude'        => $body['longitude'] ?? null,
            'location_label'   => $body['location_label'] ?? 'Registro biometrico (web panel)',
            'face_match_score' => $matchScore,
            'verify_mode'      => 'face',
            'recorded_at'      => date('Y-m-d H:i:s'),
            'raw_payload'      => json_encode(['via' => 'web_kiosk']),
        ], true);

        $record = $attendance->find($id);

        return $this->ok([
            'record'   => $record,
            'employee' => [
                'id'   => $employee['id'],
                'name' => trim($employee['first_name'] . ' ' . $employee['paternal_last_name'] . ' ' . ($employee['maternal_last_name'] ?? '')),
            ],
            'type' => $isFirstOfDay ? 'entrada' : 'checkpoint',
        ], 'Checada registrada.', 201);
    }
}
