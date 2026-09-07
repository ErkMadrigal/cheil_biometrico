<script setup>
import { ref, reactive, computed, watch } from 'vue'
import devicesApi from '../../api/devices'
import Icon from '../Icon.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  deviceId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close', 'saved'])

const isEdit = computed(() => !!props.deviceId)

const TYPES = [
  { value: 'fingerprint_entry', label: 'Huella (entrada)' },
  { value: 'face_exit', label: 'Facial (salida)' },
  { value: 'other', label: 'Otro' },
]

const blankForm = () => ({
  name: '',
  serial_number: '',
  type: 'fingerprint_entry',
  location_label: '',
  ip_address: '',
  is_active: true,
})

const form = reactive(blankForm())
const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const generalError = ref('')

function resetForm() {
  Object.assign(form, blankForm())
  errors.value = {}
  generalError.value = ''
}

async function loadDevice(id) {
  loading.value = true
  try {
    const data = await devicesApi.get(id)
    Object.assign(form, {
      name: data.name || '',
      serial_number: data.serial_number || '',
      type: data.type || 'other',
      location_label: data.location_label || '',
      ip_address: data.ip_address || '',
      is_active: !!Number(data.is_active),
    })
  } catch (e) {
    generalError.value = 'No se pudo cargar la informacion del dispositivo.'
  } finally {
    loading.value = false
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    resetForm()
    if (props.deviceId) {
      loadDevice(props.deviceId)
    }
  },
)

async function onSubmit() {
  saving.value = true
  errors.value = {}
  generalError.value = ''

  const payload = { ...form, is_active: form.is_active ? 1 : 0 }
  Object.keys(payload).forEach((k) => {
    if (payload[k] === '') delete payload[k]
  })

  try {
    if (isEdit.value) {
      await devicesApi.update(props.deviceId, payload)
    } else {
      await devicesApi.create(payload)
    }
    emit('saved', isEdit.value ? 'Dispositivo actualizado.' : 'Dispositivo creado correctamente.')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {}
      generalError.value = 'Revisa los campos marcados.'
    } else {
      generalError.value = e.response?.data?.message || 'No se pudo guardar el dispositivo.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="!saving && emit('close')" />

      <aside class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
          <div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
              {{ isEdit ? 'Editar dispositivo' : 'Nuevo dispositivo' }}
            </h3>
            <p class="text-xs text-slate-400 dark:text-slate-500">Checador ZKTeco u otro dispositivo</p>
          </div>
          <button
            type="button"
            class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
            @click="emit('close')"
          >
            <Icon name="xMark" class="h-5 w-5" />
          </button>
        </div>

        <div v-if="loading" class="flex flex-1 items-center justify-center">
          <Icon name="spinner" class="h-6 w-6 animate-spin text-brand-500" />
        </div>

        <form v-else class="scroll-thin flex-1 space-y-5 overflow-y-auto px-6 py-5" @submit.prevent="onSubmit">
          <p
            v-if="generalError"
            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400"
          >
            {{ generalError }}
          </p>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Nombre *</label>
            <input v-model="form.name" type="text" required class="input" placeholder="Checador entrada - Recepcion" />
            <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">No. de serie (SN) *</label>
            <input v-model="form.serial_number" type="text" required class="input" placeholder="CJYL12345678" />
            <p v-if="errors.serial_number" class="mt-1 text-xs text-red-500">{{ errors.serial_number }}</p>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Tipo *</label>
            <select v-model="form.type" required class="input">
              <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <p v-if="errors.type" class="mt-1 text-xs text-red-500">{{ errors.type }}</p>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Ubicacion</label>
            <input v-model="form.location_label" type="text" class="input" placeholder="Puerta principal - Oficina Polanco" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">IP</label>
            <input v-model="form.ip_address" type="text" class="input" placeholder="192.168.1.50" />
          </div>

          <label class="flex items-center gap-2.5">
            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
            <span class="text-sm text-slate-700 dark:text-slate-300">Dispositivo activo</span>
          </label>
        </form>

        <div v-if="!loading" class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4 dark:border-slate-800">
          <button
            type="button"
            class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
            @click="emit('close')"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="saving"
            class="flex items-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95 disabled:opacity-60"
            @click="onSubmit"
          >
            <Icon v-if="saving" name="spinner" class="h-4 w-4 animate-spin" />
            {{ saving ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </aside>
    </div>
  </teleport>
</template>

<style scoped>
@reference "../../style.css";

.input {
  @apply w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition-shadow focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-slate-800 dark:text-white;
}
</style>
