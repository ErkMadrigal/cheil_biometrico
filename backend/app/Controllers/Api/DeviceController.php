<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\DeviceModel;

/**
 * CRUD para administrar los checadores ZKTeco (y a futuro cualquier otro dispositivo)
 * dados de alta en el sistema. El registro automatico al primer handshake ADMS
 * ocurre en Zkteco\AdmsController; aqui solo se administra/edita.
 */
class DeviceController extends BaseController
{
    protected DeviceModel $devices;

    public function __construct()
    {
        $this->devices = new DeviceModel();
    }

    public function index()
    {
        return $this->ok($this->devices->orderBy('name', 'ASC')->findAll());
    }

    public function show($id = null)
    {
        $device = $this->devices->find($id);
        if (!$device) {
            return $this->fail('Dispositivo no encontrado.', 404);
        }
        return $this->ok($device);
    }

    public function create()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        // Mismo motivo que en EmployeeController: is_unique[...,id,{id}] necesita un
        // "id" para excluir; en un alta nueva no hay id real todavia, usamos 0.
        $data['id'] = $data['id'] ?? 0;

        if (!$this->devices->validate($data)) {
            return $this->fail('Datos invalidos.', 422, $this->devices->errors());
        }

        $id = $this->devices->insert($data, true);
        return $this->ok($this->devices->find($id), 'Dispositivo creado.', 201);
    }

    public function update($id = null)
    {
        $device = $this->devices->find($id);
        if (!$device) {
            return $this->fail('Dispositivo no encontrado.', 404);
        }

        // Ver el mismo comentario en EmployeeController::update(): se decodifica el body
        // a mano porque getJSON() puede regresar null con PUT aunque el body si sea JSON valido.
        $raw  = (string) $this->request->getBody();
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $data = $this->request->getMethod() === 'put' ? $this->request->getRawInput() : $this->request->getPost();
        }
        $data = $data ?? [];

        $merged = array_merge($device, $data, ['id' => $id]);
        if (!$this->devices->validate($merged)) {
            return $this->fail('Datos invalidos.', 422, $this->devices->errors());
        }

        // Ver el mismo comentario en EmployeeController::update(): ya validamos arriba
        // con $merged (trae "id"); si update() revalida solo con $data (sin "id") truena
        // el placeholder {id} y el guardado queda como no-op silencioso.
        $this->devices->skipValidation(true)->update($id, $data);
        return $this->ok($this->devices->find($id), 'Dispositivo actualizado.');
    }

    public function delete($id = null)
    {
        $device = $this->devices->find($id);
        if (!$device) {
            return $this->fail('Dispositivo no encontrado.', 404);
        }

        $this->devices->delete($id);
        return $this->ok(null, 'Dispositivo eliminado.');
    }
}
