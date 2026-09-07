<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDevicesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'serial_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'comment' => 'SN reportado por el checador ZKTeco'],
            'type' => ['type' => 'ENUM', 'constraint' => ['fingerprint_entry', 'face_exit', 'other'], 'default' => 'other'],
            'location_label' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'latitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'longitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('serial_number');
        $this->forge->createTable('devices');
    }

    public function down()
    {
        $this->forge->dropTable('devices', true);
    }
}
