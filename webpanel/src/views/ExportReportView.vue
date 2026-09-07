<script setup>
import { ref, watch, onMounted } from 'vue'
import reportsApi from '../api/reports'
import { useToastStore } from '../stores/toast'
import Icon from '../components/Icon.vue'
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

const employee = ref(null)
const dateFrom = ref(daysAgoStr(29))
const dateTo = ref(todayStr())

const rows = ref([])
const loading = ref(true)
const exporting = ref(false)

// Formatea EXACTAMENTE igual que el backend (ReportController::payrollExport), para
// que la vista previa en pantalla sea identica a lo que va a traer el .xlsx.
function fullName(row) {
  return [row.first_name, row.paternal_last_name, row.maternal_last_name].filter(Boolean).join(' ')
}
function compactDate(dateStr) {
  return dateStr ? dateStr.replaceAll('-', '') : ''
}
function calendarDateOfDatetime(dt) {
  return dt ? compactDate(dt.slice(0, 10)) : ''
}
function compactTime(dt) {
  return dt ? dt.slice(11, 19).replaceAll(':', '') : ''
}

function currentFilters() {
  return {
    employee_id: employee.value?.id,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  }
}

async function load() {
  if (!dateFrom.value || !dateTo.value) return
  loading.value = true
  try {
    const data = await reportsApi.daily(currentFilters())
    rows.value = data.rows
  } catch (e) {
    toast.error('No se pudo cargar la vista previa del reporte.')
  } finally {
    loading.value = false
  }
}

watch([employee, dateFrom, dateTo], load)

async function exportXlsx() {
  exporting.value = true
  try {
    await reportsApi.downloadPayrollExport(currentFilters(), `reporte_nomina_${dateFrom.value}_${dateTo.value}.xlsx`)
  } catch (e) {
    toast.error('No se pudo exportar el archivo.')
  } finally {
    exporting.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Reporte de exportacion</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          Formato listo para el sistema de nomina: Employee ID, Employee Name, Work date, Year&amp;Date, Time In, Time Out.
        </p>
      </div>
      <button
        type="button"
        :disabled="exporting || !rows.length"
        class="flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50"
        @click="exportXlsx"
      >
        <Icon :name="exporting ? 'spinner' : 'download'" class="h-4 w-4" :class="exporting && 'animate-spin'" />
        {{ exporting ? 'Exportando...' : 'Exportar a XLSX' }}
      </button>
    </div>

    <!-- Filtros -->
    <div class="grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-3">
      <EmployeePicker v-model="employee" placeholder="Todos los empleados" />
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Rango: inicio</label>
        <input
          v-model="dateFrom"
          type="date"
          class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
        />
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Rango: fin</label>
        <input
          v-model="dateTo"
          type="date"
          class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
        />
      </div>
    </div>

    <!-- Vista previa: mismas columnas/formato que el xlsx -->
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div class="scroll-thin overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:border-slate-800 dark:text-slate-500">
              <th class="px-5 py-3">Employee ID</th>
              <th class="px-5 py-3">Employee Name</th>
              <th class="px-5 py-3">Work date</th>
              <th class="px-5 py-3">Year&amp;Date</th>
              <th class="px-5 py-3">Time In</th>
              <th class="px-5 py-3">Time Out</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-mono text-xs dark:divide-slate-800">
            <tr v-if="loading" v-for="i in 6" :key="'sk-' + i">
              <td class="px-5 py-4" colspan="6">
                <div class="h-8 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
              </td>
            </tr>

            <tr v-else-if="!rows.length">
              <td colspan="6" class="px-5 py-14 text-center font-sans text-sm text-slate-400 dark:text-slate-500">
                <Icon name="documentText" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-700" />
                No hay registros para este filtro/rango de fechas.
              </td>
            </tr>

            <tr
              v-else
              v-for="row in rows"
              :key="row.employee_id + '-' + row.work_date"
              class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
            >
              <td class="px-5 py-2.5 text-slate-700 dark:text-slate-300">{{ row.employee_number }}</td>
              <td class="px-5 py-2.5 whitespace-nowrap font-sans text-slate-800 dark:text-slate-100">{{ fullName(row) }}</td>
              <td class="px-5 py-2.5 text-slate-700 dark:text-slate-300">{{ compactDate(row.work_date) }}</td>
              <td class="px-5 py-2.5 text-slate-700 dark:text-slate-300">{{ calendarDateOfDatetime(row.salida) }}</td>
              <td class="px-5 py-2.5 text-slate-700 dark:text-slate-300">{{ compactTime(row.entrada) }}</td>
              <td class="px-5 py-2.5 text-slate-700 dark:text-slate-300">{{ compactTime(row.salida) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <p class="text-xs text-slate-400 dark:text-slate-500">
      "Work date" usa el dia laboral (5:00am a 4:59am del dia siguiente). "Year&amp;Date" es la fecha real de calendario
      de la salida — solo difieren si el turno cruzo medianoche.
    </p>
  </div>
</template>
