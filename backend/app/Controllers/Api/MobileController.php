<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeEnrollTokenModel;
use App\Models\EmployeeFaceEmbeddingModel;
use App\Models\EmployeeModel;
use App\Models\GeoZoneModel;

/**
 * App movil (Ionic + Capacitor) que cada empleado instala en su propio telefono.
 * Misma nomenclatura y logica que el "registro biometrico" del web panel (KioskController):
 * el empleado se identifica con numero/CURP/RFC (sin password) y la CAMARA confirma su
 * identidad con face-api.js contra el rostro enrolado. Va SIN JwtAuthFilter por la misma
 * razon que el kiosko: no hay sesion, la camara es la unica prueba de identidad.
 *
 * La UNICA diferencia real con el kiosko es el "source_type" que se guarda (mobile_app en
 * vez de web_kiosk), para que el mapa/reportes/badges del panel distingan correctamente una
 * checada hecha desde el celular del empleado de una hecha en la pantalla compartida de
 * recepcion. Tambien aqui la ubicacion es OBLIGATORIA (ademas del bloqueo que ya hace la app
 * en el cliente): sin lat/long no se guarda nada, para que nunca quede una checada "ciega".
 */
class MobileController extends BaseController
{
    /**
     * POST /api/v1/mobile/lookup
     * body: { "query": "EMP-0001" }  (numero de empleado, CURP o RFC)
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

        // Si el empleado todavia no tiene rostro enrolado, le generamos (o reusamos) un
        // token de auto-enrolamiento para que la app misma lo enrole ahi mismo, sin que
        // el admin tenga que mandarle la liga desde el panel. Reusa el mismo mecanismo
        // (72h, un solo uso) que ya usa la liga web de RH.
        $enrollToken = null;
        if (!$hasFace) {
            $tokenRow = (new EmployeeEnrollTokenModel())->findOrCreateForEmployee($employee['id']);
            $enrollToken = $tokenRow['token'];
        }

        return $this->ok([
            'id'                 => $employee['id'],
            'employee_number'    => $employee['employee_number'],
            'first_name'         => $employee['first_name'],
            'paternal_last_name' => $employee['paternal_last_name'],
            'maternal_last_name' => $employee['maternal_last_name'],
            'position'           => $employee['position'],
            'photo_path'         => $employee['photo_path'],
            'has_face_enrolled'  => $hasFace,
            'enroll_token'       => $enrollToken,
        ]);
    }

    /**
     * POST /api/v1/mobile/checkin
     * body: { "employee_id": 1, "descriptor": [128 floats], "latitude": .., "longitude": ..,
     *         "accuracy_meters": .., "location_label": ".." }
     * latitude/longitude son OBLIGATORIOS: sin ubicacion no hay checada.
     */
    public function checkin()
    {
        $body = $this->request->getJSON(true) ?? [];
        $employeeId = (int) ($body['employee_id'] ?? 0);
        $descriptor = $body['descriptor'] ?? null;
        $latitude   = $body['latitude'] ?? null;
        $longitude  = $body['longitude'] ?? null;

        if (!$employeeId || !is_array($descriptor) || count($descriptor) !== 128) {
            return $this->fail('Faltan datos para registrar la checada.', 422);
        }

        if ($latitude === null || $longitude === null) {
            return $this->fail('Necesitamos tu ubicacion para poder registrar tu asistencia. Activa el GPS y da permiso de ubicacion en la app.', 422);
        }

        $employeeModel = new EmployeeModel();
        $employee = $employeeModel->where('status', 'active')->find($employeeId);
        if (!$employee) {
            return $this->fail('Empleado no encontrado o inactivo.', 404);
        }

        // Coordenadas deben caer dentro de alguna zona geografica general activa, O dentro
        // del Home Office personal del empleado (si ya lo configuro), O el empleado tiene
        // permiso de checar desde cualquier lugar por visitar clientes. Ver
        // GeoZoneModel::isCheckinAllowedForEmployee().
        $zoneModel = new GeoZoneModel();
        if (!$zoneModel->isCheckinAllowedForEmployee($employee, (float) $latitude, (float) $longitude)) {
            return $this->fail(
                'Tu ubicacion esta fuera de las zonas permitidas para registrar asistencia.',
                422,
                ['reason' => 'outside_geo_zone']
            );
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
            // 422 y no 401 a proposito: no hay sesion, solo es que el rostro no hizo match.
            return $this->fail(
                'No pudimos verificar tu identidad con la camara. Intenta de nuevo con mejor luz.',
                422,
                ['face_distance' => $matchScore, 'threshold' => $threshold]
            );
        }

        $attendance = new AttendanceRecordModel();

        $isFirstOfDay = !$attendance->hasCheckedInToday($employeeId);

        $id = $attendance->insert([
            'employee_id'      => $employeeId,
            'source_type'      => 'mobile_app',
            'latitude'         => $latitude,
            'longitude'        => $longitude,
            'accuracy_meters'  => $body['accuracy_meters'] ?? null,
            'location_label'   => $body['location_label'] ?? null,
            'face_match_score' => $matchScore,
            'verify_mode'      => 'face',
            'recorded_at'      => date('Y-m-d H:i:s'),
            'raw_payload'      => json_encode(['via' => 'mobile_app']),
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

    /**
     * POST /api/v1/mobile/home-location
     * body: { "employee_id": 1, "descriptor": [128 floats], "latitude": .., "longitude": .. }
     *
     * El empleado configura (o reconfigura, si un admin lo desbloqueo) su ubicacion "Home
     * Office" personal, con un margen de HOME_RADIUS_METERS (100m por default) para checar
     * su entrada. Requiere verificacion facial (una sola captura comparada contra el rostro
     * ya enrolado, mismo umbral que checkin()) para que no cualquiera pueda "mover" la casa
     * de otro empleado con solo saber su numero. Una vez guardada queda BLOQUEADA
     * (home_location_locked=1): si el empleado se muda, un admin debe desbloquearla desde
     * el panel para que la pueda volver a capturar.
     */
    public function homeLocation()
    {
        $body = $this->request->getJSON(true) ?? [];
        $employeeId = (int) ($body['employee_id'] ?? 0);
        $descriptor = $body['descriptor'] ?? null;
        $latitude   = $body['latitude'] ?? null;
        $longitude  = $body['longitude'] ?? null;

        if (!$employeeId || !is_array($descriptor) || count($descriptor) !== 128) {
            return $this->fail('Faltan datos para configurar tu ubicacion.', 422);
        }
        if ($latitude === null || $longitude === null) {
            return $this->fail('Necesitamos tu ubicacion. Activa el GPS y da permiso de ubicacion en la app.', 422);
        }

        $employeeModel = new EmployeeModel();
        $employee = $employeeModel->where('status', 'active')->find($employeeId);
        if (!$employee) {
            return $this->fail('Empleado no encontrado o inactivo.', 404);
        }

        if (!empty($employee['home_location_locked'])) {
            return $this->fail(
                'Tu ubicacion Home Office ya esta fija. Pide a RH que la desbloquee si te mudaste.',
                422,
                ['reason' => 'locked']
            );
        }

        $embeddingModel = new EmployeeFaceEmbeddingModel();
        $reference = $embeddingModel->activeForEmployee($employeeId);
        if (!$reference) {
            return $this->fail('Todavia no tienes un rostro enrolado. Enrolate primero.', 422);
        }

        $refDescriptor = json_decode($reference['descriptor'], true);
        $distance = EmployeeFaceEmbeddingModel::euclideanDistance($refDescriptor, $descriptor);
        $matchScore = round($distance, 4);
        $threshold = (float) env('FACE_MATCH_THRESHOLD', 0.6);

        if ($distance > $threshold) {
            return $this->fail(
                'No pudimos confirmar que eres tu. Intenta de nuevo con mejor luz.',
                422,
                ['face_distance' => $matchScore, 'threshold' => $threshold]
            );
        }

        $employeeModel->skipValidation(true)->update($employeeId, [
            'home_lat'              => $latitude,
            'home_lng'              => $longitude,
            'home_radius_meters'    => 100,
            'home_location_locked'  => 1,
            'home_location_set_at'  => date('Y-m-d H:i:s'),
        ]);

        $updated = $employeeModel->find($employeeId);

        return $this->ok([
            'home_lat'             => $updated['home_lat'],
            'home_lng'             => $updated['home_lng'],
            'home_radius_meters'   => (int) $updated['home_radius_meters'],
            'home_location_set_at' => $updated['home_location_set_at'],
        ], 'Ubicacion Home Office guardada.');
    }
}
