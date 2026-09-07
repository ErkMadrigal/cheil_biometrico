<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../api/client'
import { useAuthStore } from '../stores/auth'
import Icon from '../components/Icon.vue'
import StatCard from '../components/StatCard.vue'
import TrendChart from '../components/TrendChart.vue'
import { sourceBadge } from '../utils/badges'

const auth = useAuthStore()

const loadingSummary = ref(true)
const loadingActivity = ref(true)
const errorMsg = ref('')

const summary = ref({
  total_employees_active: 0,
  checkins_today: 0,
  employees_checked_today: 0,
  active_devices: 0,
  trend_last_7_days: [],
})

const recentActivity = ref([])

// Normaliza los ultimos 7 dias (el backend solo regresa dias con al menos un registro)
const trendPoints = computed(() => {
  const byDay = Object.fromEntries((summary.value.trend_last_7_days || []).map((p) => [p.day, Number(p.total)]))
  const days = []
  for (let i = 6; i >= 0; i--) {
    const d = new Date()
    d.setDate(d.getDate() - i)
    const key = d.toISOString().slice(0, 10)
    days.push({ day: key, total: byDay[key] || 0 })
  }
  return days
})

function fullName(row) {
  return [row.first_name, row.paternal_last_name, row.maternal_last_name].filter(Boolean).join(' ')
}

function timeAgo(dateStr) {
  const diffMs = Date.now() - new Date(dateStr.replace(' ', 'T')).getTime()
  const mins = Math.floor(diffMs / 60000)
  if (mins < 1) return 'justo ahora'
  if (mins < 60) return `hace ${mins} min`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `hace ${hours} h`
  return new Date(dateStr.replace(' ', 'T')).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' })
}

async function loadDashboard() {
  loadingSummary.value = true
  try {
    const { data } = await client.get('/dashboard/summary')
    summary.value = data.data
  } catch (e) {
    errorMsg.value = 'No se pudo cargar el resumen del dashboard. Revisa que el backend este corriendo.'
  } finally {
    loadingSummary.value = false
  }
}

async function loadActivity() {
  loadingActivity.value = true
  try {
    const { data } = await client.get('/attendance', { params: { page: 1, per_page: 8 } })
    recentActivity.value = data.data.data
  } catch (e) {
    // si el dashboard ya mostro el error, no lo duplicamos
  } finally {
    loadingActivity.value = false
  }
}

onMounted(() => {
  loadDashboard()
  loadActivity()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Hola, {{ auth.user?.name?.split(' ')[0] }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Esto es lo que esta pasando hoy en el equipo.</p>
      </div>
    </div>

    <p
      v-if="errorMsg"
      class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400"
    >
      {{ errorMsg }}
    </p>

    <!-- Stat cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard
        label="Empleados activos"
        :value="summary.total_employees_active"
        icon="users"
        color="brand"
        hint="Total dado de alta"
        :loading="loadingSummary"
      />
      <StatCard
        label="Checadas hoy"
        :value="summary.checkins_today"
        icon="clock"
        color="emerald"
        hint="App movil + checadores"
        :loading="loadingSummary"
      />
      <StatCard
        label="Empleados que registraron hoy"
        :value="summary.employees_checked_today"
        icon="faceSmile"
        color="amber"
        hint="Personas distintas"
        :loading="loadingSummary"
      />
      <StatCard
        label="Dispositivos activos"
        :value="summary.active_devices"
        icon="device"
        color="sky"
        hint="Checadores ZKTeco en linea"
        :loading="loadingSummary"
      />
    </div>

    <!-- Chart + actividad reciente -->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">Checadas de los ultimos 7 dias</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500">App movil + checadores ZKTeco</p>
          </div>
          <Icon name="chart" class="h-5 w-5 text-slate-300 dark:text-slate-600" />
        </div>
        <TrendChart :points="trendPoints" />
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h3 class="mb-4 font-semibold text-slate-900 dark:text-white">Actividad reciente</h3>

        <div v-if="loadingActivity" class="space-y-3">
          <div v-for="i in 5" :key="i" class="h-12 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
        </div>

        <p v-else-if="!recentActivity.length" class="py-8 text-center text-sm text-slate-400 dark:text-slate-500">
          Aun no hay checadas registradas.
        </p>

        <ul v-else class="scroll-thin max-h-72 space-y-1 overflow-y-auto">
          <li
            v-for="row in recentActivity"
            :key="row.id"
            class="flex items-start gap-3 rounded-lg px-2 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-800/60"
          >
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
              {{ row.first_name?.[0] }}{{ row.paternal_last_name?.[0] }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ fullName(row) }}</p>
              <p class="truncate text-xs text-slate-400 dark:text-slate-500">
                {{ row.location_label || 'Sin ubicacion' }}
              </p>
              <span
                class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-medium"
                :class="sourceBadge(row).color"
              >
                {{ sourceBadge(row).label }}
              </span>
            </div>
            <span class="shrink-0 whitespace-nowrap text-xs text-slate-400 dark:text-slate-500">
              {{ timeAgo(row.recorded_at) }}
            </span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
