<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeeFaceEmbeddingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INT', 'unsigned' => true],
            'descriptor' => ['type' => 'TEXT', 'comment' => 'Vector de 128 floats (face-api.js / TensorFlow.js) en JSON'],
            'source' => ['type' => 'ENUM', 'constraint' => ['web_panel', 'mobile_app'], 'default' => 'web_panel'],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('employee_id');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('employee_face_embeddings');
    }

    public function down()
    {
        $this->forge->dropTable('employee_face_embeddings', true);
    }
}
