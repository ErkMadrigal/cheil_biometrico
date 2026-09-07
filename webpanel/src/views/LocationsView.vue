<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import attendanceApi from '../api/attendance'
import { useToastStore } from '../stores/toast'
import { sourceBadge } from '../utils/badges'
import Icon from '../components/Icon.vue'
import Avatar from '../components/ui/Avatar.vue'
import EmployeePicker from '../components/attendance/EmployeePicker.vue'

const toast = useToastStore()

function todayStr() {
  return new Date().toISOString().slice(0, 10)
}
function daysAgoStr(n) {
  const d = new Date()
  d.setDate(d.getDate() - n)
  return d.toISOString().slice(0, 10)
}

// ---- modo: "hoy" (todos, en vivo) vs "historial" (un empleado, rango de fechas) ----
const mode = ref('today') // 'today' | 'history'

// ---- modo hoy ----
const loading = ref(true)
const employees = ref([])
const selectedId = ref(null)
const search = ref('')
const lastUpdated = ref(null)
let refreshTimer = null

// ---- modo historial ----
const historyEmployee = ref(null)
const historyFrom = ref(daysAgoStr(29))
const historyTo = ref(todayStr())
const historyRecords = ref([])
const historyLoading = ref(false)

const mapEl = ref(null)
let map = null
let markersLayer = null
let trailLayer = null

function fullName(e) {
  return [e.first_name, e.paternal_last_name, e.maternal_last_name].filter(Boolean).join(' ')
}
function initials(e) {
  return [e.first_name?.[0], e.paternal_last_name?.[0]].filter(Boolean).join('').toUpperCase()
}
function timeLabel(dt) {
  return new Date(dt.replace(' ', 'T')).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
}
function dateTimeLabel(dt) {
  return new Date(dt.replace(' ', 'T')).toLocaleString('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

const STATUS = {
  entrada: { hex: '#10b981', label: 'Registro su entrada' },
  checkpoint: { hex: '#5b5bf6', label: 'En movimiento hoy' },
  salida: { hex: '#8b5cf6', label: 'Ya registro su salida' },
}
const SOURCE_HEX = { mobile_app: '#10b981', zkteco_device: '#0ea5e9', web_kiosk: '#f59e0b' }

function statusOf(emp) {
  return STATUS[emp.last_point.type] || STATUS.checkpoint
}

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return employees.value
  return employees.value.filter((e) => fullName(e).toLowerCase().includes(q) || e.employee_number.toLowerCase().includes(q))
})

const selectedEmployee = computed(() => employees.value.find((e) => e.employee_id === selectedId.value) || null)

