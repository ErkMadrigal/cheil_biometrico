import client from './client'

/**
 * Helpers de la API de Incidencias. Registro independiente (no ligado a una checada):
 * empleado y evidencia son opcionales, tipo y fecha son obligatorios. Si viene un
 * archivo de evidencia se manda multipart, si no, JSON normal.
 */
function buildFormData(payload, file) {
  const form = new FormData()
  Object.entries(payload).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return
    form.append(key, value)
  })
  if (file) form.append('evidence', file)
  return form
}

export default {
  list(params = {}) {
    return client.get('/incidents', { params }).then((r) => r.data.data)
  },
  get(id) {
    return client.get(`/incidents/${id}`).then((r) => r.data.data)
  },
  create(payload, file = null) {
    if (file) {
      return client.post('/incidents', buildFormData(payload, file), {
        headers: { 'Content-Type': 'multipart/form-data' },
      }).then((r) => r.data.data)
    }
    return client.post('/incidents', payload).then((r) => r.data.data)
  },
  update(id, payload, file = null) {
    if (file) {
      // El backend acepta POST para multipart en update (igual que empleados/tipos).
      return client.post(`/incidents/${id}`, buildFormData(payload, file), {
        headers: { 'Content-Type': 'multipart/form-data' },
      }).then((r) => r.data.data)
    }
    return client.put(`/incidents/${id}`, payload).then((r) => r.data.data)
  },
  remove(id) {
    return client.delete(`/incidents/${id}`).then((r) => r.data)
  },
}
