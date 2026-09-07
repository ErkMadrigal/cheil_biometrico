<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\AuthContext;
use App\Models\EmployeeEnrollTokenModel;
use App\Models\EmployeeFaceEmbeddingModel;
use App\Models\EmployeeModel;

/**
 * Ligas temporales de auto-enrolamiento (72h, un solo uso): en vez de que el empleado
 * tenga que pedirle acceso al panel a TI, un admin genera una liga desde Empleados y se
 * la manda por WhatsApp/correo. El empleado entra a /enrolar/{token} SIN LOGIN, confirma
 * que es el, se toma el rostro (paso "save"), y luego se le pide una SEGUNDA captura en
 * vivo (paso "verify") que se compara contra la que se acaba de guardar -- si no hace
 * match, no se da por bueno el enrolamiento. Esto evita que alguien enrole "a lo rapido"
 * con mala calidad/con la foto de alguien mas sin darse cuenta.
 */
class EnrollTokenController extends BaseController
{
    /**
     * POST /api/v1/employees/{id}/enroll-token   (JWT: admin/supervisor)
     * Genera una liga nueva de 72h para que el empleado se auto-enrole.
     */
    public function generate($employeeId = null)
    {
        $employeeModel = new EmployeeModel();
        $employee = $employeeModel->find($employeeId);
        if (!$employee) {
            return $this->fail('Empleado no encontrado.', 404);
        }

        $tokenModel = new EmployeeEnrollTokenModel();
        $row = $tokenModel->generateFor((int) $employeeId, AuthContext::id());

        return $this->ok([
            'token'      => $row['token'],
            'expires_at' => $row['expires_at'],
            'path'       => '/enrolar/' . $row['token'],
        ], 'Liga de enrolamiento generada.', 201);
    }

    /**
     * GET /api/v1/enroll/{token}   (publico, sin JWT)
     * Valida el token y regresa los datos del empleado para que confirme "si soy yo".
     */
    public function lookup($token = null)
    {
        $tokenModel = new EmployeeEnrollTokenModel();
        $row = $tokenModel->findValidToken((string) $token);
        $reason = $tokenModel->invalidReason($row);

        if ($reason !== null) {
            return $this->fail($this->reasonMessage($reason), 410, ['reason' => $reason]);
        }

        $employeeModel = new EmployeeModel();
        $employee = $employeeModel->where('status', 'active')->find($row['employee_id']);
        if (!$employee) {
            return $this->fail('El empleado de esta liga ya no esta activo.', 410, ['reason' => 'inactive_employee']);
        }

        return $this->ok([
            'employee_id'        => $employee['id'],
            'employee_number'    => $employee['employee_number'],
            'first_name'         => $employee['first_name'],
            'paternal_last_name' => $employee['paternal_last_name'],
            'maternal_last_name' => $employee['maternal_last_name'],
            'photo_path'         => $employee['photo_path'],
            'expires_at'         => $row['expires_at'],
        ]);
    }

    /**
     * POST /api/v1/enroll/{token}/save   (publico, sin JWT)
     * body: { "descriptor": [128 floats] }
     * Guarda el rostro capturado como referencia. Todavia NO marca el token como usado:
     * eso solo pasa si el paso de verificacion (abajo) tambien sale bien.
     */
    public function save($token = null)
    {
        $tokenModel = new EmployeeEnrollTokenModel();
        $row = $tokenModel->findValidToken((string) $token);
        if ($tokenModel->invalidReason($row) !== null) {
            return $this->fail($this->reasonMessage($tokenModel->invalidReason($row)), 410);
        }

        $body = $this->request->getJSON(true) ?? [];
        $descriptor = $body['descriptor'] ?? null;
        if (!is_array($descriptor) || count($descriptor) !== 128) {
            return $this->fail('El descriptor facial debe ser un arreglo de 128 numeros.', 422);
        }

        $embeddingModel = new EmployeeFaceEmbeddingModel();
        $embeddingModel->where('employee_id', $row['employee_id'])->set(['is_active' => 0])->update();
        $embeddingModel->insert([
            'employee_id' => $row['employee_id'],
            'descriptor'  => json_encode($descriptor),
            'source'      => 'enroll_link',
            'is_active'   => 1,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return $this->ok(null, 'Rostro capturado. Ahora vamos a confirmarlo.');
    }

    /**
     * POST /api/v1/enroll/{token}/verify   (publico, sin JWT)
     * body: { "descriptor": [128 floats] }
     * Segunda captura EN VIVO: se compara contra lo que se acaba de guardar en save().
     * Si hace match, AHI SI se marca el token como usado (ya no sirve para nada mas).
     */
    public function verify($token = null)
    {
        $tokenModel = new EmployeeEnrollTokenModel();
        $row = $tokenModel->findValidToken((string) $token);
        if ($tokenModel->invalidReason($row) !== null) {
            return $this->fail($this->reasonMessage($tokenModel->invalidReason($row)), 410);
        }

        $body = $this->request->getJSON(true) ?? [];
        $descriptor = $body['descriptor'] ?? null;
        if (!is_array($descriptor) || count($descriptor) !== 128) {
            return $this->fail('El descriptor facial debe ser un arreglo de 128 numeros.', 422);
        }

        $embeddingModel = new EmployeeFaceEmbeddingModel();
        $reference = $embeddingModel->activeForEmployee((int) $row['employee_id']);
        if (!$reference) {
            return $this->fail('Todavia no has capturado tu rostro. Regresa al paso anterior.', 422);
        }

        $refDescriptor = json_decode($reference['descriptor'], true);
        $distance = EmployeeFaceEmbeddingModel::euclideanDistance($refDescriptor, $descriptor);
        $matchScore = round($distance, 4);
        $threshold = (float) env('FACE_MATCH_THRESHOLD', 0.6);

        if ($distance > $threshold) {
            return $this->fail(
                'No pudimos confirmar que eres tu. Intenta de nuevo con mejor luz, o vuelve a capturar tu rostro.',
                422,
                ['face_distance' => $matchScore, 'threshold' => $threshold]
            );
        }

        $tokenModel->markUsed($row['id']);

        return $this->ok(['face_distance' => $matchScore], 'Rostro enrolado y verificado correctamente.');
    }

    private function reasonMessage(string $reason): string
    {
        return match ($reason) {
            'not_found' => 'Esta liga no es valida.',
            'used'      => 'Esta liga ya fue usada. Pide que te generen una nueva.',
            'expired'   => 'Esta liga ya expiro (duran 72 horas). Pide que te generen una nueva.',
            default     => 'Esta liga ya no es valida.',
        };
    }
}
