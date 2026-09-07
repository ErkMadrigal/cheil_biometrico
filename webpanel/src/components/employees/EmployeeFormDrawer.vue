<script setup>
import { ref, reactive, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import employeesApi from '../../api/employees'
import { resolveMediaUrl } from '../../utils/media'
import Icon from '../Icon.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  employeeId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close', 'saved'])

const isEdit = computed(() => !!props.employeeId)

const blankForm = () => ({
  employee_number: '',
  zkteco_pin: '',
  first_name: '',
  paternal_last_name: '',
  maternal_last_name: '',
  curp: '',
  rfc: '',
  email: '',
  phone: '',
  position: '',
  department: '',
  hire_date: '',
  status: 'active',
})

const form = reactive(blankForm())
const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const generalError = ref('')

const photoFile = ref(null)
const photoPreview = ref(null)
const existingPhotoPath = ref(null)

// Home Office: solo lectura aqui (la fija el empleado desde la app con verificacion
// facial + GPS, ver MobileController::homeLocation()). Aqui solo se muestra y, si esta
// bloqueada, se puede desbloquear para que el empleado la vuelva a capturar.
const homeLocation = ref(null) // { lat, lng, radius, locked, setAt } | null si nunca la ha configurado
const homeMapEl = ref(null)
let homeMap = null
const unlocking = ref(false)
const unlockError = ref('')
const unlockedJustNow = ref(false)

function resetForm() {
  Object.assign(form, blankForm())
  errors.value = {}
  generalError.value = ''
  photoFile.value = null
  photoPreview.value = null
  existingPhotoPath.value = null
  homeLocation.value = null
  unlockError.value = ''
  unlockedJustNow.value = false
}

function initHomeMap() {
  if (homeMap || !homeMapEl.value || !homeLocation.value) return
  const { lat, lng, radius } = homeLocation.value
  homeMap = L.map(homeMapEl.value, { zoomControl: false, dragging: false, scrollWheelZoom: false }).setView([lat, lng], 15)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
  }).addTo(homeMap)
  L.marker([lat, lng]).addTo(homeMap)
  L.circle([lat, lng], { radius, color: '#10b981', fillColor: '#10b981', fillOpacity: 0.15 }).addTo(homeMap)
  setTimeout(() => homeMap?.invalidateSize(), 150)
}

function destroyHomeMap() {
  homeMap?.remove()
  homeMap = null
}

async function unlockHomeLocation() {
  unlocking.value = true
  unlockError.value = ''
  try {
    await employeesApi.unlockHomeLocation(props.employeeId)
    homeLocation.value.locked = false
    unlockedJustNow.value = true
  } catch (e) {
    unlockError.value = e.response?.data?.message || 'No se pudo desbloquear la ubicacion.'
  } finally {
    unlocking.value = false
  }
}

async function loadEmployee(id) {
  loading.value = true
  try {
    const data = await employeesApi.get(id)
    Object.assign(form, {
      employee_number: data.employee_number || '',
      zkteco_pin: data.zkteco_pin || '',
      first_name: data.first_name || '',
      paternal_last_name: data.paternal_last_name || '',
      maternal_last_name: data.maternal_last_name || '',
      curp: data.curp || '',
      rfc: data.rfc || '',
      email: data.email || '',
      phone: data.phone || '',
      position: data.position || '',
      department: data.department || '',
      hire_date: data.hire_date ? data.hire_date.slice(0, 10) : '',
      status: data.status || 'active',
    })
    existingPhotoPath.value = data.photo_path

    if (data.home_lat !== null && data.home_lat !== undefined) {
      homeLocation.value = {
        lat: Number(data.home_lat),
        lng: Number(data.home_lng),
        radius: Number(data.home_radius_meters) || 100,
        locked: !!Number(data.home_location_locked),
        setAt: data.home_location_set_at,
      }
    } else {
      homeLocation.value = null
    }
  } catch (e) {
    generalError.value = 'No se pudo cargar la informacion del empleado.'
  } finally {
    loading.value = false
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) {
      destroyHomeMap()
      return
    }
    resetForm()
    if (props.employeeId) {
      await loadEmployee(props.employeeId)
      // Hasta aqui "loading" ya volvio a false y el formulario (con el div del mapa)
      // ya esta en el DOM -- si initHomeMap() se llamara antes, el div todavia no
      // existiria (se estaria mostrando el spinner de carga) y no haria nada.
      await nextTick()
      initHomeMap()
    }
  },
)

onBeforeUnmount(destroyHomeMap)

function onPhotoChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  photoFile.value = file
  photoPreview.value = URL.createObjectURL(file)
}

const previewUrl = computed(() => photoPreview.value || resolveMediaUrl(existingPhotoPath.value))

