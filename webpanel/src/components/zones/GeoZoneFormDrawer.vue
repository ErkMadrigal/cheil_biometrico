<script setup>
import { ref, reactive, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import geoZonesApi from '../../api/geoZones'
import { searchAddress } from '../../utils/geocode'
import Icon from '../Icon.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  zoneId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close', 'saved'])

const isEdit = computed(() => !!props.zoneId)

const blankForm = () => ({
  name: '',
  address_label: '',
  latitude: null,
  longitude: null,
  radius_meters: 500,
  is_active: true,
})

const form = reactive(blankForm())
const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const generalError = ref('')

const mapEl = ref(null)
let map = null
let marker = null
let circle = null

// Buscador de direcciones (Nominatim/OpenStreetMap): escribes en "Direccion" y salen
// coincidencias en dropdown, como el buscador de empleados. Al elegir una, se marca
// sola en el mapa -no hace falta saber la latitud/longitud a mano-.
const addressResults = ref([])
const addressSearching = ref(false)
const addressOpen = ref(false)
let addressDebounce = null
let suppressAddressSearch = false

function onAddressInput() {
  if (suppressAddressSearch) {
    suppressAddressSearch = false
    return
  }
  clearTimeout(addressDebounce)
  const q = form.address_label
  if (!q || q.trim().length < 3) {
    addressResults.value = []
    addressOpen.value = false
    return
  }
  addressDebounce = setTimeout(async () => {
    addressSearching.value = true
    try {
      addressResults.value = await searchAddress(q)
      addressOpen.value = true
    } catch (e) {
      addressResults.value = []
    } finally {
      addressSearching.value = false
    }
  }, 500)
}

function selectAddress(result) {
  suppressAddressSearch = true
  form.address_label = result.label
  addressResults.value = []
  addressOpen.value = false
  setPoint(result.lat, result.lng)
  map?.setView([result.lat, result.lng], 15)
}

// Radio en km para que sea mas facil de escribir (50 en vez de 50000); se guarda en metros.
const radiusKm = computed({
  get: () => (form.radius_meters ? +(form.radius_meters / 1000).toFixed(3) : 0),
  set: (val) => {
    form.radius_meters = Math.round(Number(val) * 1000)
  },
})

function resetForm() {
  Object.assign(form, blankForm())
  errors.value = {}
  generalError.value = ''
  addressResults.value = []
  addressOpen.value = false
}

function setPoint(lat, lng) {
  form.latitude = +lat.toFixed(7)
  form.longitude = +lng.toFixed(7)
  drawPoint()
}

function drawPoint() {
  if (!map || form.latitude === null || form.longitude === null) return
  const latlng = [form.latitude, form.longitude]

  if (!marker) {
    marker = L.marker(latlng, { draggable: true }).addTo(map)
    marker.on('drag', (e) => {
      const p = e.target.getLatLng()
      form.latitude = +p.lat.toFixed(7)
      form.longitude = +p.lng.toFixed(7)
      circle?.setLatLng(p)
    })
  } else {
    marker.setLatLng(latlng)
  }

  if (!circle) {
    circle = L.circle(latlng, { radius: form.radius_meters || 0, color: '#5b5bf6', fillColor: '#5b5bf6', fillOpacity: 0.15 }).addTo(map)
  } else {
    circle.setLatLng(latlng)
  }
}

watch(() => form.radius_meters, (r) => {
  circle?.setRadius(Number(r) || 0)
})

function initMap() {
  if (map || !mapEl.value) return
  map = L.map(mapEl.value, { zoomControl: true }).setView(
    form.latitude !== null ? [form.latitude, form.longitude] : [19.4326, -99.1332],
    form.latitude !== null ? 13 : 11,
  )
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
  }).addTo(map)

  map.on('click', (e) => setPoint(e.latlng.lat, e.latlng.lng))

  if (form.latitude !== null) drawPoint()
  setTimeout(() => map?.invalidateSize(), 150)
}

function destroyMap() {
  map?.remove()
  map = null
  marker = null
  circle = null
}

async function loadZone(id) {
  loading.value = true
  try {
    const data = await geoZonesApi.get(id)
    Object.assign(form, {
      name: data.name || '',
      address_label: data.address_label || '',
      latitude: data.latitude !== null ? Number(data.latitude) : null,
      longitude: data.longitude !== null ? Number(data.longitude) : null,
      radius_meters: Number(data.radius_meters) || 500,
      is_active: !!Number(data.is_active),
    })
    if (map) {
      map.setView([form.latitude, form.longitude], 13)
      drawPoint()
    }
  } catch (e) {
    generalError.value = 'No se pudo cargar la informacion de la zona.'
  } finally {
    loading.value = false
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) {
      destroyMap()
      addressResults.value = []
      addressOpen.value = false
      return
    }
    resetForm()
    await nextTick()
    initMap()
    if (props.zoneId) {
      await loadZone(props.zoneId)
    }
  },
)

