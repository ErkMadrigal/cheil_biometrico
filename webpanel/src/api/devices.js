import client from './client'

/**
 * Helpers de la API de dispositivos (checadores ZKTeco). El index() del backend
 * regresa un arreglo plano (sin paginacion), son pocos dispositivos por empresa.
 */
export default {
  list() {
    return client.get('/devices').then((r) => r.data.data)
  },
  get(id) {
    return client.get(`/devices/${id}`).then((r) => r.data.data)
  },
  create(payload) {
    return client.post('/devices', payload).then((r) => r.data.data)
  },
  update(id, payload) {
    return client.put(`/devices/${id}`, payload).then((r) => r.data.data)
  },
  remove(id) {
    return client.delete(`/devices/${id}`).then((r) => r.data)
  },
}
