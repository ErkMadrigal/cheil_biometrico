<script setup>
import { useRoute } from 'vue-router'
import Icon from '../components/Icon.vue'

const route = useRoute()
</script>

<template>
  <div class="flex h-[70vh] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white text-center dark:border-slate-700 dark:bg-slate-900">
    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
      <Icon name="clock" class="h-7 w-7" />
    </div>
    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ route.meta.title }} - en construccion</h2>
    <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
      Esta seccion es el siguiente paso del panel. El Dashboard ya esta conectado a la API real.
    </p>
  </div>
</template>
