import axios from 'axios'

// En dev, Vite hace proxy de /api -> backend (ver vite.config.js).
// En produccion, cambia VITE_API_URL en .env a la URL real del backend
// (ej. https://api.tudominio.mx) y compila con `npm run build`.
const baseURL = import.meta.env.VITE_API_URL || '/api/v1'

const client = axios.create({
  baseURL,
  timeout: 15000,
})

client.interceptors.request.use((config) => {
  const token = localStorage.getItem('cheil-token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token invalido/expirado: limpia sesion y manda a login
      localStorage.removeItem('cheil-token')
      localStorage.removeItem('cheil-user')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  },
)

export default client
