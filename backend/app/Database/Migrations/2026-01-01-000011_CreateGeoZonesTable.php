<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Zonas geograficas permitidas para registrar asistencia (ej. "Oficina Polanco" con
 * radio de 50km, "Sucursal Toluca" con radio de 500m). Si hay al menos una zona activa,
 * una checada con coordenadas que no caiga dentro de NINGUNA zona se rechaza (ver
 * GeoZoneModel::isWithinAnyActiveZone(), usado en Kiosk/Mobile/AttendanceController).
 */
class CreateGeoZonesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'address_label' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'latitude' => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'longitude' => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'radius_meters' => ['type' => 'INT', 'unsigned' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('is_active');
        $this->forge->createTable('geo_zones');
    }

    public function down()
    {
        $this->forge->dropTable('geo_zones', true);
    }
}
