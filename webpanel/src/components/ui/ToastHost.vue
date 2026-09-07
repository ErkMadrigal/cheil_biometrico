<script setup>
import { useToastStore } from '../../stores/toast'
import Icon from '../Icon.vue'

const toast = useToastStore()
</script>

<template>
  <teleport to="body">
    <div class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4 sm:items-end sm:right-4 sm:left-auto">
      <transition-group name="toast">
        <div
          v-for="item in toast.items"
          :key="item.id"
          class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border p-3.5 shadow-lg backdrop-blur"
          :class="
            item.type === 'error'
              ? 'border-red-200 bg-red-50/95 text-red-700 dark:border-red-500/30 dark:bg-red-950/90 dark:text-red-300'
              : 'border-emerald-200 bg-emerald-50/95 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-950/90 dark:text-emerald-300'
          "
        >
          <span
            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full"
            :class="item.type === 'error' ? 'bg-red-500/15' : 'bg-emerald-500/15'"
          >
            <Icon :name="item.type === 'error' ? 'xMark' : 'check'" class="h-3.5 w-3.5" />
          </span>
          <p class="flex-1 text-sm font-medium">{{ item.message }}</p>
          <button type="button" class="text-current/50 hover:text-current" @click="toast.dismiss(item.id)">
            <Icon name="xMark" class="h-4 w-4" />
          </button>
        </div>
      </transition-group>
    </div>
  </teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.25s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(-8px) scale(0.98);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(8px) scale(0.98);
}
</style>