function makeIcon(color, label, size = 34) {
  return L.divIcon({
    className: '',
    html: `<div style="width:${size}px;height:${size}px;border-radius:9999px;background:${color};display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:${size / 2.6}px;font-family:Inter,system-ui,sans-serif;box-shadow:0 2px 8px rgba(15,23,42,.35);border:2px solid #fff;">${label}</div>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    popupAnchor: [0, -size / 2 - 2],
  })
}

function popupHtml(title, subtitle, time) {
  return `<div style="font-family:Inter,system-ui,sans-serif;min-width:170px">
    <p style="font-weight:600;margin:0 0 2px;color:#0f172a">${title}</p>
    <p style="margin:0;font-size:12px;color:#64748b">${subtitle || 'Sin ubicacion registrada'}</p>
    <p style="margin:2px 0 0;font-size:12px;color:#94a3b8">${time}</p>
  </div>`
}

function clearMap() {
  markersLayer?.clearLayers()
  trailLayer?.clearLayers()
}

// ---- render: modo hoy ----
function renderAllMarkers() {
  if (!markersLayer) return
  markersLayer.clearLayers()
  const pts = []

  filtered.value.forEach((emp) => {
    const p = emp.last_point
    const marker = L.marker([p.latitude, p.longitude], { icon: makeIcon(statusOf(emp).hex, initials(emp)) })
    marker.bindPopup(popupHtml(fullName(emp), p.location_label, timeLabel(p.recorded_at)))
    marker.on('click', () => (selectedId.value = emp.employee_id))
    marker.addTo(markersLayer)
    pts.push([p.latitude, p.longitude])
  })

  if (pts.length) map.fitBounds(pts, { padding: [50, 50], maxZoom: 14 })
}

function renderTrail(emp) {
  if (!trailLayer || !emp) return
  trailLayer.clearLayers()

  const latlngs = emp.points.map((p) => [p.latitude, p.longitude])
  L.polyline(latlngs, { color: '#5b5bf6', weight: 3, opacity: 0.55, dashArray: '6 8' }).addTo(trailLayer)

  emp.points.forEach((p, i) => {
    const color = STATUS[p.type]?.hex || STATUS.checkpoint.hex
    const label = p.type === 'entrada' ? 'Entrada' : p.type === 'salida' ? 'Salida' : `Visita ${i}`
    const marker = L.marker([p.latitude, p.longitude], { icon: makeIcon(color, String(i + 1), 28) })
    marker.bindPopup(popupHtml(label, p.location_label, timeLabel(p.recorded_at)))
    marker.addTo(trailLayer)
  })

  map.fitBounds(latlngs, { padding: [60, 60], maxZoom: 15 })
}

function redrawToday() {
  if (!markersLayer || !trailLayer) return
  if (selectedEmployee.value) {
    markersLayer.clearLayers()
    renderTrail(selectedEmployee.value)
  } else {
    trailLayer.clearLayers()
    renderAllMarkers()
  }
}

function selectEmployee(emp) {
  selectedId.value = selectedId.value === emp.employee_id ? null : emp.employee_id
}

async function load(silent = false) {
  if (!silent) loading.value = true
  try {
    employees.value = await attendanceApi.todayMap()
    if (selectedId.value && !employees.value.some((e) => e.employee_id === selectedId.value)) {
      selectedId.value = null
    }
    lastUpdated.value = new Date()
    if (mode.value === 'today') redrawToday()
  } catch (e) {
    if (!silent) toast.error('No se pudo cargar el mapa de ubicaciones.')
  } finally {
    loading.value = false
  }
}

// ---- render: modo historial ----
function renderHistory() {
  if (!markersLayer) return
  clearMap()

  const pts = []
  historyRecords.value.forEach((row, i) => {
    const color = SOURCE_HEX[row.source_type] || '#64748b'
    const marker = L.marker([row.latitude, row.longitude], { icon: makeIcon(color, '', 16) })
    marker.bindPopup(popupHtml(sourceBadge(row).label, row.location_label, dateTimeLabel(row.recorded_at)))
    marker.on('click', () => flyToRecord(row))
    marker.addTo(markersLayer)
    pts.push([row.latitude, row.longitude])
  })

  if (pts.length) map.fitBounds(pts, { padding: [50, 50], maxZoom: 15 })
}

function flyToRecord(row) {
  map.flyTo([row.latitude, row.longitude], 15, { duration: 0.6 })
}

async function loadHistory() {
  if (!historyEmployee.value) {
    historyRecords.value = []
    clearMap()
    return
  }
  historyLoading.value = true
  try {
    const data = await attendanceApi.list({
      employee_id: historyEmployee.value.id,
      date_from: historyFrom.value,
      date_to: historyTo.value,
      per_page: 300,
    })
    historyRecords.value = data.data
      .filter((r) => r.latitude !== null && r.longitude !== null)
      .sort((a, b) => new Date(a.recorded_at) - new Date(b.recorded_at))
    renderHistory()
  } catch (e) {
    toast.error('No se pudo cargar el historial de este empleado.')
  } finally {
    historyLoading.value = false
  }
}

function setMode(m) {
  if (mode.value === m) return
  mode.value = m
  clearMap()
  if (m === 'today') {
    redrawToday()
  } else if (historyEmployee.value) {
    renderHistory()
  }
}

watch(selectedId, () => {
  if (mode.value === 'today') redrawToday()
})
watch(search, () => {
  if (mode.value === 'today' && !selectedId.value) redrawToday()
})
watch([historyEmployee, historyFrom, historyTo], loadHistory)

onMounted(() => {
  map = L.map(mapEl.value, { zoomControl: true, attributionControl: true }).setView([23.6345, -102.5528], 5)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(map)
  markersLayer = L.layerGroup().addTo(map)
  trailLayer = L.layerGroup().addTo(map)

  load()
  refreshTimer = setInterval(() => {
    if (mode.value === 'today') load(true)
  }, 60000)
})

onBeforeUnmount(() => {
  clearInterval(refreshTimer)
  if (map) map.remove()
})
</script>

<template>
  <div class="flex h-[calc(100vh-6.5rem)] flex-col space-y-4">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Mapa</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          Donde se ha registrado cada empleado — home office, oficina o visitas a clientes.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <div class="flex rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900">
          <button
            type="button"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
            :class="mode === 'today' ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
            @click="setMode('today')"
          >
            Hoy
          </button>
          <button
            type="button"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
            :class="mode === 'history' ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
            @click="setMode('history')"
          >
            Historial
          </button>
        </div>

        <template v-if="mode === 'today'">
          <p v-if="lastUpdated" class="hidden text-xs text-slate-400 dark:text-slate-500 sm:block">
            Actualizado {{ lastUpdated.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) }}
          </p>
          <button
            type="button"
            :disabled="loading"
            class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
            @click="load()"
          >
            <Icon name="spinner" class="h-4 w-4" :class="loading ? 'animate-spin' : ''" />
            Actualizar
          </button>
        </template>
      </div>
    </div>

    <div class="flex min-h-0 flex-1 flex-col gap-4 lg:flex-row">
      <!-- Lista: modo HOY -->
      <div
        v-if="mode === 'today'"
        class="flex w-full flex-col rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 lg:w-80 lg:shrink-0"
      >
        <div class="border-b border-slate-200 p-3 dark:border-slate-800">
          <div class="relative">
            <Icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="search"
              type="text"
              placeholder="Buscar empleado..."
              class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            />
          </div>
          <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
            {{ employees.length }} empleado{{ employees.length === 1 ? '' : 's' }} registrado{{ employees.length === 1 ? '' : 's' }} hoy
          </p>
        </div>

        <div class="scroll-thin flex-1 overflow-y-auto p-2">
          <div v-if="loading" class="space-y-2 p-1">
            <div v-for="i in 5" :key="i" class="h-14 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
          </div>

          <p v-else-if="!filtered.length" class="px-3 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
            Nadie ha checado hoy todavia.
          </p>

          <button
            v-for="emp in filtered"
            :key="emp.employee_id"
            type="button"
            class="mb-1 flex w-full items-center gap-3 rounded-xl p-2.5 text-left transition-colors"
            :class="selectedId === emp.employee_id ? 'bg-brand-50 dark:bg-brand-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'"
            @click="selectEmployee(emp)"
          >
            <Avatar :photo-path="emp.photo_path" :name="fullName(emp)" size="sm" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ fullName(emp) }}</p>
              <p class="truncate text-xs text-slate-400 dark:text-slate-500">
                {{ emp.last_point.location_label || 'Sin ubicacion' }} &middot; {{ timeLabel(emp.last_point.recorded_at) }}
              </p>
            </div>
            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: statusOf(emp).hex }" :title="statusOf(emp).label" />
          </button>
        </div>

        <div v-if="selectedEmployee" class="border-t border-slate-200 p-3 dark:border-slate-800">
          <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
            Recorrido de hoy · {{ fullName(selectedEmployee) }}
          </p>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ selectedEmployee.points.length }} registro{{ selectedEmployee.points.length === 1 ? '' : 's' }}
          </p>
          <button type="button" class="mt-2 text-xs font-medium text-brand-600 hover:underline dark:text-brand-300" @click="selectedId = null">
            Ver a todos de nuevo
          </button>
        </div>
      </div>

      <!-- Lista: modo HISTORIAL -->
      <div
        v-else
        class="flex w-full flex-col rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 lg:w-80 lg:shrink-0"
      >
        <div class="space-y-2 border-b border-slate-200 p-3 dark:border-slate-800">
          <EmployeePicker v-model="historyEmployee" placeholder="Elige un empleado..." />
          <div class="flex gap-2">
            <input
              v-model="historyFrom"
              type="date"
              class="w-1/2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
            />
            <input
              v-model="historyTo"
              type="date"
              class="w-1/2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
            />
          </div>
        </div>

        <div class="scroll-thin flex-1 overflow-y-auto p-2">
          <p v-if="!historyEmployee" class="px-3 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
            Elige un empleado para ver todo lo que ha registrado en el rango de fechas.
          </p>

          <div v-else-if="historyLoading" class="space-y-2 p-1">
            <div v-for="i in 6" :key="i" class="h-12 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
          </div>

          <p v-else-if="!historyRecords.length" class="px-3 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
            No hay registros con ubicacion en este rango de fechas.
          </p>

          <button
            v-for="row in [...historyRecords].reverse()"
            :key="row.id"
            type="button"
            class="mb-1 flex w-full items-start gap-2.5 rounded-xl p-2.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/60"
            @click="flyToRecord(row)"
          >
            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: SOURCE_HEX[row.source_type] || '#64748b' }" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                {{ row.location_label || 'Sin ubicacion' }}
              </p>
              <p class="text-xs text-slate-400 dark:text-slate-500">{{ dateTimeLabel(row.recorded_at) }} &middot; {{ sourceBadge(row).label }}</p>
            </div>
          </button>
        </div>

        <div v-if="historyEmployee" class="border-t border-slate-200 p-3 dark:border-slate-800">
          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ historyRecords.length }} ubicacion{{ historyRecords.length === 1 ? '' : 'es' }} registrada{{ historyRecords.length === 1 ? '' : 's' }}
            en el rango
          </p>
        </div>
      </div>

      <!-- Mapa -->
      <div class="isolate min-h-[360px] flex-1 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800">
        <div ref="mapEl" class="h-full w-full" />
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.leaflet-popup-content-wrapper) {
  border-radius: 0.75rem;
}
</style>
