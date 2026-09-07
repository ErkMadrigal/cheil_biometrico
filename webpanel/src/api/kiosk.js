import client from './client'

// Endpoints publicos (sin JWT): el "registro biometrico" del web panel.
export default {
  lookup(query) {
    return client.post('/kiosk/lookup', { query }).then((r) => r.data.data)
  },
  checkin(payload) {
    return client.post('/kiosk/checkin', payload).then((r) => r.data.data)
  },
}
