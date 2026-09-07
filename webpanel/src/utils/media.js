// El backend guarda las rutas de foto como "uploads/employees/xxx.jpg" (relativas).
// Este helper arma la URL completa para un <img src>, usando VITE_API_URL si esta
// definido (produccion) o la raiz del proxy de Vite en desarrollo.
const apiUrl = import.meta.env.VITE_API_URL || ''
const backendOrigin = apiUrl ? apiUrl.replace(/\/api\/v1\/?$/, '') : ''

export function resolveMediaUrl(relativePath) {
  if (!relativePath) return null
  return `${backendOrigin}/${relativePath}`.replace(/([^:]\/)\/+/g, '$1')
}
