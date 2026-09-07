<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import attendanceApi from '../api/attendance'
import { useToastStore } from '../stores/toast'
import { sourceBadge, SOURCE_OPTIONS } from '../utils/badges'
import { resolveMediaUrl } from '../utils/media'
import Icon from '../components/Icon.vue'
import Avatar from '../components/ui/Avatar.vue'
import Badge from '../components/ui/Badge.vue'
import EmployeePicker from '../components/attendance/EmployeePicker.vue'
import DayDetailDrawer from '../components/attendance/DayDetailDrawer.vue'

const toast = useToastStore()

function todayStr() {
  return new Date().toISOString().slice(0, 10)
}
function daysAgoStr(n) {
  const d = new Date()
  d.setDate(d.getDate() - n)
  return d.toISOString().slice(0, 10)
}

const items = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const loading = ref(true)

const employee = ref(null)
const dateFrom = ref(daysAgoStr(6))
const dateTo = ref(todayStr())
const sourceType = ref('')

const drawerOpen = ref(false)
const drawerEmployeeId = ref(null)
const drawerEmployeeName = ref('')
const drawerDate = ref('')

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))

function fullName(row) {
  return [row.first_name, row.paternal_last_name, row.maternal_last_name].filter(Boolean).join(' ')
}

function dateTimeLabel(dt) {
  return new Date(dt.replace(' ', 'T')).toLocaleString('es-MX', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function mapsUrl(row) {
  if (!row.latitude || !row.longitude) return null
  return `https://www.google.com/maps?q=${row.latitude},${row.longitude}`
}

async function load() {
  loading.value = true
  try {
    const data = await attendanceApi.list({
      employee_id: employee.value?.id,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      source_type: sourceType.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    items.value = data.data
    total.value = data.total
  } catch (e) {
    toast.error('No se pudo cargar la asistencia.')
  } finally {
    loading.value = false
  }
}

watch([employee, dateFrom, dateTo, sourceType], () => {
  page.value = 1
  load()
})

function goToPage(p) {
  if (p < 1 || p > totalPages.value) return
  page.value = p
  load()
}

function openDay(row) {
  drawerEmployeeId.value = row.employee_id
  drawerEmployeeName.value = fullName(row)
  drawerDate.value = row.recorded_at.slice(0, 10)
  drawerOpen.value = true
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div>
      <h2 class="text-xl font-bold text-slate-900 dark:text-white">Asistencia</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400">
        Todas las checadas: app movil, checadores ZKTeco y registro biometrico del panel.
      </p>
    </div>

    <!-- Filtros -->
    <div class="grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-2 lg:grid-cols-4">
      <EmployeePicker v-model="employee" placeholder="Buscar empleado..." />
      <input
        v-model="dateFrom"
        type="date"
        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
      />
      <input
        v-model="dateTo"
        type="date"
        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
      />
      <select
        v-model="sourceType"
        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
      >
        <option v-for="opt in SOURCE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <!-- Tabla -->
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div class="scroll-thin overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:border-slate-800 dark:text-slate-500">
              <th class="px-5 py-3">Empleado</th>
              <th class="px-5 py-3">Origen</th>
              <th class="px-5 py-3">Ubicacion</th>
              <th class="px-5 py-3">Fecha / hora</th>
              <th class="px-5 py-3 text-right">Detalle</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-if="loading" v-for="i in 6" :key="'sk-' + i">
              <td class="px-5 py-4" colspan="5">
                <div class="h-10 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
              </td>
            </tr>

            <tr v-else-if="!items.length">
              <td colspan="5" class="px-5 py-14 text-center text-sm text-slate-400 dark:text-slate-500">
                <Icon name="clock" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-700" />
                No hay checadas con estos filtros.
              </td>
            </tr>

            <tr v-else v-for="row in items" :key="row.id" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <Avatar :photo-path="row.photo_path" :name="fullName(row)" size="sm" />
                  <div class="min-w-0">
                    <p class="truncate font-medium text-slate-800 dark:text-slate-100">{{ fullName(row) }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">#{{ row.employee_number }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3">
                <Badge :color="sourceBadge(row).colorKey">
                  {{ sourceBadge(row).label }}
                </Badge>
              </td>
              <td class="px-5 py-3">
                <p class="text-slate-700 dark:text-slate-300">{{ row.location_label || '—' }}</p>
                <a
                  v-if="mapsUrl(row)"
                  :href="mapsUrl(row)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline dark:text-brand-300"
                >
                  Ver en mapa
                  <Icon name="arrowUpRight" class="h-3 w-3" />
                </a>
              </td>
              <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ dateTimeLabel(row.recorded_at) }}</td>
              <td class="px-5 py-3 text-right">
                <button
                  type="button"
                  class="rounded-lg p-2 text-slate-400 hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-300"
                  title="Ver el dia completo"
                  @click="openDay(row)"
                >
                  <Icon name="arrowUpRight" class="h-4 w-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3 dark:border-slate-800">
        <p class="text-xs text-slate-400 dark:text-slate-500">Pagina {{ page }} de {{ totalPages }} &middot; {{ total }} registros</p>
        <div class="flex items-center gap-1.5">
          <button
            type="button"
            :disabled="page <= 1"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-400 dark:hover:bg-slate-800"
            @click="goToPage(page - 1)"
          >
            <Icon name="chevronLeft" class="h-4 w-4" />
          </button>
          <button
            type="button"
            :disabled="page >= totalPages"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-400 dark:hover:bg-slate-800"
            @click="goToPage(page + 1)"
          >
            <Icon name="chevronRight" class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>

    <DayDetailDrawer
      :open="drawerOpen"
      :employee-id="drawerEmployeeId"
      :employee-name="drawerEmployeeName"
      :date="drawerDate"
      @close="drawerOpen = false"
    />
  </div>
</template>
