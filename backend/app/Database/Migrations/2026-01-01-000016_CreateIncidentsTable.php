<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Incidencias reportadas (mucho trafico, siniestro en carretera, clima, etc). Es un
 * registro INDEPENDIENTE, no ligado a una checada/attendance_record especifica -- el
 * empleado afectado y la evidencia (foto/documento) son opcionales, el tipo es
 * obligatorio y se elige del catalogo incident_types.
 */
class CreateIncidentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'comment' => 'Opcional: a quien afecto la incidencia'],
            'incident_type_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'incident_date'     => ['type' => 'DATE', 'null' => false],
            'description'       => ['type' => 'TEXT', 'null' => true],
            'evidence_path'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'comment' => 'Opcional: foto/documento de evidencia'],
            'created_by'        => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'comment' => 'users.id de quien la capturo'],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('employee_id');
        $this->forge->addKey('incident_type_id');
        $this->forge->addKey('incident_date');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('incident_type_id', 'incident_types', 'id', '', 'RESTRICT');
        $this->forge->createTable('incidents');
    }

    public function down()
    {
        $this->forge->dropTable('incidents', true);
    }
}
