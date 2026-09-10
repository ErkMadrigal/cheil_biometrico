<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import incidentsApi from '../../api/incidents'
import incidentTypesApi from '../../api/incidentTypes'
import { resolveMediaUrl } from '../../utils/media'
import Icon from '../Icon.vue'
import EmployeePicker from '../attendance/EmployeePicker.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  incidentId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close', 'saved'])

const isEdit = computed(() => !!props.incidentId)

const today = () => new Date().toISOString().slice(0, 10)

const blankForm = () => ({
  incident_type_id: '',
  incident_date: today(),
  description: '',
})

const form = reactive(blankForm())
const selectedEmployee = ref(null) // null = incidencia general, no ligada a un empleado en particular
const evidenceFile = ref(null)
const evidencePreview = ref(null)
const existingEvidencePath = ref(null)

const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const generalError = ref('')

// --- Catalogo de tipos de incidencia, con alta rapida sin salir de este formulario ---
const types = ref([])
const typesLoaded = ref(false)
const addingType = ref(false)
const newTypeName = ref('')
const savingType = ref(false)

async function loadTypes() {
  try {
    types.value = await incidentTypesApi.list()
  } catch (e) {
    generalError.value = 'No se pudo cargar el catalogo de tipos de incidencia.'
  } finally {
    typesLoaded.value = true
  }
}

async function saveNewType() {
  const name = newTypeName.value.trim()
  if (!name) return
  savingType.value = true
  try {
    const created = await incidentTypesApi.create({ name })
    types.value.push(created)
    form.incident_type_id = created.id
    newTypeName.value = ''
    addingType.value = false
  } catch (e) {
    generalError.value = e.response?.data?.message || 'No se pudo agregar el tipo de incidencia.'
  } finally {
    savingType.value = false
  }
}

function resetForm() {
  Object.assign(form, blankForm())
  selectedEmployee.value = null
  evidenceFile.value = null
  evidencePreview.value = null
  existingEvidencePath.value = null
  errors.value = {}
  generalError.value = ''
  addingType.value = false
  newTypeName.value = ''
}

async function loadIncident(id) {
  loading.value = true
  try {
    const data = await incidentsApi.get(id)
    form.incident_type_id = data.incident_type_id
    form.incident_date = data.incident_date ? data.incident_date.slice(0, 10) : today()
    form.description = data.description || ''
    existingEvidencePath.value = data.evidence_path

    if (data.employee_id) {
      selectedEmployee.value = {
        id: data.employee_id,
        first_name: data.first_name,
        paternal_last_name: data.paternal_last_name,
        maternal_last_name: data.maternal_last_name,
        employee_number: data.employee_number,
      }
    }
  } catch (e) {
    generalError.value = 'No se pudo cargar la incidencia.'
  } finally {
    loading.value = false
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) return
    resetForm()
    if (props.incidentId) {
      await loadIncident(props.incidentId)
    }
  },
)

function onEvidenceChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  evidenceFile.value = file
  evidencePreview.value = file.type.startsWith('image/') ? URL.createObjectURL(file) : null
}

const existingEvidenceUrl = computed(() => resolveMediaUrl(existingEvidencePath.value))

async function onSubmit() {
  saving.value = true
  errors.value = {}
  generalError.value = ''

  const payload = {
    incident_type_id: form.incident_type_id,
    incident_date: form.incident_date,
    description: form.description || '',
    employee_id: selectedEmployee.value?.id ?? '',
  }

  try {
    if (isEdit.value) {
      await incidentsApi.update(props.incidentId, payload, evidenceFile.value)
    } else {
      await incidentsApi.create(payload, evidenceFile.value)
    }
    emit('saved', isEdit.value ? 'Incidencia actualizada.' : 'Incidencia registrada.')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {}
      generalError.value = 'Revisa los campos marcados.'
    } else {
      generalError.value = e.response?.data?.message || 'No se pudo guardar la incidencia.'
    }
  } finally {
    saving.value = false
  }
}

