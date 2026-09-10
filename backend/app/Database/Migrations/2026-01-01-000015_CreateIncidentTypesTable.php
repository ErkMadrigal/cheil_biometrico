<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catalogo de tipos de incidencia (Mucho trafico, Siniestro en carretera, etc). El
 * cliente lo va alimentando desde el panel (modulo Incidencias -> Tipos), igual que
 * el catalogo de departamentos.
 */
class CreateIncidentTypesTable extends Migration
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
        $this->forge->createTable('incident_types');

        $now = date('Y-m-d H:i:s');
        $names = [
            'Mucho trafico', 'Siniestro en carretera', 'Clima (lluvia, granizo, etc)',
            'Falla de transporte publico', 'Otro',
        ];
        $rows = array_map(fn ($name) => [
            'name' => $name, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ], $names);
        $this->db->table('incident_types')->insertBatch($rows);
    }

    public function down()
    {
        $this->forge->dropTable('incident_types', true);
    }
}
