<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\EmployeeFaceEmbeddingModel;
use App\Models\EmployeeModel;

class EmployeeController extends BaseController
{
    protected EmployeeModel $employees;

    public function __construct()
    {
        $this->employees = new EmployeeModel();
    }

    /**
     * GET /api/v1/employees?search=&status=&page=&per_page=
     */
    public function index()
    {
        $search  = $this->request->getGet('search');
        $status  = $this->request->getGet('status');
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);

        $builder = $this->employees
            ->select('employees.*, departments.name as department_name')
            ->join('departments', 'departments.id = employees.department_id', 'left')
            ->orderBy('paternal_last_name', 'ASC');

        if ($search) {
            $builder->groupStart()
                ->like('first_name', $search)
                ->orLike('paternal_last_name', $search)
                ->orLike('maternal_last_name', $search)
                ->orLike('employee_number', $search)
                ->orLike('curp', $search)
                ->orLike('rfc', $search)
                ->groupEnd();
        }
        if ($status) {
            $builder->where('status', $status);
        }

        $total = $builder->countAllResults(false);
        $rows  = $builder->paginate($perPage, 'default', $page);

        return $this->ok([
            'items'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * GET /api/v1/employees/{id}
     */
    public function show($id = null)
    {
        $employee = $this->employees
            ->select('employees.*, departments.name as department_name')
            ->join('departments', 'departments.id = employees.department_id', 'left')
            ->find($id);
        if (!$employee) {
            return $this->fail('Empleado no encontrado.', 404);
        }

        $embeddingModel = new EmployeeFaceEmbeddingModel();
        $employee['has_face_enrolled'] = (bool) $embeddingModel->activeForEmployee((int) $id);

        return $this->ok($employee);
    }

    /**
     * POST /api/v1/employees
     * Acepta JSON (Content-Type: application/json) para altas sin foto,
     * o multipart/form-data (con "photo" como archivo) cuando si se sube foto.
     * Campos: employee_number, zkteco_pin, first_name, paternal_last_name,
     * maternal_last_name, curp, rfc, email, phone, position, department, hire_date, photo (file, opcional)
     */
    public function create()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        // Las reglas is_unique[...,id,{id}] necesitan un "id" para saber a que fila
        // excluir del chequeo de unicidad. En un alta todavia no hay id real, asi que
        // usamos 0 (ninguna fila real tiene ese id, el unique sigue aplicando a todas).
        $data['id'] = $data['id'] ?? 0;

        if (!$this->employees->validate($data)) {
            return $this->fail('Datos invalidos.', 422, $this->employees->errors());
        }

        $photo = $this->request->getFile('photo');
        if ($photo && $photo->isValid() && !$photo->hasMoved()) {
            $newName = $photo->getRandomName();
            $photo->move(WRITEPATH . 'uploads/employees', $newName);
            $data['photo_path'] = 'uploads/employees/' . $newName;
        }

        $id = $this->employees->insert($data, true);

        return $this->ok($this->employees->find($id), 'Empleado creado.', 201);
    }

    /**
     * PUT/POST /api/v1/employees/{id}
     */
    public function update($id = null)
    {
        $employee = $this->employees->find($id);
        if (!$employee) {
            return $this->fail('Empleado no encontrado.', 404);
        }

        // Acepta JSON (panel moderno) o form-urlencoded/multipart (formularios clasicos / subida de foto).
        // Se decodifica el body crudo a mano en vez de confiar en getJSON(): en algunas
        // instalaciones getJSON() regresa null con requests PUT aunque el body si sea JSON
        // valido, y eso hacia que las actualizaciones se guardaran vacias silenciosamente.
        $raw  = (string) $this->request->getBody();
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $data = $this->request->getMethod() === 'put' ? $this->request->getRawInput() : $this->request->getPost();
        }
        $data = $data ?? [];

        // Fusiona con el registro existente para que actualizaciones parciales
        // (ej. solo mandar la foto) no truenen la validacion de campos "required".
        $merged = array_merge($employee, $data, ['id' => $id]);
        if (!$this->employees->validate($merged)) {
            return $this->fail('Datos invalidos.', 422, $this->employees->errors());
        }

        $photo = $this->request->getFile('photo');
        if ($photo && $photo->isValid() && !$photo->hasMoved()) {
            $newName = $photo->getRandomName();
            $photo->move(WRITEPATH . 'uploads/employees', $newName);
            $data['photo_path'] = 'uploads/employees/' . $newName;
        }

        // Ya validamos arriba con $merged (que si trae "id" para el placeholder {id}).
        // Si dejamos que update() vuelva a validar solo, con $data (sin "id"), el mismo
        // is_unique[...,id,{id}] truena/rechaza y el guardado queda como no-op silencioso.
        $this->employees->skipValidation(true)->update($id, $data);

        return $this->ok($this->employees->find($id), 'Empleado actualizado.');
    }

    /**
     * DELETE /api/v1/employees/{id}  (baja logica)
     */
    public function delete($id = null)
    {
        $employee = $this->employees->find($id);
        if (!$employee) {
            return $this->fail('Empleado no encontrado.', 404);
        }

        $this->employees->delete($id);

        return $this->ok(null, 'Empleado dado de baja.');
    }

    /**
     * POST /api/v1/employees/{id}/face
     * body JSON: { "descriptor": [128 floats], "source": "web_panel" }
     * Guarda el descriptor facial de referencia para futuras comparaciones (checkin).
     */
    public function saveFace($id = null)
    {
        $employee = $this->employees->find($id);
        if (!$employee) {
            return $this->fail('Empleado no encontrado.', 404);
        }

        $body = $this->request->getJSON(true);
        $descriptor = $body['descriptor'] ?? null;

        if (!is_array($descriptor) || count($descriptor) !== 128) {
            return $this->fail('El descriptor facial debe ser un arreglo de 128 numeros (face-api.js).', 422);
        }

        $embeddingModel = new EmployeeFaceEmbeddingModel();
        // Desactiva embeddings previos y guarda el nuevo como activo (permite reenrolar sin perder historial)
        $embeddingModel->where('employee_id', $id)->set(['is_active' => 0])->update();

        $embeddingModel->insert([
            'employee_id' => $id,
            'descriptor'  => json_encode($descriptor),
            'source'      => $body['source'] ?? 'web_panel',
            'is_active'   => 1,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return $this->ok(null, 'Rostro enrolado correctamente.');
    }

    /**
     * POST /api/v1/employees/{id}/home-location/unlock  (JWT: admin/supervisor)
     * El empleado se mudo: se desbloquea su Home Office para que la vuelva a capturar
     * el mismo desde la app (con verificacion facial). No borra la ubicacion anterior,
     * solo la deja lista para que la proxima captura la sobreescriba.
     */
    public function unlockHomeLocation($id = null)
    {
        $employee = $this->employees->find($id);
        if (!$employee) {
            return $this->fail('Empleado no encontrado.', 404);
        }

        $this->employees->skipValidation(true)->update($id, ['home_location_locked' => 0]);

        return $this->ok($this->employees->find($id), 'Ubicacion Home Office desbloqueada.');
    }
}
