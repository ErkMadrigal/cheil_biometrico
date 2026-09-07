<script setup>
import { ref, computed, watch } from 'vue'
import attendanceApi from '../../api/attendance'
import { resolveMediaUrl } from '../../utils/media'
import Icon from '../Icon.vue'
import Badge from '../ui/Badge.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  employeeId: { type: [Number, String], default: null },
  employeeName: { type: String, default: '' },
  date: { type: String, default: '' }, // YYYY-MM-DD
})
const emit = defineEmits(['close'])

const loading = ref(true)
const rows = ref([])
const errorMsg = ref('')

async function load() {
  if (!props.employeeId || !props.date) return
  loading.value = true
  errorMsg.value = ''
  try {
    rows.value = await attendanceApi.dayDetail(props.employeeId, props.date)
  } catch (e) {
    errorMsg.value = 'No se pudo cargar el detalle del dia.'
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.open, props.employeeId, props.date],
  ([isOpen]) => {
    if (isOpen) load()
  },
)

const dateLabel = computed(() => {
  if (!props.date) return ''
  return new Date(props.date + 'T00:00:00').toLocaleDateString('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
})

function timeLabel(dt) {
  return new Date(dt.replace(' ', 'T')).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
}

const typeMeta = {
  entrada: { label: 'Entrada', dot: 'bg-emerald-500', ring: 'ring-emerald-100 dark:ring-emerald-500/20' },
  salida: { label: 'Salida', dot: 'bg-violet-500', ring: 'ring-violet-100 dark:ring-violet-500/20' },
  checkpoint: { label: 'Visita', dot: 'bg-brand-500', ring: 'ring-brand-100 dark:ring-brand-500/20' },
}

function sourceLabel(row) {
  if (row.source_type === 'zkteco_device') return row.verify_mode === 'fingerprint' ? 'Checador · Huella' : 'Checador · Facial'
  if (row.source_type === 'web_kiosk') return 'Registro biometrico'
  return 'App movil'
}

function mapsUrl(row) {
  if (!row.latitude || !row.longitude) return null
  return `https://www.google.com/maps?q=${row.latitude},${row.longitude}`
}
</script>

<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="emit('close')" />

      <aside class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">{{ employeeName }}</h3>
            <p class="text-xs capitalize text-slate-400 dark:text-slate-500">{{ dateLabel }}</p>
          </div>
          <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="emit('close')">
            <Icon name="xMark" class="h-5 w-5" />
          </button>
        </div>

        <div class="scroll-thin flex-1 overflow-y-auto px-6 py-5">
          <div v-if="loading" class="space-y-4">
            <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
          </div>

          <p v-else-if="errorMsg" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">
            {{ errorMsg }}
          </p>

          <p v-else-if="!rows.length" class="py-10 text-center text-sm text-slate-400 dark:text-slate-500">
            No hay registros para este dia.
          </p>

          <ol v-else class="relative space-y-6 border-l-2 border-slate-100 pl-6 dark:border-slate-800">
            <li v-for="row in rows" :key="row.id" class="relative">
              <span
                class="absolute -left-[29px] top-0.5 flex h-4 w-4 items-center justify-center rounded-full ring-4"
                :class="[typeMeta[row.type]?.dot, typeMeta[row.type]?.ring]"
              />

              <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                  {{ typeMeta[row.type]?.label }}
                  <span class="ml-1.5 font-normal text-slate-400 dark:text-slate-500">{{ timeLabel(row.recorded_at) }}</span>
                </p>
              </div>

              <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ row.location_label || 'Sin ubicacion registrada' }}
              </p>

              <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <Badge color="slate">{{ sourceLabel(row) }}</Badge>
                <a
                  v-if="mapsUrl(row)"
                  :href="mapsUrl(row)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline dark:text-brand-300"
                >
                  Ver en mapa
                  <Icon name="arrowUpRight" class="h-3 w-3" />
                </a>
                <img
                  v-if="row.photo_path"
                  :src="resolveMediaUrl(row.photo_path)"
                  class="h-7 w-7 rounded-md object-cover ring-1 ring-slate-200 dark:ring-slate-700"
                  alt="Selfie de la checada"
                />
              </div>
            </li>
          </ol>
        </div>
      </aside>
    </div>
  </teleport>
</template>
