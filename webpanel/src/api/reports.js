import client from './client'

export default {
  daily(params = {}) {
    return client.get('/reports/daily', { params }).then((r) => r.data.data)
  },
  dayDetail(employeeId, date) {
    return client.get(`/reports/day-detail/${employeeId}/${date}`).then((r) => r.data.data)
  },
  /**
   * El export es CSV protegido por JWT, asi que no se puede abrir con un <a href> normal
   * (no manda el Authorization header). Lo pedimos por axios como blob y forzamos la
   * descarga nosotros mismos.
   */
  async downloadDaily(params = {}, filename = 'reporte_asistencia.csv') {
    const response = await client.get('/reports/daily/export', { params, responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  },
  /**
   * Reporte de exportacion para nomina externa (Employee ID, Employee Name, Work date,
   * Year&Date, Time In, Time Out). Igual que downloadDaily: blob por JWT, no <a href>.
   */
  async downloadPayrollExport(params = {}, filename = 'reporte_nomina.xlsx') {
    const response = await client.get('/reports/payroll-export', { params, responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  },
}
