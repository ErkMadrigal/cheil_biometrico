<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\IncidentTypeModel;

/**
 * Catalogo de tipos de incidencia (Mucho trafico, Siniestro en carretera, etc). Se
 * alimenta desde el mismo modulo de Incidencias en el panel, sin salir del flujo de
 * captura.
 */
class IncidentTypeController extends BaseController
{
    protected IncidentTypeModel $types;

    public function __construct()
    {
        $this->types = new IncidentTypeModel();
    }

    public function index()
    {
        return $this->ok($this->types->orderBy('name', 'ASC')->findAll());
    }

    public function create()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $data['id'] = $data['id'] ?? 0;

        if (!$this->types->validate($data)) {
            return $this->fail('Datos invalidos.', 422, $this->types->errors());
        }

        $id = $this->types->insert($data, true);
        return $this->ok($this->types->find($id), 'Tipo de incidencia creado.', 201);
    }

    public function update($id = null)
    {
        $type = $this->types->find($id);
        if (!$type) {
            return $this->fail('Tipo de incidencia no encontrado.', 404);
        }

        $raw  = (string) $this->request->getBody();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = $this->request->getMethod() === 'put' ? $this->request->getRawInput() : $this->request->getPost();
        }
        $data = $data ?? [];

        $merged = array_merge($type, $data, ['id' => $id]);
        if (!$this->types->validate($merged)) {
            return $this->fail('Datos invalidos.', 422, $this->types->errors());
        }

        $this->types->skipValidation(true)->update($id, $data);
        return $this->ok($this->types->find($id), 'Tipo de incidencia actualizado.');
    }

    public function delete($id = null)
    {
        $type = $this->types->find($id);
        if (!$type) {
            return $this->fail('Tipo de incidencia no encontrado.', 404);
        }

        // Baja logica: no borra tipos que ya se usaron en incidencias historicas.
        $this->types->skipValidation(true)->update($id, ['is_active' => 0]);
        return $this->ok(null, 'Tipo de incidencia desactivado.');
    }
}
