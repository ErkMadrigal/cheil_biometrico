<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useFaceCamera } from '../composables/useFaceCamera'
import kioskApi from '../api/kiosk'
import { resolveMediaUrl } from '../utils/media'
import Icon from '../components/Icon.vue'
import Avatar from '../components/ui/Avatar.vue'
import ThemeToggle from '../components/ThemeToggle.vue'

const router = useRouter()

// identify -> confirm -> camera -> success
const step = ref('identify')

const query = ref('')
const searching = ref(false)
const searchError = ref('')
const employee = ref(null)

const videoRef = ref(null)
const canvasRef = ref(null)
const cam = useFaceCamera(videoRef, canvasRef)
const verifying = ref(false)
const checkinError = ref('')
const result = ref(null)

let resetTimer = null

function fullName(e) {
  if (!e) return ''
  return [e.first_name, e.paternal_last_name, e.maternal_last_name].filter(Boolean).join(' ')
}

async function search() {
  const q = query.value.trim()
  if (!q) return
  searching.value = true
  searchError.value = ''
  try {
    employee.value = await kioskApi.lookup(q)
    step.value = 'confirm'
  } catch (e) {
    searchError.value = e.response?.data?.message || 'No se pudo buscar. Intenta de nuevo.'
  } finally {
    searching.value = false
  }
}

function backToIdentify() {
  query.value = ''
  employee.value = null
  searchError.value = ''
  step.value = 'identify'
}

async function goToCamera() {
  step.value = 'camera'
  checkinError.value = ''
  await cam.start()
}

function getGeolocation() {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve({})
    const timer = setTimeout(() => resolve({}), 4000)
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        clearTimeout(timer)
        resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude })
      },
      () => {
        clearTimeout(timer)
        resolve({})
      },
      { timeout: 3500 },
    )
  })
}

async function verifyAndCheckin() {
  if (!cam.faceDetected.value || verifying.value) return
  verifying.value = true
  checkinError.value = ''

  try {
    const descriptor = await cam.captureDescriptor()
    if (!descriptor) {
      checkinError.value = 'Se perdio el rostro justo al capturar, intenta de nuevo.'
      cam.resume()
      return
    }

    const geo = await getGeolocation()
    const data = await kioskApi.checkin({
      employee_id: employee.value.id,
      descriptor: Array.from(descriptor),
      ...geo,
    })

    cam.cleanup()
    result.value = data
    step.value = 'success'
    resetTimer = setTimeout(resetAll, 6000)
  } catch (e) {
    checkinError.value = e.response?.data?.message || 'No se pudo registrar la checada. Intenta de nuevo.'
    cam.resume()
  } finally {
    verifying.value = false
  }
}

function resetAll() {
  clearTimeout(resetTimer)
  cam.cleanup()
  query.value = ''
  employee.value = null
  searchError.value = ''
  checkinError.value = ''
  result.value = null
  step.value = 'identify'
}

const resultTime = computed(() => {
  if (!result.value?.record?.recorded_at) return ''
  return new Date(result.value.record.recorded_at.replace(' ', 'T')).toLocaleTimeString('es-MX', {
    hour: '2-digit',
    minute: '2-digit',
  })
})

onBeforeUnmount(() => {
  clearTimeout(resetTimer)
  cam.cleanup()
})
</script>

