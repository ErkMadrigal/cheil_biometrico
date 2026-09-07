import client from './client'

/**
 * Helpers de la API de empleados. Centralizados aqui para que las vistas
 * no hablen con axios directamente y sea facil ajustar el contrato con el backend.
 */
export default {
  list(params = {}) {
    return client.get('/employees', { params }).then((r) => r.data.data)
  },
  get(id) {
    return client.get(`/employees/${id}`).then((r) => r.data.data)
  },
  create(payload) {
    return client.post('/employees', payload).then((r) => r.data.data)
  },
  update(id, payload) {
    return client.put(`/employees/${id}`, payload).then((r) => r.data.data)
  },
  remove(id) {
    return client.delete(`/employees/${id}`).then((r) => r.data)
  },
  uploadPhoto(id, file) {
    const form = new FormData()
    form.append('photo', file)
    // El backend acepta POST para multipart en update (ver EmployeeController::update)
    return client.post(`/employees/${id}`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then((r) => r.data.data)
  },
  saveFace(id, descriptor, source = 'web_panel') {
    return client.post(`/employees/${id}/face`, { descriptor: Array.from(descriptor), source }).then((r) => r.data)
  },
  // Carga masiva por XLSX: sube el archivo y regresa el detalle fila por fila
  // (creados/actualizados/con error) para mostrarlo en el modal de importacion.
  importXlsx(file) {
    const form = new FormData()
    form.append('file', file)
    return client.post('/employees/import', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then((r) => r.data.data)
  },
  // Descarga la plantilla .xlsx (blob) para que RH la llene y la vuelva a subir.
  downloadTemplate() {
    return client.get('/employees/import/template', { responseType: 'blob' }).then((r) => r.data)
  },
  // Genera una liga temporal de 72h para que el empleado se auto-enrole el rostro
  // sin necesitar acceso al panel (util cuando pedirle a TI es "castroso").
  generateEnrollToken(id) {
    return client.post(`/employees/${id}/enroll-token`).then((r) => r.data.data)
  },
  // Desbloquea la ubicacion Home Office del empleado (se mudo) para que la vuelva a
  // capturar el mismo desde la app.
  unlockHomeLocation(id) {
    return client.post(`/employees/${id}/home-location/unlock`).then((r) => r.data.data)
  },
}
