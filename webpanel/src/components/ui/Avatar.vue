<script setup>
import { ref, computed, watch } from 'vue'
import { resolveMediaUrl } from '../../utils/media'

const props = defineProps({
  photoPath: { type: String, default: null },
  name: { type: String, default: '' },
  size: { type: String, default: 'md' }, // sm | md | lg
})

const sizeMap = {
  sm: 'h-8 w-8 text-xs',
  md: 'h-10 w-10 text-sm',
  lg: 'h-16 w-16 text-lg',
}

const failed = ref(false)
watch(() => props.photoPath, () => (failed.value = false))

const url = computed(() => resolveMediaUrl(props.photoPath))

const initials = computed(() =>
  props.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join('') || '?',
)
</script>

<template>
  <img
    v-if="url && !failed"
    :src="url"
    :alt="name"
    class="shrink-0 rounded-full object-cover ring-1 ring-slate-200 dark:ring-slate-700"
    :class="sizeMap[size]"
    @error="failed = true"
  />
  <span
    v-else
    class="flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-slate-400 to-slate-500 font-bold text-white dark:from-slate-600 dark:to-slate-700"
    :class="sizeMap[size]"
  >
    {{ initials }}
  </span>
</template>
