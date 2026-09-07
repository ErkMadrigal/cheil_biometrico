<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAttendanceRecordsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INT', 'unsigned' => true],
            'source_type' => ['type' => 'ENUM', 'constraint' => ['mobile_app', 'zkteco_device'], 'comment' => 'Origen del registro'],
            'device_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'comment' => 'Solo aplica si source_type = zkteco_device'],
            'latitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'longitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'accuracy_meters' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'location_label' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'photo_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'face_match_score' => ['type' => 'DECIMAL', 'constraint' => '6,4', 'null' => true],
            'verify_mode' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'comment' => 'face, fingerprint, etc'],
            'recorded_at' => ['type' => 'DATETIME', 'comment' => 'Fecha/hora real del registro (hora servidor)'],
            'raw_payload' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['employee_id', 'recorded_at']);
        $this->forge->addKey('device_id');
        $this->forge->addKey('source_type');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('device_id', 'devices', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('attendance_records');
    }

    public function down()
    {
        $this->forge->dropTable('attendance_records', true);
    }
}
