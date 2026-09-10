<script setup>
import { ref, watch, onMounted } from 'vue'
import reportsApi from '../api/reports'
import departmentsApi from '../api/departments'
import { useToastStore } from '../stores/toast'
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

const employee = ref(null)
const departmentFilter = ref('')
const dateFrom = ref(daysAgoStr(6))
const dateTo = ref(todayStr())

const rows = ref([])
const loading = ref(true)
const exporting = ref(false)

const departments = ref([])
async function loadDepartments() {
  try {
    departments.value = (await departmentsApi.list()).filter((d) => Number(d.is_active) === 1)
  } catch (e) {
    // silencioso: el filtro de departamento simplemente queda vacio
  }
}

const drawerOpen = ref(false)
const drawerEmployeeId = ref(null)
const drawerEmployeeName = ref('')
const drawerDate = ref('')

function fullName(row) {
  return [row.first_name, row.paternal_last_name, row.maternal_last_name].filter(Boolean).join(' ')
}

function timeOnly(dt) {
  if (!dt) return '—'
  return new Date(dt.replace(' ', 'T')).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
}

function dateLabel(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' })
}

function currentFilters() {
  return {
    employee_id: employee.value?.id,
    department_id: departmentFilter.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
  }
}

async function load() {
  loading.value = true
  try {
    const data = await reportsApi.daily(currentFilters())
    rows.value = data.rows
  } catch (e) {
    toast.error('No se pudo cargar el reporte.')
  } finally {
    loading.value = false
  }
}

watch([employee, departmentFilter, dateFrom, dateTo], load)

async function exportCsv() {
  exporting.value = true
  try {
    await reportsApi.downloadDaily(currentFilters(), `reporte_asistencia_${dateFrom.value}_${dateTo.value}.csv`)
  } catch (e) {
    toast.error('No se pudo exportar el CSV.')
  } finally {
    exporting.value = false
  }
}

function openDay(row) {
  drawerEmployeeId.value = row.employee_id
  drawerEmployeeName.value = fullName(row)
  drawerDate.value = row.work_date
  drawerOpen.value = true
}

onMounted(() => {
  loadDepartments()
  load()
})
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Reportes</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          Entrada, salida y visitas del dia, por empleado o de todos.
        </p>
      </div>
      <button
        type="button"
        :disabled="exporting || !rows.length"
        class="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
        @click="exportCsv"
      >
        <Icon v-if="exporting" name="spinner" class="h-4 w-4 animate-spin" />
        <Icon v-else name="chart" class="h-4 w-4" />
        {{ exporting ? 'Exportando...' : 'Exportar CSV' }}
      </button>
    </div>

    <!-- Filtros -->
    <div class="grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-4">
      <EmployeePicker v-model="employee" placeholder="Todos los empleados" />
      <div class="relative self-start">
        <select
          v-model="departmentFilter"
          class="w-full appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 pr-9 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
        >
          <option value="">Todos los departamentos</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <Icon name="chevronDown" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      </div>
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
    </div>

    <!-- Tabla -->
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div class="scroll-thin overflow-x-auto">
        <table class="w-full min-w-[680px] text-left text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:border-slate-800 dark:text-slate-500">
              <th class="px-5 py-3">Empleado</th>
              <th class="px-5 py-3">Departamento</th>
              <th class="px-5 py-3">Fecha</th>
              <th class="px-5 py-3">Entrada</th>
              <th class="px-5 py-3">Salida</th>
              <th class="px-5 py-3">Registros</th>
              <th class="px-5 py-3 text-right">Detalle</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-if="loading" v-for="i in 6" :key="'sk-' + i">
              <td class="px-5 py-4" colspan="7">
                <div class="h-10 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
              </td>
            </tr>

            <tr v-else-if="!rows.length">
              <td colspan="7" class="px-5 py-14 text-center text-sm text-slate-400 dark:text-slate-500">
                <Icon name="chart" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-700" />
                No hay datos para este rango de fechas.
              </td>
            </tr>

            <tr
              v-else
              v-for="row in rows"
              :key="row.employee_id + '-' + row.work_date"
              class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
            >
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <Avatar :name="fullName(row)" size="sm" />
                  <div class="min-w-0">
                    <p class="truncate font-medium text-slate-800 dark:text-slate-100">{{ fullName(row) }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">#{{ row.employee_number }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ row.department_name || '—' }}</td>
              <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ dateLabel(row.work_date) }}</td>
              <td class="px-5 py-3">
                <div class="flex items-center gap-2">
                  <Badge color="emerald">{{ timeOnly(row.entrada) }}</Badge>
                  <Badge v-if="row.is_late" color="red">
                    <Icon name="alertTriangle" class="h-3 w-3" />
                    Retardo
                  </Badge>
                </div>
              </td>
              <td class="px-5 py-3">
                <Badge color="violet">{{ timeOnly(row.salida) }}</Badge>
              </td>
              <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ row.total_checkpoints }}</td>
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
