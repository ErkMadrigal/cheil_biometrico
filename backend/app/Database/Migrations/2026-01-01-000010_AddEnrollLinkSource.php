<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega 'enroll_link' como origen valido de un rostro enrolado: es cuando el
 * empleado se enrolo el mismo a distancia con la liga temporal de /enrolar/{token},
 * a diferencia de 'web_panel' (alguien de RH lo enrola en persona desde Empleados).
 */
class AddEnrollLinkSource extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('employee_face_embeddings', [
            'source' => [
                'type'       => 'ENUM',
                'constraint' => ['web_panel', 'mobile_app', 'enroll_link'],
                'default'    => 'web_panel',
                'null'       => false,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('employee_face_embeddings', [
            'source' => [
                'type'       => 'ENUM',
                'constraint' => ['web_panel', 'mobile_app'],
                'default'    => 'web_panel',
                'null'       => false,
            ],
        ]);
    }
}
