<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega 'web_kiosk' como origen valido de una checada: es el registro biometrico
 * que se hace DESDE el web panel (numero de empleado/CURP/RFC + camara con
 * TensorFlow.js), sin necesidad de comprar un checador fisico adicional.
 */
class AddWebKioskSourceType extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('attendance_records', [
            'source_type' => [
                'type'       => 'ENUM',
                'constraint' => ['mobile_app', 'zkteco_device', 'web_kiosk'],
                'null'       => false,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('attendance_records', [
            'source_type' => [
                'type'       => 'ENUM',
                'constraint' => ['mobile_app', 'zkteco_device'],
                'null'       => false,
            ],
        ]);
    }
}
