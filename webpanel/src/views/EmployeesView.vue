<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import employeesApi from '../api/employees'
import { useToastStore } from '../stores/toast'
import Icon from '../components/Icon.vue'
import Avatar from '../components/ui/Avatar.vue'
import Badge from '../components/ui/Badge.vue'
import EmployeeFormDrawer from '../components/employees/EmployeeFormDrawer.vue'
import FaceEnrollModal from '../components/employees/FaceEnrollModal.vue'
import EmployeeImportModal from '../components/employees/EmployeeImportModal.vue'
import EnrollLinkModal from '../components/employees/EnrollLinkModal.vue'

const toast = useToastStore()

const items = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(10)
const loading = ref(true)

const search = ref('')
const status = ref('')

const drawerOpen = ref(false)
const editingId = ref(null) // null = alta

const faceModalOpen = ref(false)
const faceEmployee = ref(null)

const importModalOpen = ref(false)

const enrollLinkModalOpen = ref(false)
const enrollLinkEmployee = ref(null)

const confirmDeleteId = ref(null)
const deletingId = ref(null)

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))
const rangeLabel = computed(() => {
  if (!total.value) return '0 empleados'
  const from = (page.value - 1) * perPage.value + 1
  const to = Math.min(page.value * perPage.value, total.value)
  return `${from}-${to} de ${total.value}`
})

function fullName(row) {
  return [row.first_name, row.paternal_last_name, row.maternal_last_name].filter(Boolean).join(' ')
}

async function load() {
  loading.value = true
  try {
    const data = await employeesApi.list({
      search: search.value || undefined,
      status: status.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    items.value = data.items
    total.value = data.total
  } catch (e) {
    toast.error('No se pudo cargar la lista de empleados.')
  } finally {
    loading.value = false
  }
}

let searchTimeout
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})

watch(status, () => {
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

function openFace(row) {
  faceEmployee.value = row
  faceModalOpen.value = true
}

function openEnrollLink(row) {
  enrollLinkEmployee.value = row
  enrollLinkModalOpen.value = true
}

function onSaved(message) {
  drawerOpen.value = false
  toast.success(message)
  load()
}

async function confirmDelete(row) {
  deletingId.value = row.id
  try {
    await employeesApi.remove(row.id)
    toast.success(`${fullName(row)} fue dado de baja.`)
    confirmDeleteId.value = null
    if (items.value.length === 1 && page.value > 1) {
      page.value -= 1
    }
    load()
  } catch (e) {
    toast.error('No se pudo dar de baja al empleado.')
  } finally {
    deletingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Empleados</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ rangeLabel }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
          @click="importModalOpen = true"
        >
          <Icon name="upload" class="h-4 w-4" />
          Importar
        </button>
        <button
          type="button"
          class="flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 transition-opacity hover:opacity-95"
          @click="openCreate"
        >
          <Icon name="plus" class="h-4 w-4" />
          Nuevo empleado
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center">
      <div class="relative flex-1">
        <Icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          v-model="search"
          type="text"
          placeholder="Buscar por nombre, numero, CURP o RFC..."
          class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
        />
      </div>
      <select
        v-model="status"
        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
      >
        <option value="">Todos los estados</option>
        <option value="active">Activos</option>
        <option value="inactive">Inactivos</option>
      </select>
    </div>

    <!-- Tabla -->
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div class="scroll-thin overflow-x-auto">
        <table class="w-full min-w-[820px] text-left text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:border-slate-800 dark:text-slate-500">
              <th class="px-5 py-3">Empleado</th>
              <th class="px-5 py-3">Puesto / Depto.</th>
              <th class="px-5 py-3">Contacto</th>
              <th class="px-5 py-3">CURP / RFC</th>
              <th class="px-5 py-3">Estado</th>
              <th class="px-5 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-if="loading" v-for="i in 5" :key="'sk-' + i">
              <td class="px-5 py-4" colspan="6">
                <div class="h-10 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
              </td>
            </tr>

            <tr v-else-if="!items.length">
              <td colspan="6" class="px-5 py-14 text-center text-sm text-slate-400 dark:text-slate-500">
                <Icon name="users" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-700" />
                No hay empleados que coincidan con la busqueda.
              </td>
            </tr>

            <tr
              v-else
              v-for="row in items"
              :key="row.id"
              class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
            >
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <Avatar :photo-path="row.photo_path" :name="fullName(row)" size="md" />
                  <div class="min-w-0">
                    <p class="truncate font-medium text-slate-800 dark:text-slate-100">{{ fullName(row) }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">#{{ row.employee_number }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3">
                <p class="text-slate-700 dark:text-slate-300">{{ row.position || '—' }}</p>
                <p v-if="row.department_name" class="text-xs text-slate-400 dark:text-slate-500">{{ row.department_name }}</p>
                <Badge v-else color="amber">
                  <Icon name="alertTriangle" class="h-3 w-3" />
                  Sin departamento
                </Badge>
              </td>
              <td class="px-5 py-3">
                <p class="text-slate-700 dark:text-slate-300">{{ row.email || '—' }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500">{{ row.phone || '—' }}</p>
              </td>
              <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                <p>{{ row.curp || '—' }}</p>
                <p>{{ row.rfc || '—' }}</p>
              </td>
              <td class="px-5 py-3">
                <Badge :color="row.status === 'active' ? 'emerald' : 'slate'">
                  {{ row.status === 'active' ? 'Activo' : 'Inactivo' }}
                </Badge>
              </td>
              <td class="px-5 py-3">
                <div v-if="confirmDeleteId === row.id" class="flex items-center justify-end gap-2">
                  <span class="text-xs text-slate-500 dark:text-slate-400">¿Dar de baja?</span>
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
                    title="Enrolar rostro"
                    class="rounded-lg p-2 text-slate-400 hover:bg-violet-50 hover:text-violet-600 dark:hover:bg-violet-500/10 dark:hover:text-violet-300"
                    @click="openFace(row)"
                  >
                    <Icon name="camera" class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    title="Generar liga de auto-enrolamiento"
                    class="rounded-lg p-2 text-slate-400 hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-500/10 dark:hover:text-sky-300"
                    @click="openEnrollLink(row)"
                  >
                    <Icon name="link" class="h-4 w-4" />
                  </button>
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
                    title="Dar de baja"
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

    <EmployeeFormDrawer
      :open="drawerOpen"
      :employee-id="editingId"
      @close="drawerOpen = false"
      @saved="onSaved"
    />

    <FaceEnrollModal
      :open="faceModalOpen"
      :employee="faceEmployee"
      @close="faceModalOpen = false"
      @saved="
        () => {
          faceModalOpen = false
          toast.success('Rostro enrolado correctamente.')
        }
      "
    />

    <EmployeeImportModal
      :open="importModalOpen"
      @close="importModalOpen = false"
      @imported="load"
    />

    <EnrollLinkModal
      :open="enrollLinkModalOpen"
      :employee="enrollLinkEmployee"
      @close="enrollLinkModalOpen = false"
    />
  </div>
</template>
