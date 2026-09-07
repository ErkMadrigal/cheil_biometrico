<script setup>
import { useThemeStore } from '../stores/theme'
import Icon from './Icon.vue'

const theme = useThemeStore()
</script>

<template>
  <button
    type="button"
    class="relative inline-flex h-9 w-16 items-center rounded-full border border-slate-200 bg-slate-100 px-1 transition-colors dark:border-slate-700 dark:bg-slate-800"
    :aria-pressed="theme.dark"
    aria-label="Cambiar tema claro/oscuro"
    @click="theme.toggle()"
  >
    <span
      class="flex h-7 w-7 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-black/5 transition-transform duration-200 dark:bg-slate-950"
      :class="theme.dark ? 'translate-x-7' : 'translate-x-0'"
    >
      <Icon
        :name="theme.dark ? 'moon' : 'sun'"
        class="h-4 w-4"
        :class="theme.dark ? 'text-brand-300' : 'text-amber-500'"
        filled
      />
    </span>
  </button>
</template>
