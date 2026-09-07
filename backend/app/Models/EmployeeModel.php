<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table            = 'employees';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $allowedFields    = [
        'employee_number', 'zkteco_pin', 'first_name', 'paternal_last_name',
        'maternal_last_name', 'curp', 'rfc', 'photo_path', 'email', 'phone',
        'position', 'department', 'hire_date', 'status',
        // Home Office personal (la fija el empleado desde la app, ver MobileController::homeLocation())
        'home_lat', 'home_lng', 'home_radius_meters', 'home_location_locked', 'home_location_set_at',
        // Empleados que visitan clientes: pueden checar desde cualquier lugar (modulo "Movilidad")
        'checkin_unrestricted',
    ];

    protected $validationRules = [
        // CI4 exige que el campo referenciado por el placeholder {id} tenga su propia
        // regla (aunque sea permisiva); sin esto, is_unique[...,id,{id}] truena con
        // "No validation rules for the placeholder: id". Aceptamos 0 (alta, sin id real
        // que excluir todavia) y cualquier entero positivo (edicion).
        'id'                   => 'permit_empty|is_natural',
        'employee_number'     => 'required|max_length[30]|is_unique[employees.employee_number,id,{id}]',
        'first_name'          => 'required|max_length[100]',
        'paternal_last_name'  => 'required|max_length[100]',
        'maternal_last_name'  => 'permit_empty|max_length[100]',
        'curp'                => 'permit_empty|max_length[18]|is_unique[employees.curp,id,{id}]',
        'rfc'                 => 'permit_empty|max_length[13]|is_unique[employees.rfc,id,{id}]',
        'email'               => 'permit_empty|valid_email',
        'zkteco_pin'          => 'permit_empty|max_length[30]|is_unique[employees.zkteco_pin,id,{id}]',
        'home_lat'                => 'permit_empty|decimal',
        'home_lng'                => 'permit_empty|decimal',
        'home_radius_meters'      => 'permit_empty|is_natural_no_zero',
        'home_location_locked'    => 'permit_empty|in_list[0,1]',
        'home_location_set_at'    => 'permit_empty',
        'checkin_unrestricted'    => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'employee_number' => [
            'is_unique' => 'Ya existe un empleado con ese numero de empleado.',
        ],
        'curp' => ['is_unique' => 'Ya existe un empleado con esa CURP.'],
        'rfc'  => ['is_unique' => 'Ya existe un empleado con ese RFC.'],
    ];

    public function fullName(array $employee): string
    {
        return trim($employee['first_name'] . ' ' . $employee['paternal_last_name'] . ' ' . ($employee['maternal_last_name'] ?? ''));
    }

    public function findByZktecoPin(string $pin): ?array
    {
        return $this->where('zkteco_pin', $pin)->first();
    }
}
