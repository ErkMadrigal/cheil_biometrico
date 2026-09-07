import axios from 'axios'

// Cliente aparte (NO el axios de /api/client.js): esta pantalla la abre el empleado
// SIN sesion, desde una liga publica. El client normal manda a /login en cualquier
// 401/expiracion de sesion, lo cual no aplica aqui (aqui ni siquiera hay token).
const baseURL = import.meta.env.VITE_API_URL || '/api/v1'
const publicClient = axios.create({ baseURL, timeout: 15000 })

/**
 * Helpers publicos para /enrolar/:token (auto-enrolamiento facial por liga temporal).
 */
export default {
  lookup(token) {
    return publicClient.get(`/enroll/${token}`).then((r) => r.data.data)
  },
  saveFace(token, descriptor) {
    return publicClient.post(`/enroll/${token}/save`, { descriptor: Array.from(descriptor) }).then((r) => r.data)
  },
  verifyFace(token, descriptor) {
    return publicClient.post(`/enroll/${token}/verify`, { descriptor: Array.from(descriptor) }).then((r) => r.data)
  },
}
