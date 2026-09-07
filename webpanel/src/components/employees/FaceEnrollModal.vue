<script setup>
import { ref, computed, watch } from 'vue'
import { useFaceCamera } from '../../composables/useFaceCamera'
import employeesApi from '../../api/employees'
import Icon from '../Icon.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  employee: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const videoRef = ref(null)
const canvasRef = ref(null)
const cam = useFaceCamera(videoRef, canvasRef)

const descriptor = ref(null)
const saving = ref(false)
const capturing = ref(false)
const localError = ref('')

// idle -> loadingModels -> requestingCamera -> scanning -> captured -> saving -> error
const stage = computed(() => {
  if (saving.value) return 'saving'
  if (descriptor.value) return 'captured'
  return cam.stage.value
})
const faceDetected = cam.faceDetected
const errorMsg = computed(() => localError.value || cam.errorMsg.value)

function fullName() {
  if (!props.employee) return ''
  return [props.employee.first_name, props.employee.paternal_last_name, props.employee.maternal_last_name]
    .filter(Boolean)
    .join(' ')
}

async function start() {
  localError.value = ''
  descriptor.value = null
  await cam.start()
}

async function capture() {
  if (!faceDetected.value || capturing.value) return
  capturing.value = true
  try {
    const result = await cam.captureDescriptor()
    if (!result) {
      localError.value = 'Se perdio el rostro justo al capturar, intenta de nuevo.'
      cam.resume()
      return
    }
    descriptor.value = result
  } finally {
    capturing.value = false
  }
}

function retry() {
  descriptor.value = null
  localError.value = ''
  cam.resume()
}

async function save() {
  if (!descriptor.value || !props.employee) return
  saving.value = true
  try {
    await employeesApi.saveFace(props.employee.id, descriptor.value)
    cam.cleanup()
    emit('saved')
  } catch (e) {
    localError.value = e.response?.data?.message || 'No se pudo guardar el rostro. Intenta de nuevo.'
  } finally {
    saving.value = false
  }
}

function close() {
  cam.cleanup()
  descriptor.value = null
  emit('close')
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) start()
    else {
      cam.cleanup()
      descriptor.value = null
    }
  },
)
</script>

<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="stage !== 'saving' && close()" />

      <div class="relative w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">Enrolar rostro</h3>
            <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ fullName() }}</p>
          </div>
          <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="close">
            <Icon name="xMark" class="h-5 w-5" />
          </button>
        </div>

        <div class="p-5">
          <!-- Camara / preview -->
          <div class="relative mx-auto aspect-square w-full max-w-[280px] overflow-hidden rounded-2xl bg-slate-900">
            <video
              ref="videoRef"
              class="h-full w-full -scale-x-100 object-cover"
              :class="stage === 'captured' || stage === 'saving' ? 'opacity-40' : 'opacity-100'"
              muted
              playsinline
            />
            <canvas ref="canvasRef" class="absolute inset-0 h-full w-full -scale-x-100" />

            <div
              v-if="stage === 'captured' || stage === 'saving'"
              class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-white"
            >
              <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/90">
                <Icon name="check" class="h-6 w-6" />
              </span>
              <p class="text-sm font-medium">Rostro capturado</p>
            </div>

            <div
              v-if="stage === 'loadingModels' || stage === 'requestingCamera'"
              class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-slate-900/80 text-white"
            >
              <Icon name="spinner" class="h-6 w-6 animate-spin" />
              <p class="text-xs">{{ stage === 'loadingModels' ? 'Cargando modelo facial...' : 'Pidiendo acceso a camara...' }}</p>
            </div>
          </div>

          <!-- Estado -->
          <div class="mt-4 flex items-center justify-center gap-2 text-sm">
            <template v-if="stage === 'scanning'">
              <span
                class="h-2 w-2 rounded-full"
                :class="faceDetected ? 'bg-emerald-500' : 'animate-pulse bg-amber-400'"
              />
              <span class="text-slate-600 dark:text-slate-300">
                {{ faceDetected ? 'Rostro detectado, listo para capturar' : 'Ubica tu rostro dentro del cuadro' }}
              </span>
            </template>
            <template v-else-if="stage === 'captured'">
              <span class="text-slate-600 dark:text-slate-300">¿Se ve bien? Guarda o vuelve a intentar.</span>
            </template>
          </div>

          <p v-if="errorMsg" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-center text-xs text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">
            {{ errorMsg }}
          </p>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
          <template v-if="stage === 'captured'">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="retry">
              Reintentar
            </button>
            <button
              type="button"
              class="flex items-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95"
              @click="save"
            >
              Guardar rostro
            </button>
          </template>
          <template v-else-if="stage === 'saving'">
            <button type="button" disabled class="flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white opacity-70">
              <Icon name="spinner" class="h-4 w-4 animate-spin" />
              Guardando...
            </button>
          </template>
          <template v-else>
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="close">
              Cancelar
            </button>
            <button
              type="button"
              :disabled="stage !== 'scanning' || !faceDetected || capturing"
              class="flex items-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40"
              @click="capture"
            >
              <Icon name="camera" class="h-4 w-4" />
              Capturar
            </button>
          </template>
        </div>
      </div>
    </div>
  </teleport>
</template>
