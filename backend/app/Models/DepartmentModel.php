<?php

namespace App\Models;

use CodeIgniter\Model;

class DepartmentModel extends Model
{
    protected $table         = 'departments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'is_active'];

    protected $validationRules = [
        // Ver el mismo comentario en EmployeeModel: {id} necesita su propia regla.
        'id'   => 'permit_empty|is_natural',
        'name' => 'required|max_length[100]|is_unique[departments.name,id,{id}]',
    ];

    protected $validationMessages = [
        'name' => ['is_unique' => 'Ya existe un departamento con ese nombre.'],
    ];

    public function activeOnes(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }
}