async function onSubmit() {
  if (form.latitude === null || form.longitude === null) {
    generalError.value = 'Da click en el mapa para marcar la ubicacion de la zona.'
    return
  }

  saving.value = true
  errors.value = {}
  generalError.value = ''

  const payload = { ...form, is_active: form.is_active ? 1 : 0 }

  try {
    if (isEdit.value) {
      await geoZonesApi.update(props.zoneId, payload)
    } else {
      await geoZonesApi.create(payload)
    }
    emit('saved', isEdit.value ? 'Zona actualizada.' : 'Zona creada correctamente.')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {}
      generalError.value = e.response.data.errors ? 'Revisa los campos marcados.' : e.response.data.message
    } else {
      generalError.value = e.response?.data?.message || 'No se pudo guardar la zona.'
    }
  } finally {
    saving.value = false
  }
}

onBeforeUnmount(destroyMap)
</script>

<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="!saving && emit('close')" />

      <aside class="absolute right-0 top-0 flex h-full w-full max-w-lg flex-col bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
          <div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
              {{ isEdit ? 'Editar zona' : 'Nueva zona geografica' }}
            </h3>
            <p class="text-xs text-slate-400 dark:text-slate-500">Busca la direccion o da click en el mapa</p>
          </div>
          <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="emit('close')">
            <Icon name="xMark" class="h-5 w-5" />
          </button>
        </div>

        <div v-if="loading" class="flex flex-1 items-center justify-center">
          <Icon name="spinner" class="h-6 w-6 animate-spin text-brand-500" />
        </div>

        <form v-else class="scroll-thin flex-1 space-y-4 overflow-y-auto px-6 py-5" @submit.prevent="onSubmit">
          <p
            v-if="generalError"
            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400"
          >
            {{ generalError }}
          </p>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Nombre *</label>
            <input v-model="form.name" type="text" required class="input" placeholder="Oficina Polanco" />
            <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
          </div>

          <div class="relative" @focusout="(e) => !e.currentTarget.contains(e.relatedTarget) && (addressOpen = false)">
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Direccion (busca y elige de la lista)</label>
            <div class="relative">
              <input
                v-model="form.address_label"
                type="text"
                class="input pr-8"
                placeholder="Av. Ejercito Nacional #350, Polanco, CDMX"
                @input="onAddressInput"
                @focus="addressResults.length && (addressOpen = true)"
              />
              <Icon
                :name="addressSearching ? 'spinner' : 'search'"
                class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                :class="addressSearching && 'animate-spin'"
              />
            </div>

            <div
              v-if="addressOpen && addressResults.length"
              class="absolute z-20 mt-1.5 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
            >
              <button
                v-for="(r, i) in addressResults"
                :key="i"
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/60"
                @click="selectAddress(r)"
              >
                {{ r.label }}
              </button>
            </div>
          </div>

          <div ref="mapEl" class="isolate h-56 w-full rounded-xl border border-slate-200 dark:border-slate-700" />
          <p class="text-xs text-slate-400 dark:text-slate-500">
            {{ form.latitude !== null ? `${form.latitude}, ${form.longitude}` : 'Busca una direccion o da click en el mapa para marcar el centro' }}
          </p>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Radio permitido (km)</label>
            <input v-model.number="radiusKm" type="number" min="0.01" step="0.01" required class="input" placeholder="50" />
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
              = {{ form.radius_meters?.toLocaleString('es-MX') }} metros. Ej: 0.5 para 500m, 50 para 50km.
            </p>
            <p v-if="errors.radius_meters" class="mt-1 text-xs text-red-500">{{ errors.radius_meters }}</p>
          </div>

          <label class="flex items-center gap-2.5">
            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
            <span class="text-sm text-slate-700 dark:text-slate-300">Zona activa</span>
          </label>
          <p class="text-xs text-slate-400 dark:text-slate-500">
            Si NINGUNA zona esta activa, no se bloquea ninguna checada (comportamiento actual). En cuanto haya al
            menos una zona activa, las checadas fuera de todas las zonas se rechazan.
          </p>
        </form>

        <div v-if="!loading" class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4 dark:border-slate-800">
          <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="emit('close')">
            Cancelar
          </button>
          <button
            type="button"
            :disabled="saving"
            class="flex items-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95 disabled:opacity-60"
            @click="onSubmit"
          >
            <Icon v-if="saving" name="spinner" class="h-4 w-4 animate-spin" />
            {{ saving ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </aside>
    </div>
  </teleport>
</template>

<style scoped>
@reference "../../style.css";

.input {
  @apply w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition-shadow focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-slate-800 dark:text-white;
}
</style>
