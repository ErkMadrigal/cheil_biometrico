<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ligas temporales de auto-enrolamiento: el admin genera un token para un empleado
 * (valido 72h, un solo uso) y se lo manda por WhatsApp/correo, sin que el empleado
 * tenga que pedirle a TI que le abra el panel. El empleado entra a /enrolar/{token},
 * confirma que es el, se toma el rostro, y luego se le pide una SEGUNDA captura para
 * verificar que de verdad es la misma persona antes de dar por bueno el enrolamiento.
 */
class CreateEmployeeEnrollTokensTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INT', 'unsigned' => true],
            'token' => ['type' => 'VARCHAR', 'constraint' => 64],
            'expires_at' => ['type' => 'DATETIME'],
            'used_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'comment' => 'users.id del admin que genero la liga'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token');
        $this->forge->addKey('employee_id');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('employee_enroll_tokens');
    }

    public function down()
    {
        $this->forge->dropTable('employee_enroll_tokens', true);
    }
}
