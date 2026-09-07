<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useFaceCamera } from '../composables/useFaceCamera'
import enrollTokenApi from '../api/enrollToken'
import Icon from '../components/Icon.vue'
import Avatar from '../components/ui/Avatar.vue'
import ThemeToggle from '../components/ThemeToggle.vue'

const route = useRoute()
const token = route.params.token

// loading -> invalid -> confirm -> capture -> verify -> success
const step = ref('loading')
const invalidMessage = ref('')

const employee = ref(null)
const expiresAt = ref('')

const videoRef = ref(null)
const canvasRef = ref(null)
const cam = useFaceCamera(videoRef, canvasRef)

const working = ref(false)
const stepError = ref('')
const faceMismatch = ref(false)
const matchScore = ref(null)

function fullName() {
  if (!employee.value) return ''
  return [employee.value.first_name, employee.value.paternal_last_name, employee.value.maternal_last_name]
    .filter(Boolean)
    .join(' ')
}

async function loadToken() {
  step.value = 'loading'
  try {
    employee.value = await enrollTokenApi.lookup(token)
    expiresAt.value = employee.value.expires_at
    step.value = 'confirm'
  } catch (e) {
    invalidMessage.value = e.response?.data?.message || 'Esta liga no es valida.'
    step.value = 'invalid'
  }
}

async function startCapture() {
  step.value = 'capture'
  stepError.value = ''
  faceMismatch.value = false
  await cam.start()
}

async function saveFace() {
  if (!cam.faceDetected.value || working.value) return
  working.value = true
  stepError.value = ''
  try {
    const descriptor = await cam.captureDescriptor()
    if (!descriptor) {
      stepError.value = 'Se perdio el rostro justo al capturar. Intenta de nuevo.'
      cam.resume()
      return
    }
    await enrollTokenApi.saveFace(token, descriptor)
    cam.cleanup()
    step.value = 'verify'
    stepError.value = ''
    await cam.start()
  } catch (e) {
    stepError.value = e.response?.data?.message || 'No se pudo guardar tu rostro. Intenta de nuevo.'
    cam.resume()
  } finally {
    working.value = false
  }
}

async function verifyFace() {
  if (!cam.faceDetected.value || working.value) return
  working.value = true
  stepError.value = ''
  faceMismatch.value = false
  try {
    const descriptor = await cam.captureDescriptor()
    if (!descriptor) {
      stepError.value = 'Se perdio el rostro justo al capturar. Intenta de nuevo.'
      cam.resume()
      return
    }
    const res = await enrollTokenApi.verifyFace(token, descriptor)
    matchScore.value = res?.data?.face_distance ?? null
    cam.cleanup()
    step.value = 'success'
  } catch (e) {
    const isMismatch = e.response?.data?.errors?.face_distance !== undefined
    faceMismatch.value = isMismatch
    stepError.value = isMismatch
      ? 'No pudimos confirmar que eres tu. Intenta con mejor luz, sin lentes oscuros ni cubrebocas.'
      : e.response?.data?.message || 'No se pudo verificar tu rostro. Intenta de nuevo.'
    cam.resume()
  } finally {
    working.value = false
  }
}

function retryFromStart() {
  cam.cleanup()
  stepError.value = ''
  faceMismatch.value = false
  startCapture()
}

onMounted(loadToken)
onBeforeUnmount(() => cam.cleanup())
</script>

