<script setup>
import { ref, onMounted } from 'vue'
import devicesApi from '../api/devices'
import { useToastStore } from '../stores/toast'
import Icon from '../components/Icon.vue'
import Badge from '../components/ui/Badge.vue'
import DeviceFormDrawer from '../components/devices/DeviceFormDrawer.vue'

const toast = useToastStore()

const items = ref([])
const loading = ref(true)

const drawerOpen = ref(false)
const editingId = ref(null)

const confirmDeleteId = ref(null)
const deletingId = ref(null)

const TYPE_META = {
  fingerprint_entry: { label: 'Huella (entrada)', color: 'sky', icon: 'idCard' },
  face_exit: { label: 'Facial (salida)', color: 'violet', icon: 'faceSmile' },
  other: { label: 'Otro', color: 'slate', icon: 'device' },
}

function typeMeta(type) {
  return TYPE_META[type] || TYPE_META.other
}

function lastSeenLabel(row) {
  if (!row.last_seen_at) return 'Nunca'
  return new Date(row.last_seen_at.replace(' ', 'T')).toLocaleString('es-MX', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function load() {
  loading.value = true
  try {
    items.value = await devicesApi.list()
  } catch (e) {
    toast.error('No se pudo cargar la lista de dispositivos.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  drawerOpen.value = true
}

function openEdit(row) {
  editingId.value = row.id
  drawerOpen.value = true
}

function onSaved(message) {
  drawerOpen.value = false
  toast.success(message)
  load()
}

async function confirmDelete(row) {
  deletingId.value = row.id
  try {
    await devicesApi.remove(row.id)
    toast.success(`${row.name} fue eliminado.`)
    confirmDeleteId.value = null
    load()
  } catch (e) {
    toast.error('No se pudo eliminar el dispositivo.')
  } finally {
    deletingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Dispositivos</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          Checadores ZKTeco dados de alta. Los nuevos se autoregistran (inactivos) al primer handshake ADMS.
        </p>
      </div>
      <button
        type="button"
        class="flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 transition-opacity hover:opacity-95"
        @click="openCreate"
      >
        <Icon name="plus" class="h-4 w-4" />
        Nuevo dispositivo
      </button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-32 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800" />
    </div>

    <div
      v-else-if="!items.length"
      class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center dark:border-slate-800 dark:bg-slate-900"
    >
      <Icon name="device" class="mx-auto mb-3 h-9 w-9 text-slate-300 dark:text-slate-700" />
      <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Aun no hay dispositivos dados de alta.</p>
      <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
        Agrega uno manualmente, o conecta el checador ZKTeco al backend y se autoregistrara aqui.
      </p>
    </div>

    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="row in items"
        :key="row.id"
        class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-center gap-3">
            <div
              class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
              :class="Number(row.is_active) ? 'bg-brand-50 dark:bg-brand-500/10' : 'bg-slate-100 dark:bg-slate-800'"
            >
              <Icon
                :name="typeMeta(row.type).icon"
                class="h-5 w-5"
                :class="Number(row.is_active) ? 'text-brand-600 dark:text-brand-300' : 'text-slate-400 dark:text-slate-500'"
              />
            </div>
            <div class="min-w-0">
              <p class="truncate font-medium text-slate-800 dark:text-slate-100">{{ row.name }}</p>
              <p class="truncate text-xs text-slate-400 dark:text-slate-500">SN {{ row.serial_number }}</p>
            </div>
          </div>
          <Badge :color="Number(row.is_active) ? 'emerald' : 'slate'">
            {{ Number(row.is_active) ? 'Activo' : 'Inactivo' }}
          </Badge>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
          <Badge :color="typeMeta(row.type).color">{{ typeMeta(row.type).label }}</Badge>
        </div>

        <div class="space-y-1 text-xs text-slate-500 dark:text-slate-400">
          <p class="flex items-center gap-1.5">
            <Icon name="mapPin" class="h-3.5 w-3.5 text-slate-300 dark:text-slate-600" />
            {{ row.location_label || 'Sin ubicacion' }}
          </p>
          <p class="flex items-center gap-1.5">
            <Icon name="clock" class="h-3.5 w-3.5 text-slate-300 dark:text-slate-600" />
            Ultima vez: {{ lastSeenLabel(row) }}
          </p>
          <p v-if="row.ip_address" class="flex items-center gap-1.5">
            <Icon name="info" class="h-3.5 w-3.5 text-slate-300 dark:text-slate-600" />
            {{ row.ip_address }}
          </p>
        </div>

        <div class="mt-auto flex items-center justify-end gap-1 border-t border-slate-100 pt-3 dark:border-slate-800">
          <template v-if="confirmDeleteId === row.id">
            <span class="mr-auto text-xs text-slate-500 dark:text-slate-400">¿Eliminar?</span>
            <button
              type="button"
              :disabled="deletingId === row.id"
              class="rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60"
              @click="confirmDelete(row)"
            >
              Si
            </button>
            <button
              type="button"
              class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
              @click="confirmDeleteId = null"
            >
              No
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              title="Editar"
              class="rounded-lg p-2 text-slate-400 hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-300"
              @click="openEdit(row)"
            >
              <Icon name="pencil" class="h-4 w-4" />
            </button>
            <button
              type="button"
              title="Eliminar"
              class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
              @click="confirmDeleteId = row.id"
            >
              <Icon name="trash" class="h-4 w-4" />
            </button>
          </template>
        </div>
      </div>
    </div>

    <DeviceFormDrawer :open="drawerOpen" :device-id="editingId" @close="drawerOpen = false" @saved="onSaved" />
  </div>
</template>
