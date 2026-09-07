import client from './client'

/**
 * Helpers de la API de zonas geograficas permitidas (ver GeoZoneController). El
 * index() regresa un arreglo plano (sin paginacion), son pocas zonas por empresa.
 */
export default {
  list() {
    return client.get('/geo-zones').then((r) => r.data.data)
  },
  get(id) {
    return client.get(`/geo-zones/${id}`).then((r) => r.data.data)
  },
  create(payload) {
    return client.post('/geo-zones', payload).then((r) => r.data.data)
  },
  update(id, payload) {
    return client.put(`/geo-zones/${id}`, payload).then((r) => r.data.data)
  },
  remove(id) {
    return client.delete(`/geo-zones/${id}`).then((r) => r.data)
  },
}
