<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import geoZonesApi from '../api/geoZones'
import { useToastStore } from '../stores/toast'
import Icon from '../components/Icon.vue'
import Badge from '../components/ui/Badge.vue'
import GeoZoneFormDrawer from '../components/zones/GeoZoneFormDrawer.vue'

const toast = useToastStore()

const zones = ref([])
const loading = ref(true)
const drawerOpen = ref(false)
const editingId = ref(null)
const confirmDeleteId = ref(null)
const deletingId = ref(null)

const mapEl = ref(null)
let map = null
let zonesLayer = null

function km(meters) {
  return meters >= 1000 ? `${(meters / 1000).toLocaleString('es-MX', { maximumFractionDigits: 2 })} km` : `${meters} m`
}

function openCreate() {
  editingId.value = null
  drawerOpen.value = true
}
function openEdit(zone) {
  editingId.value = zone.id
  drawerOpen.value = true
}
function onSaved(message) {
  drawerOpen.value = false
  toast.success(message)
  load()
}

async function confirmDelete(zone) {
  deletingId.value = zone.id
  try {
    await geoZonesApi.remove(zone.id)
    toast.success(`Zona "${zone.name}" eliminada.`)
    confirmDeleteId.value = null
    load()
  } catch (e) {
    toast.error('No se pudo eliminar la zona.')
  } finally {
    deletingId.value = null
  }
}

function renderZones() {
  if (!zonesLayer) return
  zonesLayer.clearLayers()

  const bounds = []
  zones.value.forEach((z) => {
    const latlng = [Number(z.latitude), Number(z.longitude)]
    const color = Number(z.is_active) ? '#5b5bf6' : '#94a3b8'

    L.circle(latlng, { radius: Number(z.radius_meters), color, fillColor: color, fillOpacity: 0.12, weight: 2 })
      .bindPopup(
        `<div style="font-family:Inter,system-ui,sans-serif;min-width:160px">
          <p style="font-weight:600;margin:0 0 2px;color:#0f172a">${z.name}</p>
          <p style="margin:0;font-size:12px;color:#64748b">${z.address_label || ''}</p>
          <p style="margin:2px 0 0;font-size:12px;color:#94a3b8">Radio: ${km(Number(z.radius_meters))}${Number(z.is_active) ? '' : ' &middot; inactiva'}</p>
        </div>`,
      )
      .addTo(zonesLayer)

    L.marker(latlng).addTo(zonesLayer)
    bounds.push(latlng)
  })

  if (bounds.length) map.fitBounds(bounds, { padding: [60, 60], maxZoom: 12 })
}

async function load() {
  loading.value = true
  try {
    zones.value = await geoZonesApi.list()
    renderZones()
  } catch (e) {
    toast.error('No se pudo cargar la lista de zonas.')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await nextTick()
  map = L.map(mapEl.value, { zoomControl: true }).setView([23.6345, -102.5528], 5)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
  }).addTo(map)
  zonesLayer = L.layerGroup().addTo(map)
  load()
})

onBeforeUnmount(() => map?.remove())

watch(drawerOpen, (isOpen) => {
  if (!isOpen) setTimeout(() => map?.invalidateSize(), 100)
})
</script>

<template>
  <div class="flex h-[calc(100vh-6.5rem)] flex-col space-y-4">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Zonas geograficas</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          Areas permitidas para registrar asistencia. Si hay al menos una zona activa, las checadas fuera de todas se rechazan.
        </p>
      </div>
      <button
        type="button"
        class="flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95"
        @click="openCreate"
      >
        <Icon name="plus" class="h-4 w-4" />
        Nueva zona
      </button>
    </div>

    <div class="flex min-h-0 flex-1 flex-col gap-4 lg:flex-row">
      <!-- Listado -->
      <div class="flex w-full flex-col rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 lg:w-96 lg:shrink-0">
        <div class="scroll-thin flex-1 overflow-y-auto p-3">
          <div v-if="loading" class="space-y-2">
            <div v-for="i in 3" :key="i" class="h-20 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />
          </div>

          <p v-else-if="!zones.length" class="px-3 py-14 text-center text-sm text-slate-400 dark:text-slate-500">
            <Icon name="mapPin" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-700" />
            No hay zonas registradas. Sin zonas, cualquier ubicacion es valida para checar.
          </p>

          <div
            v-else
            v-for="zone in zones"
            :key="zone.id"
            class="mb-2 rounded-xl border border-slate-100 p-3 dark:border-slate-800"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ zone.name }}</p>
                <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ zone.address_label || 'Sin direccion' }}</p>
              </div>
              <Badge :color="Number(zone.is_active) ? 'emerald' : 'slate'">
                {{ Number(zone.is_active) ? 'Activa' : 'Inactiva' }}
              </Badge>
            </div>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Radio: {{ km(Number(zone.radius_meters)) }}</p>

            <div v-if="confirmDeleteId === zone.id" class="mt-2 flex items-center gap-2">
              <span class="text-xs text-slate-500 dark:text-slate-400">¿Eliminar?</span>
              <button
                type="button"
                :disabled="deletingId === zone.id"
                class="rounded-lg bg-red-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                @click="confirmDelete(zone)"
              >
                Si
              </button>
              <button
                type="button"
                class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                @click="confirmDeleteId = null"
              >
                No
              </button>
            </div>
            <div v-else class="mt-2 flex items-center gap-1">
              <button
                type="button"
                class="rounded-lg px-2.5 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-500/10"
                @click="openEdit(zone)"
              >
                Editar
              </button>
              <button
                type="button"
                class="rounded-lg px-2.5 py-1 text-xs font-medium text-red-500 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                @click="confirmDeleteId = zone.id"
              >
                Eliminar
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Mapa -->
      <div class="isolate min-h-[360px] flex-1 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800">
        <div ref="mapEl" class="h-full w-full" />
      </div>
    </div>

    <GeoZoneFormDrawer :open="drawerOpen" :zone-id="editingId" @close="drawerOpen = false" @saved="onSaved" />
  </div>
</template>
