<script setup>
import { ref } from 'vue'
import employeesApi from '../../api/employees'
import Icon from '../Icon.vue'
import Badge from '../ui/Badge.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'imported'])

const fileInput = ref(null)
const file = ref(null)
const dragOver = ref(false)
const uploading = ref(false)
const downloadingTemplate = ref(false)
const result = ref(null) // { created, updated, failed, total, details: [{row, employee_number, status, message}] }
const errorMsg = ref('')

function pickFile() {
  fileInput.value?.click()
}

function setFile(f) {
  if (!f) return
  file.value = f
  result.value = null
  errorMsg.value = ''
}

function onFileChange(e) {
  setFile(e.target.files?.[0])
}

function onDrop(e) {
  dragOver.value = false
  setFile(e.dataTransfer?.files?.[0])
}

async function downloadTemplate() {
  downloadingTemplate.value = true
  errorMsg.value = ''
  try {
    const blob = await employeesApi.downloadTemplate()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'plantilla_empleados.xlsx'
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch (e) {
    errorMsg.value = 'No se pudo descargar la plantilla.'
  } finally {
    downloadingTemplate.value = false
  }
}

async function upload() {
  if (!file.value || uploading.value) return
  uploading.value = true
  errorMsg.value = ''
  try {
    const data = await employeesApi.importXlsx(file.value)
    result.value = data
    if (data.created || data.updated) emit('imported')
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'No se pudo procesar el archivo. Verifica que sea un .xlsx valido.'
  } finally {
    uploading.value = false
  }
}

function reset() {
  file.value = null
  result.value = null
  errorMsg.value = ''
  if (fileInput.value) fileInput.value.value = ''
}

function close() {
  reset()
  emit('close')
}

function statusColor(status) {
  if (status === 'created') return 'emerald'
  if (status === 'updated') return 'brand'
  return 'red'
}

function statusLabel(status) {
  if (status === 'created') return 'Creado'
  if (status === 'updated') return 'Actualizado'
  return 'Error'
}
</script>

<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="!uploading && close()" />

      <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">Carga masiva de empleados</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500">Sube un archivo .xlsx con la plantilla de empleados</p>
          </div>
          <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="close">
            <Icon name="xMark" class="h-5 w-5" />
          </button>
        </div>

        <div class="scroll-thin flex-1 overflow-y-auto p-5">
          <!-- Paso 1: sin resultado todavia -->
          <template v-if="!result">
            <button
              type="button"
              class="mb-4 flex w-full items-center justify-between gap-2 rounded-xl border border-dashed border-brand-300 bg-brand-50/60 px-4 py-3 text-left text-sm text-brand-700 hover:bg-brand-50 dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-300"
              :disabled="downloadingTemplate"
              @click="downloadTemplate"
            >
              <span class="flex items-center gap-2">
                <Icon name="documentText" class="h-4 w-4" />
                Descargar plantilla .xlsx de ejemplo
              </span>
              <Icon :name="downloadingTemplate ? 'spinner' : 'download'" :class="['h-4 w-4', downloadingTemplate && 'animate-spin']" />
            </button>

            <div
              class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors"
              :class="dragOver ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-700'"
              @dragover.prevent="dragOver = true"
              @dragleave.prevent="dragOver = false"
              @drop.prevent="onDrop"
            >
              <Icon name="upload" class="h-8 w-8 text-slate-300 dark:text-slate-600" />
              <p v-if="!file" class="text-sm text-slate-500 dark:text-slate-400">
                Arrastra tu archivo aqui o
                <button type="button" class="font-semibold text-brand-600 hover:underline dark:text-brand-400" @click="pickFile">elige un archivo</button>
              </p>
              <template v-else>
                <p class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ file.name }}</p>
                <button type="button" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400" @click="pickFile">
                  Cambiar archivo
                </button>
              </template>
              <input ref="fileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="onFileChange" />
            </div>

            <p v-if="errorMsg" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-center text-xs text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">
              {{ errorMsg }}
            </p>

            <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
              Cada fila se procesa por separado: si un numero de empleado ya existe se actualiza, si no, se crea. Un renglon con
              error no detiene el resto de la carga.
            </p>
          </template>

          <!-- Paso 2: resultado -->
          <template v-else>
            <div class="mb-4 grid grid-cols-3 gap-2">
              <div class="rounded-xl bg-emerald-50 px-3 py-3 text-center dark:bg-emerald-500/10">
                <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ result.created }}</p>
                <p class="text-xs text-emerald-600/80 dark:text-emerald-400/80">Creados</p>
              </div>
              <div class="rounded-xl bg-brand-50 px-3 py-3 text-center dark:bg-brand-500/10">
                <p class="text-lg font-bold text-brand-600 dark:text-brand-400">{{ result.updated }}</p>
                <p class="text-xs text-brand-600/80 dark:text-brand-400/80">Actualizados</p>
              </div>
              <div class="rounded-xl bg-red-50 px-3 py-3 text-center dark:bg-red-500/10">
                <p class="text-lg font-bold text-red-600 dark:text-red-400">{{ result.failed }}</p>
                <p class="text-xs text-red-600/80 dark:text-red-400/80">Con error</p>
              </div>
            </div>

            <div class="scroll-thin max-h-64 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800">
              <table class="w-full text-left text-xs">
                <thead class="sticky top-0 bg-slate-50 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                  <tr>
                    <th class="px-3 py-2">Fila</th>
                    <th class="px-3 py-2">Empleado</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Detalle</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="d in result.details" :key="d.row">
                    <td class="px-3 py-2 text-slate-400">{{ d.row }}</td>
                    <td class="px-3 py-2 font-medium text-slate-700 dark:text-slate-200">{{ d.employee_number || '—' }}</td>
                    <td class="px-3 py-2">
                      <Badge :color="statusColor(d.status)">{{ statusLabel(d.status) }}</Badge>
                    </td>
                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ d.message }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
          <template v-if="!result">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="close">
              Cancelar
            </button>
            <button
              type="button"
              :disabled="!file || uploading"
              class="flex items-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40"
              @click="upload"
            >
              <Icon :name="uploading ? 'spinner' : 'upload'" :class="['h-4 w-4', uploading && 'animate-spin']" />
              {{ uploading ? 'Procesando...' : 'Subir e importar' }}
            </button>
          </template>
          <template v-else>
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="reset">
              Subir otro archivo
            </button>
            <button
              type="button"
              class="rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:opacity-95"
              @click="close"
            >
              Listo
            </button>
          </template>
        </div>
      </div>
    </div>
  </teleport>
</template>
