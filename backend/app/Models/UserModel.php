<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $allowedFields    = [
        'name', 'email', 'password_hash', 'role_id', 'employee_id',
        'is_active', 'last_login_at',
    ];

    protected $validationRules = [
        'name'     => 'required|min_length[2]|max_length[150]',
        'email'    => 'required|valid_email',
        'role_id'  => 'required|integer',
    ];

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Devuelve el usuario con el nombre de su rol y, si aplica, sus datos de empleado.
     */
    public function getWithRole(int $id): ?array
    {
        return $this->select('users.*, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->find($id);
    }
}
