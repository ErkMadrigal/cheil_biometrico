<script setup>
import Icon from './Icon.vue'

defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon: { type: String, required: true },
  hint: { type: String, default: '' },
  color: {
    type: String,
    default: 'brand', // brand | emerald | amber | sky
  },
  loading: { type: Boolean, default: false },
})

const colorMap = {
  brand: 'text-brand-600 dark:text-brand-300 bg-brand-50 dark:bg-brand-500/10',
  emerald: 'text-emerald-600 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-500/10',
  amber: 'text-amber-600 dark:text-amber-300 bg-amber-50 dark:bg-amber-500/10',
  sky: 'text-sky-600 dark:text-sky-300 bg-sky-50 dark:bg-sky-500/10',
}
</script>

<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-start justify-between">
      <div class="min-w-0">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ label }}</p>
        <p v-if="loading" class="mt-2 h-8 w-16 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
        <p v-else class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ value }}</p>
        <p v-if="hint && !loading" class="mt-1 truncate text-xs text-slate-400 dark:text-slate-500">{{ hint }}</p>
      </div>
      <div
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
        :class="colorMap[color]"
      >
        <Icon :name="icon" class="h-5 w-5" />
      </div>
    </div>
  </div>
</template>