async function onSubmit() {
  saving.value = true
  errors.value = {}
  generalError.value = ''

  const payload = { ...form }
  // No mandes vacios como string; deja que el backend los trate como null/omitidos
  Object.keys(payload).forEach((k) => {
    if (payload[k] === '') delete payload[k]
  })

  try {
    let id = props.employeeId
    if (isEdit.value) {
      await employeesApi.update(id, payload)
    } else {
      const created = await employeesApi.create(payload)
      id = created.id
    }

    if (photoFile.value) {
      await employeesApi.uploadPhoto(id, photoFile.value)
    }

    emit('saved', isEdit.value ? 'Empleado actualizado.' : 'Empleado creado correctamente.')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {}
      generalError.value = 'Revisa los campos marcados.'
    } else {
      generalError.value = e.response?.data?.message || 'No se pudo guardar el empleado.'
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

      <aside class="absolute right-0 top-0 flex h-full w-full max-w-lg flex-col bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
          <div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
              {{ isEdit ? 'Editar empleado' : 'Nuevo empleado' }}
            </h3>
            <p class="text-xs text-slate-400 dark:text-slate-500">Informacion basica del empleado</p>
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

          <!-- Foto -->
          <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
              <img v-if="previewUrl" :src="previewUrl" class="h-full w-full object-cover" />
              <Icon v-else name="idCard" class="h-6 w-6 text-slate-300 dark:text-slate-600" />
            </div>
            <label class="cursor-pointer rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
              Subir foto
              <input type="file" accept="image/*" class="hidden" @change="onPhotoChange" />
            </label>
          </div>

          <!-- Nombre -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="sm:col-span-3">
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Nombre(s) *</label>
              <input v-model="form.first_name" type="text" required class="input" />
              <p v-if="errors.first_name" class="mt-1 text-xs text-red-500">{{ errors.first_name }}</p>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Apellido paterno *</label>
              <input v-model="form.paternal_last_name" type="text" required class="input" />
              <p v-if="errors.paternal_last_name" class="mt-1 text-xs text-red-500">{{ errors.paternal_last_name }}</p>
            </div>
            <div class="sm:col-span-2">
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Apellido materno</label>
              <input v-model="form.maternal_last_name" type="text" class="input" />
            </div>
          </div>

          <!-- Identificadores -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">No. de empleado *</label>
              <input v-model="form.employee_number" type="text" required class="input" placeholder="EMP-0001" />
              <p v-if="errors.employee_number" class="mt-1 text-xs text-red-500">{{ errors.employee_number }}</p>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">
                PIN del checador ZKTeco
              </label>
              <input v-model="form.zkteco_pin" type="text" class="input" placeholder="1001" />
              <p v-if="errors.zkteco_pin" class="mt-1 text-xs text-red-500">{{ errors.zkteco_pin }}</p>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">CURP</label>
              <input v-model="form.curp" type="text" maxlength="18" class="input uppercase" />
              <p v-if="errors.curp" class="mt-1 text-xs text-red-500">{{ errors.curp }}</p>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">RFC</label>
              <input v-model="form.rfc" type="text" maxlength="13" class="input uppercase" />
              <p v-if="errors.rfc" class="mt-1 text-xs text-red-500">{{ errors.rfc }}</p>
            </div>
          </div>

          <!-- Contacto -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Correo</label>
              <input v-model="form.email" type="email" class="input" />
              <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email }}</p>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Telefono</label>
              <input v-model="form.phone" type="tel" class="input" />
            </div>
          </div>

          <!-- Puesto -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Puesto</label>
              <input v-model="form.position" type="text" class="input" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Departamento</label>
              <input v-model="form.department" type="text" class="input" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Fecha de ingreso</label>
              <input v-model="form.hire_date" type="date" class="input" />
            </div>
            <div v-if="isEdit">
              <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Estado</label>
              <select v-model="form.status" class="input">
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
              </select>
            </div>
          </div>

          <!-- Home Office: solo lectura, el empleado la configura desde la app -->
          <div v-if="isEdit" class="border-t border-slate-200 pt-5 dark:border-slate-800">
            <p class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400">Ubicacion Home Office</p>

            <div v-if="!homeLocation" class="rounded-lg border border-dashed border-slate-300 px-3 py-3 text-xs text-slate-400 dark:border-slate-700 dark:text-slate-500">
              Este empleado todavia no ha configurado su ubicacion Home Office. Se hace desde la app (con
              verificacion facial y GPS), no desde aqui.
            </div>

            <div v-else class="space-y-2">
              <div ref="homeMapEl" class="isolate h-40 w-full rounded-xl border border-slate-200 dark:border-slate-700" />
              <p class="text-xs text-slate-400 dark:text-slate-500">
                {{ homeLocation.lat.toFixed(6) }}, {{ homeLocation.lng.toFixed(6) }} - radio de {{ homeLocation.radius }}m
                <span v-if="homeLocation.setAt"> - fijada el {{ homeLocation.setAt }}</span>
              </p>

              <div v-if="homeLocation.locked" class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
                <span class="text-xs text-slate-500 dark:text-slate-400">Bloqueada: el empleado no la puede cambiar solo.</span>
                <button
                  type="button"
                  :disabled="unlocking"
                  class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-white disabled:opacity-60 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                  @click="unlockHomeLocation"
                >
                  {{ unlocking ? 'Desbloqueando...' : 'Desbloquear' }}
                </button>
              </div>
              <p v-else-if="unlockedJustNow" class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                Desbloqueada. El empleado ya puede volver a capturarla desde la app.
              </p>
              <p v-if="unlockError" class="text-xs text-red-500">{{ unlockError }}</p>
            </div>
          </div>
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
