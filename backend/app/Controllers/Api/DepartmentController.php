<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;

/**
 * Catalogo de departamentos (Retail Center, Creative, Client Service, etc). El cliente
 * lo va alimentando el mismo desde el panel conforme lo necesite; es obligatorio elegir
 * uno al dar de alta/editar un empleado (ver EmployeeModel).
 */
class DepartmentController extends BaseController
{
    protected DepartmentModel $departments;

    public function __construct()
    {
        $this->departments = new DepartmentModel();
    }

    public function index()
    {
        return $this->ok($this->departments->orderBy('name', 'ASC')->findAll());
    }

    public function show($id = null)
    {
        $department = $this->departments->find($id);
        if (!$department) {
            return $this->fail('Departamento no encontrado.', 404);
        }
        return $this->ok($department);
    }

    public function create()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $data['id'] = $data['id'] ?? 0;

        if (!$this->departments->validate($data)) {
            return $this->fail('Datos invalidos.', 422, $this->departments->errors());
        }

        $id = $this->departments->insert($data, true);
        return $this->ok($this->departments->find($id), 'Departamento creado.', 201);
    }

    public function update($id = null)
    {
        $department = $this->departments->find($id);
        if (!$department) {
            return $this->fail('Departamento no encontrado.', 404);
        }

        $raw  = (string) $this->request->getBody();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = $this->request->getMethod() === 'put' ? $this->request->getRawInput() : $this->request->getPost();
        }
        $data = $data ?? [];

        $merged = array_merge($department, $data, ['id' => $id]);
        if (!$this->departments->validate($merged)) {
            return $this->fail('Datos invalidos.', 422, $this->departments->errors());
        }

        $this->departments->skipValidation(true)->update($id, $data);
        return $this->ok($this->departments->find($id), 'Departamento actualizado.');
    }

    public function delete($id = null)
    {
        $department = $this->departments->find($id);
        if (!$department) {
            return $this->fail('Departamento no encontrado.', 404);
        }

        // Baja logica (is_active=0) en vez de borrar: si hay empleados ya asignados a
        // este departamento, no queremos romper esas referencias historicas.
        // skipValidation(true): sin esto, update() valida $data solo (sin "name"), y la
        // regla "required" de name truena aunque no lo estemos tocando.
        $this->departments->skipValidation(true)->update($id, ['is_active' => 0]);
        return $this->ok(null, 'Departamento desactivado.');
    }
}
