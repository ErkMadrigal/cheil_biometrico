<script setup>
import { ref, computed, watch } from 'vue'
import employeesApi from '../../api/employees'
import Icon from '../Icon.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  employee: { type: Object, default: null },
})
const emit = defineEmits(['close'])

const loading = ref(false)
const errorMsg = ref('')
const link = ref('')
const expiresAt = ref('')
const copied = ref(false)

const fullUrl = computed(() => (link.value ? window.location.origin + link.value : ''))

const expiresLabel = computed(() => {
  if (!expiresAt.value) return ''
  const d = new Date(expiresAt.value.replace(' ', 'T'))
  return d.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' })
})

function fullName() {
  if (!props.employee) return ''
  return [props.employee.first_name, props.employee.paternal_last_name, props.employee.maternal_last_name]
    .filter(Boolean)
    .join(' ')
}

async function generate() {
  if (!props.employee) return
  loading.value = true
  errorMsg.value = ''
  link.value = ''
  copied.value = false
  try {
    const data = await employeesApi.generateEnrollToken(props.employee.id)
    link.value = data.path
    expiresAt.value = data.expires_at
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'No se pudo generar la liga. Intenta de nuevo.'
  } finally {
    loading.value = false
  }
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(fullUrl.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch (e) {
    errorMsg.value = 'No se pudo copiar automaticamente. Selecciona y copia el texto de forma manual.'
  }
}

function close() {
  emit('close')
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) generate()
    else {
      link.value = ''
      errorMsg.value = ''
    }
  },
)
</script>

<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close" />

      <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">Liga de auto-enrolamiento</h3>
            <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ fullName() }}</p>
          </div>
          <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="close">
            <Icon name="xMark" class="h-5 w-5" />
          </button>
        </div>

        <div class="p-5">
          <div v-if="loading" class="flex flex-col items-center justify-center gap-2 py-8 text-slate-400">
            <Icon name="spinner" class="h-6 w-6 animate-spin" />
            <p class="text-xs">Generando liga...</p>
          </div>

          <template v-else-if="link">
            <p class="mb-3 text-sm text-slate-600 dark:text-slate-300">
              Manda esta liga por WhatsApp o correo. El empleado la abre sin necesitar cuenta ni acceso al panel, captura su
              rostro y lo verifica con una segunda foto en vivo antes de que quede enrolado.
            </p>

            <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800">
              <Icon name="link" class="h-4 w-4 shrink-0 text-slate-400" />
              <input
                type="text"
                readonly
                :value="fullUrl"
                class="flex-1 truncate bg-transparent text-sm text-slate-700 outline-none dark:text-slate-200"
                @click="$event.target.select()"
              />
              <button
                type="button"
                class="shrink-0 rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-brand-600 shadow-sm hover:bg-brand-50 dark:bg-slate-900 dark:text-brand-400 dark:hover:bg-brand-500/10"
                @click="copyLink"
              >
                <span v-if="copied" class="flex items-center gap-1"><Icon name="check" class="h-3.5 w-3.5" /> Copiada</span>
                <span v-else class="flex items-center gap-1"><Icon name="clipboard" class="h-3.5 w-3.5" /> Copiar</span>
              </button>
            </div>

            <p class="mt-3 flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400">
              <Icon name="clock" class="h-3.5 w-3.5" />
              Valida hasta el {{ expiresLabel }} (72 horas), un solo uso.
            </p>

            <button type="button" class="mt-4 text-xs font-medium text-slate-400 hover:text-brand-600 dark:hover:text-brand-400" @click="generate">
              Generar una liga nueva
            </button>
          </template>

          <p v-if="errorMsg" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-center text-xs text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">
            {{ errorMsg }}
          </p>
        </div>

        <div class="flex items-center justify-end border-t border-slate-200 px-5 py-4 dark:border-slate-800">
          <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="close">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>
