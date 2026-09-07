<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateZktecoRawLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'device_serial' => ['type' => 'VARCHAR', 'constraint' => 50],
            'table_name' => ['type' => 'VARCHAR', 'constraint' => 30, 'comment' => 'ATTLOG, OPERLOG, etc'],
            'raw_line' => ['type' => 'TEXT'],
            'line_hash' => ['type' => 'CHAR', 'constraint' => 40, 'comment' => 'sha1 del raw_line para evitar duplicados por reintentos del dispositivo'],
            'processed' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'attendance_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'error_message' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('line_hash');
        $this->forge->addKey('device_serial');
        $this->forge->addForeignKey('attendance_record_id', 'attendance_records', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('zkteco_raw_logs');
    }

    public function down()
    {
        $this->forge->dropTable('zkteco_raw_logs', true);
    }
}
