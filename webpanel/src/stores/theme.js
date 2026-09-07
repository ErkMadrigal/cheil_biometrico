import { defineStore } from 'pinia'

const STORAGE_KEY = 'cheil-theme'

export const useThemeStore = defineStore('theme', {
  state: () => ({
    dark: false,
  }),
  actions: {
    /**
     * Se llama una vez al arrancar la app. index.html ya aplico la clase "dark"
     * al <html> para evitar el flash; aqui solo sincronizamos el estado de Pinia.
     */
    init() {
      this.dark = document.documentElement.classList.contains('dark')
    },
    toggle() {
      this.set(!this.dark)
    },
    set(isDark) {
      this.dark = isDark
      document.documentElement.classList.toggle('dark', isDark)
      localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light')
    },
  },
})
