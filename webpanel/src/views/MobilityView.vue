<script setup>
import { ref, onMounted } from 'vue'
import employeesApi from '../api/employees'
import { useToastStore } from '../stores/toast'
import Icon from '../components/Icon.vue'
import Avatar from '../components/ui/Avatar.vue'
import Badge from '../components/ui/Badge.vue'
import EmployeePicker from '../components/attendance/EmployeePicker.vue'

const toast = useToastStore()

function fullName(e) {
  return [e.first_name, e.paternal_last_name, e.maternal_last_name].filter(Boolean).join(' ')
}

// --- Escoger un empleado y prender/apagar su permiso de checar desde cualquier lugar ---
const selected = ref(null)
const selectedUnrestricted = ref(false)
const saving = ref(false)

function onSelect(emp) {
  selected.value = emp
  selectedUnrestricted.value = !!Number(emp?.checkin_unrestricted)
}

async function saveSelected() {
  if (!selected.value) return
  saving.value = true
  try {
    await employeesApi.update(selected.value.id, { checkin_unrestricted: selectedUnrestricted.value ? 1 : 0 })
    toast.success(
      selectedUnrestricted.value
        ? `${fullName(selected.value)} ya puede checar desde cualquier lugar.`
        : `${fullName(selected.value)} ya vuelve a necesitar estar en una zona/Home Office valida.`,
    )
    // Refleja el cambio en la lista de abajo sin tener que recargar todo.
    const idx = unrestrictedList.value.findIndex((e) => e.id === selected.value.id)
    if (selectedUnrestricted.value && idx === -1) {
      unrestrictedList.value.unshift({ ...selected.value, checkin_unrestricted: 1 })
    } else if (!selectedUnrestricted.value && idx !== -1) {
      unrestrictedList.value.splice(idx, 1)
    }
  } catch (e) {
    toast.error('No se pudo guardar el cambio.')
  } finally {
    saving.value = false
  }
}

// --- Lista de quienes ya tienen el permiso activo, para verlos de un vistazo ---
const unrestrictedList = ref([])
const loadingList = ref(true)

async function loadUnrestrictedList() {
  loadingList.value = true
  try {
    const data = await employeesApi.list({ status: 'active', per_page: 200 })
    unrestrictedList.value = data.items.filter((e) => Number(e.checkin_unrestricted) === 1)
  } catch (e) {
    toast.error('No se pudo cargar la lista de empleados con movilidad.')
  } finally {
    loadingList.value = false
  }
}

async function quickRemove(emp) {
  try {
    await employeesApi.update(emp.id, { checkin_unrestricted: 0 })
    unrestrictedList.value = unrestrictedList.value.filter((e) => e.id !== emp.id)
    if (selected.value?.id === emp.id) selectedUnrestricted.value = false
    toast.success(`${fullName(emp)} ya vuelve a necesitar estar en una zona/Home Office valida.`)
  } catch (e) {
    toast.error('No se pudo quitar el permiso.')
  }
}

onMounted(loadUnrestrictedList)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-xl font-bold text-slate-900 dark:text-white">Movilidad</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400">
        Para empleados que visitan clientes: permite que checar su asistencia sea valido desde
        CUALQUIER lugar, sin importar zonas geograficas ni su Home Office. Su checada sigue
        sirviendo como evidencia (rostro + ubicacion + hora), solo que no se restringe donde.
      </p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
      <label class="mb-2 block text-xs font-medium text-slate-500 dark:text-slate-400">Buscar empleado</label>
      <EmployeePicker :model-value="selected" placeholder="Busca por nombre o numero de empleado" @update:model-value="onSelect" />

      <div v-if="selected" class="mt-4 flex items-center justify-between gap-4 rounded-xl border border-slate-100 p-4 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <Avatar :photo-path="selected.photo_path" :name="fullName(selected)" size="md" />
          <div>
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ fullName(selected) }}</p>
            <p class="text-xs text-slate-400 dark:text-slate-500">#{{ selected.employee_number }}</p>
          </div>
        </div>

        <label class="flex items-center gap-2.5">
          <input
            v-model="selectedUnrestricted"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"
          />
          <span class="text-sm text-slate-700 dark:text-slate-300">Puede checar desde cualquier lugar</span>
        </label>
      </div>

      <button
        v-if="selected"
        type="button"
        :disabled="saving"
        class="mt-4 flex items-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95 disabled:opacity-60"
        @click="saveSelected"
      >
        <Icon v-if="saving" name="spinner" class="h-4 w-4 animate-spin" />
        {{ saving ? 'Guardando...' : 'Guardar' }}
      </button>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Con movilidad activa</h3>
        <p class="text-xs text-slate-400 dark:text-slate-500">Empleados que hoy pueden checar desde cualquier lugar.</p>
      </div>

      <div v-if="loadingList" class="space-y-2 p-4">
        <div v-for="i in 3" :key="i" class="h-14 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />
      </div>

      <p v-else-if="!unrestrictedList.length" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
        <Icon name="compass" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-700" />
        Nadie tiene este permiso activo todavia.
      </p>

      <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
        <div v-for="emp in unrestrictedList" :key="emp.id" class="flex items-center justify-between gap-3 px-5 py-3">
          <div class="flex items-center gap-3">
            <Avatar :photo-path="emp.photo_path" :name="fullName(emp)" size="sm" />
            <div>
              <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ fullName(emp) }}</p>
              <p class="text-xs text-slate-400 dark:text-slate-500">#{{ emp.employee_number }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <Badge color="brand">Sin restriccion</Badge>
            <button
              type="button"
              class="rounded-lg px-2.5 py-1 text-xs font-medium text-red-500 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
              @click="quickRemove(emp)"
            >
              Quitar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
