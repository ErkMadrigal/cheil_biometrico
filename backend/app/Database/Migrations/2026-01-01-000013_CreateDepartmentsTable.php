<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catalogo de departamentos de la empresa. Antes "departamento" era texto libre en
 * employees.department (capturado distinto cada vez, con errores, vacio, etc). Ahora
 * es un catalogo cerrado que el cliente mismo va alimentando (ver DepartmentController),
 * y employees.department_id es obligatorio al dar de alta/editar un empleado.
 */
class CreateDepartmentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('departments');

        // Seed inicial con los departamentos que ya maneja el cliente. Desde el panel
        // (modulo Departamentos) pueden agregar/desactivar mas despues.
        $now = date('Y-m-d H:i:s');
        $names = [
            'Retail Center', 'Creative', 'Client Service', 'Account Service', 'Finance',
            'Project Manager', 'Digital', 'FFM Center', 'Human Resources', 'México',
            'Planning', 'General Services', 'New Business', 'Corporate Service',
        ];
        $rows = array_map(fn ($name) => [
            'name'       => $name,
            'is_active'  => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $names);
        $this->db->table('departments')->insertBatch($rows);
    }

    public function down()
    {
        $this->forge->dropTable('departments', true);
    }
}
