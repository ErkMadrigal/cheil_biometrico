<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import incidentsApi from '../api/incidents'
import incidentTypesApi from '../api/incidentTypes'
import departmentsApi from '../api/departments'
import { useToastStore } from '../stores/toast'
import { resolveMediaUrl } from '../utils/media'
import Icon from '../components/Icon.vue'
import Badge from '../components/ui/Badge.vue'
import EmployeePicker from '../components/attendance/EmployeePicker.vue'
import IncidentFormDrawer from '../components/incidents/IncidentFormDrawer.vue'

const toast = useToastStore()

const items = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(10)
const loading = ref(true)

const employeeFilter = ref(null)
const departmentFilter = ref('')
const typeFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')

const departments = ref([])
const types = ref([])

const drawerOpen = ref(false)
const editingId = ref(null)

const confirmDeleteId = ref(null)
const deletingId = ref(null)

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))

function fullName(row) {
  const name = [row.first_name, row.paternal_last_name, row.maternal_last_name].filter(Boolean).join(' ')
  return name || 'General (sin empleado)'
}

function dateLabel(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' })
}

async function load() {
  loading.value = true
  try {
    const data = await incidentsApi.list({
      employee_id: employeeFilter.value?.id || undefined,
      department_id: departmentFilter.value || undefined,
      incident_type_id: typeFilter.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    items.value = data.items
    total.value = data.total
  } catch (e) {
    toast.error('No se pudo cargar la lista de incidencias.')
  } finally {
    loading.value = false
  }
}

async function loadFilters() {
  try {
    const [deps, incTypes] = await Promise.all([departmentsApi.list(), incidentTypesApi.list()])
    departments.value = deps.filter((d) => Number(d.is_active) === 1)
    types.value = incTypes.filter((t) => Number(t.is_active) === 1)
  } catch (e) {
    // silencioso: los selects de filtro simplemente quedan vacios
  }
}

watch([employeeFilter, departmentFilter, typeFilter, dateFrom, dateTo], () => {
  page.value = 1
  load()
})

function goToPage(p) {
  if (p < 1 || p > totalPages.value) return
  page.value = p
  load()
}

function openCreate() {
  editingId.value = null
  drawerOpen.value = true
}

function openEdit(row) {
  editingId.value = row.id
  drawerOpen.value = true
}

function onSaved(message) {
  drawerOpen.value = false
  toast.success(message)
  load()
}

async function confirmDelete(row) {
  deletingId.value = row.id
  try {
    await incidentsApi.remove(row.id)
    toast.success('Incidencia eliminada.')
    confirmDeleteId.value = null
    if (items.value.length === 1 && page.value > 1) page.value -= 1
    load()
  } catch (e) {
    toast.error('No se pudo eliminar la incidencia.')
  } finally {
    deletingId.value = null
  }
}

onMounted(() => {
  loadFilters()
  load()
})
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Incidencias</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          Mucho trafico, siniestro en carretera, clima, etc. Registro independiente de las checadas.
        </p>
      </div>
      <button
        type="button"
        class="flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 transition-opacity hover:opacity-95"
        @click="openCreate"
      >
        <Icon name="plus" class="h-4 w-4" />
        Nueva incidencia
      </button>
    </div>

    <!-- Filtros -->
    <div class="grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-2 lg:grid-cols-5">
      <EmployeePicker v-model="employeeFilter" placeholder="Todos los empleados" />

      <div class="relative self-start">
        <select v-model="departmentFilter" class="input select-input">
          <option value="">Todos los departamentos</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <Icon name="chevronDown" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      </div>

      <div class="relative self-start">
        <select v-model="typeFilter" class="input select-input">
          <option value="">Todos los tipos</option>
          <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <Icon name="chevronDown" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      </div>

      <input v-model="dateFrom" type="date" class="input" title="Desde" />
      <input v-model="dateTo" type="date" class="input" title="Hasta" />
    </div>

    <!-- Tabla -->
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div class="scroll-thin overflow-x-auto">
        <table class="w-full min-w-[860px] text-left text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:border-slate-800 dark:text-slate-500">
              <th class="px-5 py-3">Fecha</th>
              <th class="px-5 py-3">Empleado</th>
              <th class="px-5 py-3">Departamento</th>
              <th class="px-5 py-3">Tipo</th>
              <th class="px-5 py-3">Descripcion</th>
              <th class="px-5 py-3">Evidencia</th>
              <th class="px-5 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-if="loading" v-for="i in 5" :key="'sk-' + i">
              <td class="px-5 py-4" colspan="7">
                <div class="h-10 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
              </td>
            </tr>

            <tr v-else-if="!items.length">
              <td colspan="7" class="px-5 py-14 text-center text-sm text-slate-400 dark:text-slate-500">
                <Icon name="alertTriangle" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-700" />
                No hay incidencias registradas con estos filtros.
              </td>
            </tr>

            <tr v-else v-for="row in items" :key="row.id" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <td class="px-5 py-3 text-slate-700 dark:text-slate-300">{{ dateLabel(row.incident_date) }}</td>
              <td class="px-5 py-3">
                <p class="text-slate-700 dark:text-slate-300">{{ fullName(row) }}</p>
                <p v-if="row.employee_number" class="text-xs text-slate-400 dark:text-slate-500">#{{ row.employee_number }}</p>
              </td>
              <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ row.department_name || '—' }}</td>
              <td class="px-5 py-3">
                <Badge color="amber">{{ row.incident_type_name }}</Badge>
              </td>
              <td class="max-w-xs truncate px-5 py-3 text-slate-500 dark:text-slate-400" :title="row.description">
                {{ row.description || '—' }}
              </td>
              <td class="px-5 py-3">
                <a
                  v-if="row.evidence_path"
                  :href="resolveMediaUrl(row.evidence_path)"
                  target="_blank"
                  class="flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline dark:text-brand-300"
                >
                  <Icon name="clipboard" class="h-3.5 w-3.5" />
                  Ver
                </a>
                <span v-else class="text-xs text-slate-400 dark:text-slate-500">—</span>
              </td>
              <td class="px-5 py-3">
                <div v-if="confirmDeleteId === row.id" class="flex items-center justify-end gap-2">
                  <span class="text-xs text-slate-500 dark:text-slate-400">¿Eliminar?</span>
                  <button
                    type="button"
                    :disabled="deletingId === row.id"
                    class="rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                    @click="confirmDelete(row)"
                  >
                    Si
                  </button>
                  <button
                    type="button"
                    class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                    @click="confirmDeleteId = null"
                  >
                    No
                  </button>
                </div>
                <div v-else class="flex items-center justify-end gap-1">
                  <button
                    type="button"
                    title="Editar"
                    class="rounded-lg p-2 text-slate-400 hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-300"
                    @click="openEdit(row)"
                  >
                    <Icon name="pencil" class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    title="Eliminar"
                    class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                    @click="confirmDeleteId = row.id"
                  >
                    <Icon name="trash" class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginacion -->
      <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3 dark:border-slate-800">
        <p class="text-xs text-slate-400 dark:text-slate-500">Pagina {{ page }} de {{ totalPages }}</p>
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

    <IncidentFormDrawer :open="drawerOpen" :incident-id="editingId" @close="drawerOpen = false" @saved="onSaved" />
  </div>
</template>

<style scoped>
@reference "../style.css";

.input {
  @apply w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200;
}

.select-input {
  @apply appearance-none pr-9;
}
</style>
