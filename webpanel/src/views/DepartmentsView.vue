<script setup>
import { ref, onMounted, computed } from 'vue'
import departmentsApi from '../api/departments'
import { useToastStore } from '../stores/toast'
import Icon from '../components/Icon.vue'
import Badge from '../components/ui/Badge.vue'

const toast = useToastStore()

const items = ref([])
const loading = ref(true)

// Alta nueva
const newName = ref('')
const creating = ref(false)

// Edicion inline (solo el nombre, es lo unico que tiene el catalogo)
const editingId = ref(null)
const editingName = ref('')
const savingId = ref(null)

const confirmDeactivateId = ref(null)

const activeItems = computed(() => items.value.filter((d) => Number(d.is_active) === 1))
const inactiveItems = computed(() => items.value.filter((d) => Number(d.is_active) !== 1))

async function load() {
  loading.value = true
  try {
    items.value = await departmentsApi.list()
  } catch (e) {
    toast.error('No se pudo cargar el catalogo de departamentos.')
  } finally {
    loading.value = false
  }
}

async function createDepartment() {
  const name = newName.value.trim()
  if (!name) return
  creating.value = true
  try {
    await departmentsApi.create({ name })
    newName.value = ''
    toast.success(`Departamento "${name}" agregado.`)
    load()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'No se pudo agregar el departamento.')
  } finally {
    creating.value = false
  }
}

function startEdit(row) {
  editingId.value = row.id
  editingName.value = row.name
}

function cancelEdit() {
  editingId.value = null
  editingName.value = ''
}

async function saveEdit(row) {
  const name = editingName.value.trim()
  if (!name) return
  savingId.value = row.id
  try {
    await departmentsApi.update(row.id, { name })
    toast.success('Departamento actualizado.')
    cancelEdit()
    load()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'No se pudo actualizar.')
  } finally {
    savingId.value = null
  }
}

async function deactivate(row) {
  savingId.value = row.id
  try {
    await departmentsApi.remove(row.id)
    toast.success(`"${row.name}" fue desactivado.`)
    confirmDeactivateId.value = null
    load()
  } catch (e) {
    toast.error('No se pudo desactivar.')
  } finally {
    savingId.value = null
  }
}

async function reactivate(row) {
  savingId.value = row.id
  try {
    await departmentsApi.update(row.id, { name: row.name, is_active: 1 })
    toast.success(`"${row.name}" fue reactivado.`)
    load()
  } catch (e) {
    toast.error('No se pudo reactivar.')
  } finally {
    savingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div>
      <h2 class="text-xl font-bold text-slate-900 dark:text-white">Departamentos</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400">
        Catalogo de departamentos de la empresa. Es obligatorio elegir uno al dar de alta o
        editar un empleado, y los reportes se pueden agrupar/filtrar por departamento.
      </p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
      <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="createDepartment">
        <input
          v-model="newName"
          type="text"
          placeholder="Nombre del nuevo departamento (ej. Retail Center)"
          class="flex-1 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
        />
        <button
          type="submit"
          :disabled="creating || !newName.trim()"
          class="flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 transition-opacity hover:opacity-95 disabled:opacity-50"
        >
          <Icon v-if="creating" name="spinner" class="h-4 w-4 animate-spin" />
          <Icon v-else name="plus" class="h-4 w-4" />
          Agregar
        </button>
      </form>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />
    </div>

    <div v-else class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
          Activos <span class="text-slate-400">({{ activeItems.length }})</span>
        </h3>
      </div>

      <p v-if="!activeItems.length" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
        <Icon name="building" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-700" />
        No hay departamentos activos todavia.
      </p>

      <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
        <div v-for="row in activeItems" :key="row.id" class="flex items-center justify-between gap-3 px-5 py-3">
          <div class="flex min-w-0 flex-1 items-center gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-500/10">
              <Icon name="building" class="h-4.5 w-4.5 text-brand-600 dark:text-brand-300" />
            </div>
            <input
              v-if="editingId === row.id"
              v-model="editingName"
              type="text"
              class="min-w-0 flex-1 rounded-lg border border-brand-300 bg-white px-3 py-1.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-brand-700 dark:bg-slate-800 dark:text-slate-100"
              @keyup.enter="saveEdit(row)"
              @keyup.esc="cancelEdit"
            />
            <p v-else class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ row.name }}</p>
          </div>

          <div class="flex items-center gap-1.5">
            <template v-if="editingId === row.id">
              <button
                type="button"
                :disabled="savingId === row.id"
                class="rounded-lg bg-brand-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                @click="saveEdit(row)"
              >
                Guardar
              </button>
              <button
                type="button"
                class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                @click="cancelEdit"
              >
                Cancelar
              </button>
            </template>
            <template v-else-if="confirmDeactivateId === row.id">
              <span class="mr-1 text-xs text-slate-500 dark:text-slate-400">¿Desactivar?</span>
              <button
                type="button"
                :disabled="savingId === row.id"
                class="rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                @click="deactivate(row)"
              >
                Si
              </button>
              <button
                type="button"
                class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                @click="confirmDeactivateId = null"
              >
                No
              </button>
            </template>
            <template v-else>
              <button
                type="button"
                title="Renombrar"
                class="rounded-lg p-2 text-slate-400 hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-300"
                @click="startEdit(row)"
              >
                <Icon name="pencil" class="h-4 w-4" />
              </button>
              <button
                type="button"
                title="Desactivar"
                class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                @click="confirmDeactivateId = row.id"
              >
                <Icon name="trash" class="h-4 w-4" />
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!loading && inactiveItems.length" class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
          Desactivados <span class="text-slate-400">({{ inactiveItems.length }})</span>
        </h3>
        <p class="text-xs text-slate-400 dark:text-slate-500">
          Se dejan aqui (no se borran) para no romper el historial de empleados que ya los usaron.
        </p>
      </div>
      <div class="divide-y divide-slate-100 dark:divide-slate-800">
        <div v-for="row in inactiveItems" :key="row.id" class="flex items-center justify-between gap-3 px-5 py-3">
          <div class="flex items-center gap-3">
            <Badge color="slate">Inactivo</Badge>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ row.name }}</p>
          </div>
          <button
            type="button"
            :disabled="savingId === row.id"
            class="rounded-lg px-2.5 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 disabled:opacity-60 dark:text-brand-300 dark:hover:bg-brand-500/10"
            @click="reactivate(row)"
          >
            Reactivar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
