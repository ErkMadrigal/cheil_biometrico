<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Incidencias (mucho trafico, siniestro en carretera, clima, etc). Registro
 * independiente -- no se liga a una checada/attendance_record especifica. El
 * empleado afectado y la evidencia son opcionales; el tipo es obligatorio.
 */
class IncidentModel extends Model
{
    protected $table         = 'incidents';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'employee_id', 'incident_type_id', 'incident_date', 'description',
        'evidence_path', 'created_by',
    ];

    protected $validationRules = [
        'id'                => 'permit_empty|is_natural',
        'employee_id'       => 'permit_empty|is_natural_no_zero',
        'incident_type_id'  => 'required|is_natural_no_zero',
        'incident_date'     => 'required|valid_date',
        'description'       => 'permit_empty',
        'evidence_path'     => 'permit_empty',
        'created_by'        => 'permit_empty|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'incident_type_id' => [
            'required'          => 'Selecciona el tipo de incidencia.',
            'is_natural_no_zero' => 'El tipo de incidencia no es valido.',
        ],
        'incident_date' => ['required' => 'La fecha de la incidencia es obligatoria.'],
    ];

    /**
     * Listado paginado con filtros para el panel: por empleado, por departamento
     * (via el employee_id -> employees.department_id), por tipo de incidencia y
     * por rango de fechas. Todos opcionales.
     */
    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $builder = $this->select(
            'incidents.*, incident_types.name as incident_type_name,
             employees.first_name, employees.paternal_last_name, employees.maternal_last_name,
             employees.employee_number, employees.department_id, departments.name as department_name'
        )
            ->join('incident_types', 'incident_types.id = incidents.incident_type_id')
            ->join('employees', 'employees.id = incidents.employee_id', 'left')
            ->join('departments', 'departments.id = employees.department_id', 'left');

        if (!empty($filters['employee_id'])) {
            $builder->where('incidents.employee_id', $filters['employee_id']);
        }
        if (!empty($filters['department_id'])) {
            $builder->where('employees.department_id', $filters['department_id']);
        }
        if (!empty($filters['incident_type_id'])) {
            $builder->where('incidents.incident_type_id', $filters['incident_type_id']);
        }
        if (!empty($filters['date_from'])) {
            $builder->where('incidents.incident_date >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $builder->where('incidents.incident_date <=', $filters['date_to']);
        }

        $total = $builder->countAllResults(false);

        $rows = $builder->orderBy('incidents.incident_date', 'DESC')
            ->orderBy('incidents.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function findWithDetail(int $id): ?array
    {
        return $this->select(
            'incidents.*, incident_types.name as incident_type_name,
             employees.first_name, employees.paternal_last_name, employees.maternal_last_name,
             employees.employee_number, employees.department_id, departments.name as department_name'
        )
            ->join('incident_types', 'incident_types.id = incidents.incident_type_id')
            ->join('employees', 'employees.id = incidents.employee_id', 'left')
            ->join('departments', 'departments.id = employees.department_id', 'left')
            ->where('incidents.id', $id)
            ->first();
    }
}