onMounted(loadTypes)
</script>

<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="!saving && emit('close')" />

      <aside class="absolute right-0 top-0 flex h-full w-full max-w-lg flex-col bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
          <div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
              {{ isEdit ? 'Editar incidencia' : 'Nueva incidencia' }}
            </h3>
            <p class="text-xs text-slate-400 dark:text-slate-500">
              Mucho trafico, siniestro en carretera, clima, etc. No se liga a una checada especifica.
            </p>
          </div>
          <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="emit('close')">
            <Icon name="xMark" class="h-5 w-5" />
          </button>
        </div>

        <div v-if="loading" class="flex flex-1 items-center justify-center">
          <Icon name="spinner" class="h-6 w-6 animate-spin text-brand-500" />
        </div>

        <form v-else class="scroll-thin flex-1 space-y-5 overflow-y-auto px-6 py-5" @submit.prevent="onSubmit">
          <p v-if="generalError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">
            {{ generalError }}
          </p>

          <!-- Empleado afectado: opcional -->
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">
              Empleado afectado (opcional)
            </label>
            <EmployeePicker v-model="selectedEmployee" placeholder="General / sin empleado especifico" />
          </div>

          <!-- Tipo de incidencia + alta rapida del catalogo -->
          <div>
            <div class="mb-1 flex items-center justify-between">
              <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Tipo de incidencia *</label>
              <button
                v-if="!addingType"
                type="button"
                class="flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline dark:text-brand-300"
                @click="addingType = true"
              >
                <Icon name="plus" class="h-3.5 w-3.5" />
                Nuevo tipo
              </button>
            </div>

            <div v-if="addingType" class="mb-2 flex gap-2">
              <input
                v-model="newTypeName"
                type="text"
                placeholder="Ej. Corte de energia"
                class="input flex-1"
                @keyup.enter="saveNewType"
              />
              <button
                type="button"
                :disabled="savingType || !newTypeName.trim()"
                class="rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                @click="saveNewType"
              >
                Agregar
              </button>
              <button
                type="button"
                class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                @click="addingType = false; newTypeName = ''"
              >
                Cancelar
              </button>
            </div>

            <select v-model="form.incident_type_id" required class="input">
              <option value="" disabled>Selecciona un tipo</option>
              <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
            <p v-if="errors.incident_type_id" class="mt-1 text-xs text-red-500">{{ errors.incident_type_id }}</p>
            <p v-else-if="typesLoaded && !types.length" class="mt-1 text-xs text-amber-500">
              No hay tipos en el catalogo todavia, agrega el primero arriba.
            </p>
          </div>

          <!-- Fecha -->
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Fecha *</label>
            <input v-model="form.incident_date" type="date" required class="input" />
            <p v-if="errors.incident_date" class="mt-1 text-xs text-red-500">{{ errors.incident_date }}</p>
          </div>

          <!-- Descripcion -->
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Descripcion</label>
            <textarea v-model="form.description" rows="3" class="input resize-none" placeholder="Detalles de lo que paso..." />
          </div>

          <!-- Evidencia: opcional -->
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Evidencia (opcional)</label>

            <div v-if="existingEvidenceUrl && !evidenceFile" class="mb-2">
              <a :href="existingEvidenceUrl" target="_blank" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-300">
                Ver evidencia actual
              </a>
            </div>
            <div v-if="evidencePreview" class="mb-2">
              <img :src="evidencePreview" class="h-24 w-auto rounded-lg border border-slate-200 object-cover dark:border-slate-700" />
            </div>

            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-300 px-3 py-2.5 text-sm text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
              <Icon name="upload" class="h-4 w-4" />
              {{ evidenceFile ? evidenceFile.name : 'Subir foto o documento' }}
              <input type="file" accept="image/*,.pdf" class="hidden" @change="onEvidenceChange" />
            </label>
          </div>
        </form>

        <div v-if="!loading" class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4 dark:border-slate-800">
          <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="emit('close')">
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
