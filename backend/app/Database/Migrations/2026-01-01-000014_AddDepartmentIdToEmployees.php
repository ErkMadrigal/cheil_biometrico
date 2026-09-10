<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega employees.department_id (catalogo departments). El viejo employees.department
 * (texto libre) se deja intacto por historial, pero ya no se usa en el panel -- desde
 * ahora el departamento es obligatorio y se elige del catalogo. Los empleados que ya
 * existian quedan con department_id en NULL a proposito (RH los va corrigiendo uno por
 * uno desde Empleados, no se intenta adivinar/emparejar automatico).
 */
class AddDepartmentIdToEmployees extends Migration
{
    public function up()
    {
        $this->forge->addColumn('employees', [
            'department_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'department',
            ],
        ]);
        $this->db->query('ALTER TABLE employees ADD INDEX idx_department (department_id)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE employees DROP INDEX idx_department');
        $this->forge->dropColumn('employees', 'department_id');
    }
}
