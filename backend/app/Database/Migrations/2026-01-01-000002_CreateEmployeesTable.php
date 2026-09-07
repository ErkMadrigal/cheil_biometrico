<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_number' => ['type' => 'VARCHAR', 'constraint' => 30, 'comment' => 'Numero de empleado visible/administrativo'],
            'zkteco_pin' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'comment' => 'PIN/ID con el que esta enrolado en el checador ZKTeco'],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'paternal_last_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'maternal_last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'curp' => ['type' => 'VARCHAR', 'constraint' => 18, 'null' => true],
            'rfc' => ['type' => 'VARCHAR', 'constraint' => 13, 'null' => true],
            'photo_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'position' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'comment' => 'Puesto'],
            'department' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'hire_date' => ['type' => 'DATE', 'null' => true],
            'status' => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('employee_number');
        $this->forge->addUniqueKey('zkteco_pin');
        $this->forge->addUniqueKey('curp');
        $this->forge->addUniqueKey('rfc');
        $this->forge->addKey('status');
        $this->forge->createTable('employees');
    }

    public function down()
    {
        $this->forge->dropTable('employees', true);
    }
}
