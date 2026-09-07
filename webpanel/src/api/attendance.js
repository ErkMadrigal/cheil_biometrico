import client from './client'

export default {
  list(params = {}) {
    return client.get('/attendance', { params }).then((r) => r.data.data)
  },
  dayDetail(employeeId, date) {
    return client.get(`/attendance/day-detail/${employeeId}/${date}`).then((r) => r.data.data)
  },
  todayMap() {
    return client.get('/attendance/today-map').then((r) => r.data.data)
  },
}