<template>
  <div class="relative flex min-h-screen flex-col overflow-hidden bg-slate-50 dark:bg-slate-950">
    <div class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-brand-400/30 blur-3xl dark:bg-brand-600/20" />
    <div class="pointer-events-none absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-fuchsia-400/20 blur-3xl dark:bg-fuchsia-600/10" />

    <header class="relative flex items-center justify-between px-5 py-4 sm:px-8">
      <button
        type="button"
        class="flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
        @click="router.push({ name: 'login' })"
      >
        <Icon name="chevronLeft" class="h-4 w-4" />
        Volver al login
      </button>
      <ThemeToggle />
    </header>

    <main class="relative flex flex-1 items-center justify-center px-4 pb-10">
      <div class="w-full max-w-md">
        <div class="mb-6 flex flex-col items-center text-center">
          <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-xl shadow-brand-500/30">
            <Icon name="faceSmile" class="h-6 w-6" />
          </div>
          <h1 class="text-xl font-bold text-slate-900 dark:text-white">Registro biometrico</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Sin password: solo tu identificacion y tu rostro.
          </p>
        </div>

        <!-- Paso 1: identificarse -->
        <form
          v-if="step === 'identify'"
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900"
          @submit.prevent="search"
        >
          <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
            Numero de empleado, CURP o RFC
          </label>
          <input
            v-model="query"
            type="text"
            required
            autofocus
            placeholder="EMP-0001"
            class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition-shadow placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
          />
          <p v-if="searchError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">
            {{ searchError }}
          </p>
          <button
            type="submit"
            :disabled="searching"
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95 disabled:opacity-60"
          >
            <Icon v-if="searching" name="spinner" class="h-4 w-4 animate-spin" />
            {{ searching ? 'Buscando...' : 'Continuar' }}
          </button>
        </form>

        <!-- Paso 2: confirmar identidad -->
        <div
          v-else-if="step === 'confirm'"
          class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900"
        >
          <Avatar :photo-path="employee.photo_path" :name="fullName(employee)" size="lg" class="mx-auto" />
          <p class="mt-3 text-lg font-semibold text-slate-900 dark:text-white">{{ fullName(employee) }}</p>
          <p class="text-sm text-slate-400 dark:text-slate-500">
            #{{ employee.employee_number }}<span v-if="employee.position"> &middot; {{ employee.position }}</span>
          </p>

          <template v-if="employee.has_face_enrolled">
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">¿Eres tu?</p>
            <div class="mt-4 flex gap-3">
              <button
                type="button"
                class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                @click="backToIdentify"
              >
                No soy yo
              </button>
              <button
                type="button"
                class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95"
                @click="goToCamera"
              >
                <Icon name="camera" class="h-4 w-4" />
                Si, continuar
              </button>
            </div>
          </template>
          <template v-else>
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
              Tu rostro todavia no esta enrolado. Pide a RH que lo registre desde el panel.
            </p>
            <button
              type="button"
              class="mt-4 w-full rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
              @click="backToIdentify"
            >
              Buscar de nuevo
            </button>
          </template>
        </div>

        <!-- Paso 3: camara -->
        <div
          v-else-if="step === 'camera'"
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900"
        >
          <div class="relative mx-auto aspect-square w-full max-w-[280px] overflow-hidden rounded-2xl bg-slate-900">
            <video ref="videoRef" class="h-full w-full -scale-x-100 object-cover" muted playsinline />
            <canvas ref="canvasRef" class="absolute inset-0 h-full w-full -scale-x-100" />

            <div
              v-if="cam.stage.value === 'loadingModels' || cam.stage.value === 'requestingCamera' || verifying"
              class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-slate-900/80 text-white"
            >
              <Icon name="spinner" class="h-6 w-6 animate-spin" />
              <p class="text-xs">
                {{
                  verifying
                    ? 'Verificando...'
                    : cam.stage.value === 'loadingModels'
                      ? 'Cargando modelo facial...'
                      : 'Pidiendo acceso a camara...'
                }}
              </p>
            </div>
          </div>

          <div class="mt-4 flex items-center justify-center gap-2 text-sm">
            <span
              v-if="cam.stage.value === 'scanning'"
              class="h-2 w-2 rounded-full"
              :class="cam.faceDetected.value ? 'bg-emerald-500' : 'animate-pulse bg-amber-400'"
            />
            <span v-if="cam.stage.value === 'scanning'" class="text-slate-600 dark:text-slate-300">
              {{ cam.faceDetected.value ? 'Rostro detectado, listo' : 'Ubica tu rostro en el cuadro' }}
            </span>
          </div>

          <p v-if="cam.errorMsg.value || checkinError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-center text-xs text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">
            {{ cam.errorMsg.value || checkinError }}
          </p>

          <div class="mt-5 flex gap-3">
            <button
              type="button"
              class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
              @click="resetAll"
            >
              Cancelar
            </button>
            <button
              type="button"
              :disabled="cam.stage.value !== 'scanning' || !cam.faceDetected.value || verifying"
              class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40"
              @click="verifyAndCheckin"
            >
              <Icon name="camera" class="h-4 w-4" />
              Verificar y checar
            </button>
          </div>
        </div>

        <!-- Paso 4: exito -->
        <div
          v-else-if="step === 'success'"
          class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900"
        >
          <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
            <Icon name="check" class="h-8 w-8" />
          </span>
          <p class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
            Listo, {{ result?.employee?.name?.split(' ')[0] }}
          </p>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ result?.type === 'entrada' ? 'Entrada registrada' : 'Registro guardado' }} a las {{ resultTime }}
          </p>
          <button
            type="button"
            class="mt-6 w-full rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95"
            @click="resetAll"
          >
            Registrar a otra persona
          </button>
          <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">Esta pantalla se reinicia sola en unos segundos...</p>
        </div>
      </div>
    </main>
  </div>
</template>
