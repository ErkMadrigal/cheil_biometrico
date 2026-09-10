<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\AuthContext;
use App\Models\IncidentModel;

/**
 * Modulo de Incidencias (mucho trafico, siniestro en carretera, clima, etc).
 * Registro INDEPENDIENTE -- no se liga a una checada/attendance_record especifica.
 * El empleado afectado y la evidencia (foto/documento) son opcionales; el tipo de
 * incidencia (catalogo, ver IncidentTypeController) es obligatorio.
 */
class IncidentController extends BaseController
{
    protected IncidentModel $incidents;

    public function __construct()
    {
        $this->incidents = new IncidentModel();
    }

    /**
     * GET /api/v1/incidents?employee_id=&department_id=&incident_type_id=&date_from=&date_to=&page=&per_page=
     */
    public function index()
    {
        $filters = [
            'employee_id'      => $this->request->getGet('employee_id'),
            'department_id'    => $this->request->getGet('department_id'),
            'incident_type_id' => $this->request->getGet('incident_type_id'),
            'date_from'        => $this->request->getGet('date_from'),
            'date_to'          => $this->request->getGet('date_to'),
        ];
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);

        $result = $this->incidents->search($filters, $page, $perPage);

        return $this->ok([
            'items'    => $result['data'],
            'total'    => $result['total'],
            'page'     => $result['page'],
            'per_page' => $result['per_page'],
        ]);
    }

    /**
     * GET /api/v1/incidents/{id}
     */
    public function show($id = null)
    {
        $incident = $this->incidents->findWithDetail((int) $id);
        if (!$incident) {
            return $this->fail('Incidencia no encontrada.', 404);
        }

        return $this->ok($incident);
    }

    /**
     * POST /api/v1/incidents
     * Acepta JSON (sin evidencia) o multipart/form-data (con "evidence" como archivo).
     * Campos: employee_id (opcional), incident_type_id (requerido), incident_date
     * (requerido, YYYY-MM-DD), description (opcional), evidence (file, opcional).
     */
    public function create()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $data['id'] = $data['id'] ?? 0;

        // employee_id vacio ("" desde un <select> sin elegir nada) debe quedar en
        // null, no en string vacio, para que permit_empty no lo rechace como "0".
        if (isset($data['employee_id']) && $data['employee_id'] === '') {
            $data['employee_id'] = null;
        }

        $data['created_by'] = AuthContext::id();

        if (!$this->incidents->validate($data)) {
            return $this->fail('Datos invalidos.', 422, $this->incidents->errors());
        }

        $evidence = $this->request->getFile('evidence');
        if ($evidence && $evidence->isValid() && !$evidence->hasMoved()) {
            $newName = $evidence->getRandomName();
            $evidence->move(WRITEPATH . 'uploads/incidents', $newName);
            $data['evidence_path'] = 'uploads/incidents/' . $newName;
        }

        $id = $this->incidents->insert($data, true);

        return $this->ok($this->incidents->findWithDetail($id), 'Incidencia registrada.', 201);
    }

    /**
     * PUT/POST /api/v1/incidents/{id}  (POST para permitir multipart al reemplazar evidencia)
     */
    public function update($id = null)
    {
        $incident = $this->incidents->find($id);
        if (!$incident) {
            return $this->fail('Incidencia no encontrada.', 404);
        }

        $raw  = (string) $this->request->getBody();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = $this->request->getMethod() === 'put' ? $this->request->getRawInput() : $this->request->getPost();
        }
        $data = $data ?? [];

        if (isset($data['employee_id']) && $data['employee_id'] === '') {
            $data['employee_id'] = null;
        }

        $merged = array_merge($incident, $data, ['id' => $id]);
        if (!$this->incidents->validate($merged)) {
            return $this->fail('Datos invalidos.', 422, $this->incidents->errors());
        }

        $evidence = $this->request->getFile('evidence');
        if ($evidence && $evidence->isValid() && !$evidence->hasMoved()) {
            $newName = $evidence->getRandomName();
            $evidence->move(WRITEPATH . 'uploads/incidents', $newName);
            $data['evidence_path'] = 'uploads/incidents/' . $newName;
        }

        // Igual que en los demas controladores: ya se valido arriba con $merged (trae
        // "id"); dejar que update() revalide solo con $data lo rechaza/no-opea en silencio.
        $this->incidents->skipValidation(true)->update($id, $data);

        return $this->ok($this->incidents->findWithDetail((int) $id), 'Incidencia actualizada.');
    }

    /**
     * DELETE /api/v1/incidents/{id}
     */
    public function delete($id = null)
    {
        $incident = $this->incidents->find($id);
        if (!$incident) {
            return $this->fail('Incidencia no encontrada.', 404);
        }

        $this->incidents->delete($id);

        return $this->ok(null, 'Incidencia eliminada.');
    }
}
