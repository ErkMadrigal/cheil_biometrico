<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ubicacion "Home Office" personal de cada empleado (para quienes trabajan desde casa):
 * el empleado la configura EL MISMO desde la app (con verificacion facial, ver
 * MobileController::homeLocation()), jalando el GPS exacto de su telefono. Una vez
 * fijada queda bloqueada (home_location_locked) para que no la pueda cambiar solo -
 * si se muda, un admin la desbloquea desde el panel y el empleado la vuelve a capturar.
 *
 * checkin_unrestricted es para el caso contrario: empleados que visitan clientes y
 * necesitan poder checar desde CUALQUIER lugar (sin validar contra zonas ni home office),
 * activable desde el nuevo modulo "Movilidad" del panel.
 */
class AddHomeOfficeToEmployees extends Migration
{
    public function up()
    {
        $this->forge->addColumn('employees', [
            'home_lat' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,7',
                'null'       => true,
                'after'      => 'status',
            ],
            'home_lng' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,7',
                'null'       => true,
                'after'      => 'home_lat',
            ],
            'home_radius_meters' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'default'    => 100,
                'null'       => false,
                'after'      => 'home_lng',
            ],
            'home_location_locked' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'home_radius_meters',
            ],
            'home_location_set_at' => [
                'type'       => 'DATETIME',
                'null'       => true,
                'after'      => 'home_location_locked',
            ],
            'checkin_unrestricted' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'home_location_set_at',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('employees', [
            'home_lat',
            'home_lng',
            'home_radius_meters',
            'home_location_locked',
            'home_location_set_at',
            'checkin_unrestricted',
        ]);
    }
}
