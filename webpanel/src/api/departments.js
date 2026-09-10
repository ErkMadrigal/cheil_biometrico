import client from './client'

/**
 * Helpers de la API de departamentos (catalogo). El index() del backend regresa un
 * arreglo plano (activos e inactivos), son pocos departamentos por empresa. delete()
 * hace baja logica (is_active=0), no borra el registro.
 */
export default {
  list() {
    return client.get('/departments').then((r) => r.data.data)
  },
  create(payload) {
    return client.post('/departments', payload).then((r) => r.data.data)
  },
  update(id, payload) {
    return client.put(`/departments/${id}`, payload).then((r) => r.data.data)
  },
  remove(id) {
    return client.delete(`/departments/${id}`).then((r) => r.data)
  },
}
