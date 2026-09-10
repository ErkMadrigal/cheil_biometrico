<?php

namespace App\Models;

use CodeIgniter\Model;

class IncidentTypeModel extends Model
{
    protected $table         = 'incident_types';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'is_active'];

    protected $validationRules = [
        'id'   => 'permit_empty|is_natural',
        'name' => 'required|max_length[100]|is_unique[incident_types.name,id,{id}]',
    ];

    protected $validationMessages = [
        'name' => ['is_unique' => 'Ya existe un tipo de incidencia con ese nombre.'],
    ];

    public function activeOnes(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }
}
