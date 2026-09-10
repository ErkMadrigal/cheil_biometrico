import client from './client'

/**
 * Helpers de la API del catalogo de tipos de incidencia (Mucho trafico, Siniestro en
 * carretera, etc). Se alimenta desde el mismo modulo de Incidencias, sin salir del flujo.
 */
export default {
  list() {
    return client.get('/incident-types').then((r) => r.data.data)
  },
  create(payload) {
    return client.post('/incident-types', payload).then((r) => r.data.data)
  },
  update(id, payload) {
    return client.put(`/incident-types/${id}`, payload).then((r) => r.data.data)
  },
  remove(id) {
    return client.delete(`/incident-types/${id}`).then((r) => r.data)
  },
}
