<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeEnrollTokenModel extends Model
{
    protected $table         = 'employee_enroll_tokens';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['employee_id', 'token', 'expires_at', 'used_at', 'created_by', 'created_at'];

    /**
     * Genera un token de 72h para que el empleado se auto-enrole sin pasar por TI.
     */
    public function generateFor(int $employeeId, ?int $createdBy = null): array
    {
        $token = bin2hex(random_bytes(24)); // 48 caracteres, suficientemente unico

        $id = $this->insert([
            'employee_id' => $employeeId,
            'token'       => $token,
            'expires_at'  => date('Y-m-d H:i:s', strtotime('+72 hours')),
            'created_by'  => $createdBy,
            'created_at'  => date('Y-m-d H:i:s'),
        ], true);

        return $this->find($id);
    }

    /**
     * Como generateFor(), pero si el empleado YA tiene un token vigente sin usar
     * (ej. abrio la app, no termino de enrolarse, y la vuelve a abrir), reutiliza
     * ese mismo en vez de crear uno nuevo cada vez. Lo usa MobileController::lookup()
     * para el auto-enrolamiento dentro de la app (sin que el admin tenga que generar
     * la liga manualmente).
     */
    public function findOrCreateForEmployee(int $employeeId, ?int $createdBy = null): array
    {
        $existing = $this->where('employee_id', $employeeId)
            ->where('used_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->orderBy('id', 'DESC')
            ->first();

        return $existing ?? $this->generateFor($employeeId, $createdBy);
    }

    public function findValidToken(string $token): ?array
    {
        return $this->where('token', $token)
            ->where('used_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();
    }

    /**
     * Regresa por que un token no sirve (para dar un mensaje claro), o null si SI sirve.
     */
    public function invalidReason(?array $row): ?string
    {
        if (!$row) {
            return 'not_found';
        }
        if ($row['used_at'] !== null) {
            return 'used';
        }
        if (strtotime($row['expires_at']) <= time()) {
            return 'expired';
        }
        return null;
    }

    public function markUsed(int $id): void
    {
        $this->update($id, ['used_at' => date('Y-m-d H:i:s')]);
    }
}
