<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\GeoZoneModel;

/**
 * CRUD para las zonas geograficas permitidas (ej. "Oficina Polanco" 50km, "Sucursal
 * Toluca" 500m). Estas zonas se usan para BLOQUEAR checadas (kiosko/app movil) cuyas
 * coordenadas no caigan dentro de ninguna zona activa -ver GeoZoneModel::isWithinAnyActiveZone()-.
 */
class GeoZoneController extends BaseController
{
    protected GeoZoneModel $zones;

    public function __construct()
    {
        $this->zones = new GeoZoneModel();
    }

    public function index()
    {
        return $this->ok($this->zones->orderBy('name', 'ASC')->findAll());
    }

    public function show($id = null)
    {
        $zone = $this->zones->find($id);
        if (!$zone) {
            return $this->fail('Zona no encontrada.', 404);
        }
        return $this->ok($zone);
    }

    public function create()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        // Mismo motivo que en EmployeeController/DeviceController: is_natural en "id"
        // deja que el placeholder {id} de las reglas is_unique resuelva bien; aqui no
        // hay ninguna regla is_unique todavia, pero se deja el mismo patron por consistencia.
        $data['id'] = $data['id'] ?? 0;

        if (!$this->zones->validate($data)) {
            return $this->fail('Datos invalidos.', 422, $this->zones->errors());
        }

        $id = $this->zones->insert($data, true);
        return $this->ok($this->zones->find($id), 'Zona creada.', 201);
    }

    public function update($id = null)
    {
        $zone = $this->zones->find($id);
        if (!$zone) {
            return $this->fail('Zona no encontrada.', 404);
        }

        $raw  = (string) $this->request->getBody();
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $data = $this->request->getMethod() === 'put' ? $this->request->getRawInput() : $this->request->getPost();
        }
        $data = $data ?? [];

        $merged = array_merge($zone, $data, ['id' => $id]);
        if (!$this->zones->validate($merged)) {
            return $this->fail('Datos invalidos.', 422, $this->zones->errors());
        }

        $this->zones->skipValidation(true)->update($id, $data);
        return $this->ok($this->zones->find($id), 'Zona actualizada.');
    }

    public function delete($id = null)
    {
        $zone = $this->zones->find($id);
        if (!$zone) {
            return $this->fail('Zona no encontrada.', 404);
        }

        $this->zones->delete($id);
        return $this->ok(null, 'Zona eliminada.');
    }
}
