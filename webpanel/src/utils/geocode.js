import axios from 'axios'

// Nominatim (geocoder de OpenStreetMap, el mismo proveedor de los tiles del mapa):
// gratis, sin API key, basta con no golpearlo mas de ~1 req/seg (por eso el debounce
// en quien use esto). No es el client.js normal porque este NO pega a nuestro backend.
const nominatim = axios.create({ baseURL: 'https://nominatim.openstreetmap.org', timeout: 8000 })

/**
 * Busca una direccion/lugar y regresa hasta 5 coincidencias con lat/lng.
 * Acotado a Mexico (countrycodes=mx) porque es donde vive la empresa.
 */
export async function searchAddress(query) {
  const q = query?.trim()
  if (!q || q.length < 3) return []

  const { data } = await nominatim.get('/search', {
    params: { format: 'json', q, limit: 5, countrycodes: 'mx', addressdetails: 0 },
  })

  return data.map((r) => ({
    label: r.display_name,
    lat: parseFloat(r.lat),
    lng: parseFloat(r.lon),
  }))
}
