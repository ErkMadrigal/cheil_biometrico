<script setup>
import { ref, watch } from 'vue'
import employeesApi from '../../api/employees'
import Avatar from '../ui/Avatar.vue'
import Icon from '../Icon.vue'

const props = defineProps({
  modelValue: { type: Object, default: null }, // empleado seleccionado o null ("todos")
  placeholder: { type: String, default: 'Todos los empleados' },
})
const emit = defineEmits(['update:modelValue'])

const query = ref('')
const results = ref([])
const loading = ref(false)
const open = ref(false)

function fullName(e) {
  return [e.first_name, e.paternal_last_name, e.maternal_last_name].filter(Boolean).join(' ')
}

let debounceTimer
watch(query, (q) => {
  clearTimeout(debounceTimer)
  if (!q.trim()) {
    results.value = []
    return
  }
  debounceTimer = setTimeout(async () => {
    loading.value = true
    try {
      const data = await employeesApi.list({ search: q, per_page: 8, status: 'active' })
      results.value = data.items
    } finally {
      loading.value = false
    }
  }, 300)
})

function select(emp) {
  emit('update:modelValue', emp)
  query.value = ''
  results.value = []
  open.value = false
}

function clear() {
  emit('update:modelValue', null)
  query.value = ''
  results.value = []
}

function onFocusOut(e) {
  if (!e.currentTarget.contains(e.relatedTarget)) {
    open.value = false
  }
}
</script>

<template>
  <div class="relative" @focusout="onFocusOut">
    <!-- Chip cuando ya hay un empleado seleccionado -->
    <div
      v-if="modelValue"
      class="flex items-center gap-2 rounded-lg border border-brand-200 bg-brand-50 py-1.5 pl-2 pr-3 dark:border-brand-500/30 dark:bg-brand-500/10"
    >
      <Avatar :photo-path="modelValue.photo_path" :name="fullName(modelValue)" size="sm" />
      <span class="min-w-0 flex-1 truncate text-sm font-medium text-brand-700 dark:text-brand-300">
        {{ fullName(modelValue) }}
      </span>
      <button type="button" class="text-brand-400 hover:text-brand-700 dark:hover:text-brand-200" @click="clear">
        <Icon name="xMark" class="h-4 w-4" />
      </button>
    </div>

    <!-- Buscador -->
    <div v-else class="relative">
      <Icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      <input
        v-model="query"
        type="text"
        :placeholder="placeholder"
        class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
        @focus="open = true"
      />

      <div
        v-if="open && (query.trim() || loading)"
        class="absolute z-20 mt-1.5 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
      >
        <div v-if="loading" class="px-3 py-3 text-center text-xs text-slate-400">Buscando...</div>
        <p v-else-if="!results.length" class="px-3 py-3 text-center text-xs text-slate-400">Sin resultados.</p>
        <button
          v-for="r in results"
          :key="r.id"
          type="button"
          class="flex w-full items-center gap-2.5 px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700/60"
          @click="select(r)"
        >
          <Avatar :photo-path="r.photo_path" :name="fullName(r)" size="sm" />
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ fullName(r) }}</span>
            <span class="block text-xs text-slate-400 dark:text-slate-500">#{{ r.employee_number }}</span>
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