<template>
  <div class="relative flex min-h-screen flex-col overflow-hidden bg-slate-50 dark:bg-slate-950">
    <div class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-brand-400/30 blur-3xl dark:bg-brand-600/20" />
    <div class="pointer-events-none absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-fuchsia-400/20 blur-3xl dark:bg-fuchsia-600/10" />

    <header class="relative flex items-center justify-end px-5 py-4 sm:px-8">
      <ThemeToggle />
    </header>

    <main class="relative flex flex-1 items-center justify-center px-4 pb-10">
      <div class="w-full max-w-md">
        <div class="mb-6 flex flex-col items-center text-center">
          <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-xl shadow-brand-500/30">
            <Icon name="faceSmile" class="h-6 w-6" />
          </div>
          <h1 class="text-xl font-bold text-slate-900 dark:text-white">Enrolamiento de rostro</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Liga personal, un solo uso.</p>
        </div>

        <!-- Validando token -->
        <div v-if="step === 'loading'" class="flex flex-col items-center gap-3 rounded-2xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
          <Icon name="spinner" class="h-6 w-6 animate-spin text-slate-400" />
          <p class="text-sm text-slate-500 dark:text-slate-400">Validando tu liga...</p>
        </div>

        <!-- Token invalido/expirado/usado -->
        <div v-else-if="step === 'invalid'" class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
          <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400">
            <Icon name="xMark" class="h-7 w-7" />
          </span>
          <p class="mt-4 font-semibold text-slate-900 dark:text-white">Esta liga ya no sirve</p>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ invalidMessage }}</p>
          <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">Pide a RH que te genere una liga nueva desde el panel.</p>
        </div>

        <!-- Confirmar identidad -->
        <div v-else-if="step === 'confirm'" class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
          <Avatar :photo-path="employee.photo_path" :name="fullName()" size="lg" class="mx-auto" />
          <p class="mt-3 text-lg font-semibold text-slate-900 dark:text-white">{{ fullName() }}</p>
          <p class="text-sm text-slate-400 dark:text-slate-500">#{{ employee.employee_number }}</p>

          <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
            Vamos a capturar tu rostro y luego te pediremos una segunda foto en vivo para confirmar que de verdad eres tu.
          </p>
          <button
            type="button"
            class="mt-5 flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95"
            @click="startCapture"
          >
            <Icon name="camera" class="h-4 w-4" />
            Si, soy yo. Empezar
          </button>
        </div>

        <!-- Captura (paso 1) o verificacion (paso 2) -->
        <div
          v-else-if="step === 'capture' || step === 'verify'"
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900"
        >
          <div class="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
            <span :class="step === 'capture' ? 'text-brand-600 dark:text-brand-400' : ''">1. Captura</span>
            <Icon name="chevronRight" class="h-3.5 w-3.5" />
            <span :class="step === 'verify' ? 'text-brand-600 dark:text-brand-400' : ''">2. Verificacion</span>
          </div>

          <div class="relative mx-auto aspect-square w-full max-w-[280px] overflow-hidden rounded-2xl bg-slate-900">
            <video ref="videoRef" class="h-full w-full -scale-x-100 object-cover" muted playsinline />
            <canvas ref="canvasRef" class="absolute inset-0 h-full w-full -scale-x-100" />

            <div
              v-if="cam.stage.value === 'loadingModels' || cam.stage.value === 'requestingCamera' || working"
              class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-slate-900/80 text-white"
            >
              <Icon name="spinner" class="h-6 w-6 animate-spin" />
              <p class="text-xs">
                {{
                  working
                    ? (step === 'capture' ? 'Guardando...' : 'Verificando...')
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
              {{
                cam.faceDetected.value
                  ? (step === 'capture' ? 'Rostro detectado, listo para capturar' : 'Listo para verificar')
                  : 'Ubica tu rostro en el cuadro'
              }}
            </span>
          </div>

          <div
            v-if="stepError && faceMismatch"
            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-center dark:border-amber-500/30 dark:bg-amber-500/10"
          >
            <p class="text-sm font-semibold text-amber-700 dark:text-amber-300">{{ stepError }}</p>
            <p class="mt-1 text-xs text-amber-600/90 dark:text-amber-400/80">
              Busca un lugar con mas luz, quitate lentes oscuros/cubrebocas y vuelve a intentar.
            </p>
          </div>
          <p
            v-else-if="stepError"
            class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-center text-xs text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400"
          >
            {{ stepError }}
          </p>
          <p v-if="cam.errorMsg.value" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-center text-xs text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">
            {{ cam.errorMsg.value }}
          </p>

          <div class="mt-5 flex gap-3">
            <button
              type="button"
              class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
              @click="retryFromStart"
            >
              Empezar de nuevo
            </button>
            <button
              type="button"
              :disabled="cam.stage.value !== 'scanning' || !cam.faceDetected.value || working"
              class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40"
              @click="step === 'capture' ? saveFace() : verifyFace()"
            >
              <Icon name="camera" class="h-4 w-4" />
              {{ step === 'capture' ? 'Capturar' : 'Verificar' }}
            </button>
          </div>
        </div>

        <!-- Exito -->
        <div v-else-if="step === 'success'" class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
          <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
            <Icon name="check" class="h-8 w-8" />
          </span>
          <p class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">Rostro enrolado y verificado</p>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Listo, {{ fullName() }}. Ya puedes usar el checador biometrico o la app movil.
          </p>
          <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">Ya puedes cerrar esta pagina.</p>
        </div>
      </div>
    </main>
  </div>
</template>
