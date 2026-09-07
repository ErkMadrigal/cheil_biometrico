<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Zonas geograficas permitidas para checar asistencia (ej. "Oficina Polanco" 50km,
 * "Sucursal Toluca" 500m). Mientras no haya NINGUNA zona activa registrada, no se
 * restringe nada (asi el sistema no se rompe antes de que el admin configure zonas).
 */
class GeoZoneModel extends Model
{
    protected $table         = 'geo_zones';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'address_label', 'latitude', 'longitude', 'radius_meters', 'is_active'];

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'name'          => 'required|max_length[150]',
        'address_label' => 'permit_empty|max_length[255]',
        'latitude'      => 'required|decimal',
        'longitude'     => 'required|decimal',
        'radius_meters' => 'required|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'radius_meters' => ['is_natural_no_zero' => 'El radio debe ser un numero entero mayor a 0 (en metros).'],
    ];

    public function activeZones(): array
    {
        return $this->where('is_active', 1)->findAll();
    }

    /**
     * Distancia Haversine en metros entre dos coordenadas (linea recta, no por calles).
     */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMeters = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
    }

    /**
     * ¿Esta coordenada cae dentro de AL MENOS UNA zona activa? Si no hay zonas activas
     * configuradas todavia, regresa true (no bloquea nada por default).
     */
    public function isWithinAnyActiveZone(float $lat, float $lng): bool
    {
        $zones = $this->activeZones();
        if (empty($zones)) {
            return true;
        }

        foreach ($zones as $zone) {
            $distance = self::distanceMeters($lat, $lng, (float) $zone['latitude'], (float) $zone['longitude']);
            if ($distance <= (float) $zone['radius_meters']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Regla combinada para saber si una checada de ESTE empleado es valida en (lat,lng):
     *  1. Si el empleado tiene checkin_unrestricted (visita clientes) -> siempre valida.
     *  2. Si cae dentro de alguna zona geografica general activa -> valida.
     *  3. Si el empleado tiene su Home Office personal configurado y cae dentro de su
     *     radio (home_radius_meters, tipicamente 100m) -> valida.
     *  Si nada de eso aplica -> invalida.
     *
     * $employee es el array del empleado (de EmployeeModel), debe traer al menos
     * checkin_unrestricted, home_lat, home_lng, home_radius_meters.
     */
    public function isCheckinAllowedForEmployee(array $employee, float $lat, float $lng): bool
    {
        if (!empty($employee['checkin_unrestricted'])) {
            return true;
        }

        if ($this->isWithinAnyActiveZone($lat, $lng)) {
            return true;
        }

        if (($employee['home_lat'] ?? null) !== null && ($employee['home_lng'] ?? null) !== null) {
            $radius = (float) ($employee['home_radius_meters'] ?? 100);
            $distance = self::distanceMeters($lat, $lng, (float) $employee['home_lat'], (float) $employee['home_lng']);
            return $distance <= $radius;
        }

        return false;
    }
}
