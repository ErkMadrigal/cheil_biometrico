<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import Icon from '../components/Icon.vue'
import ThemeToggle from '../components/ThemeToggle.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('admin@cheil.local')
const password = ref('')
const showPassword = ref(false)

async function onSubmit() {
  const ok = await auth.login(email.value, password.value)
  if (ok) {
    router.push(route.query.redirect || { name: 'dashboard' })
  }
}
</script>

<template>
  <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-4 dark:bg-slate-950">
    <!-- Blobs decorativos -->
    <div class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-brand-400/30 blur-3xl dark:bg-brand-600/20" />
    <div class="pointer-events-none absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-fuchsia-400/20 blur-3xl dark:bg-fuchsia-600/10" />

    <div class="absolute right-5 top-5">
      <ThemeToggle />
    </div>

    <div class="relative w-full max-w-md">
      <div class="mb-8 flex flex-col items-center text-center">
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-xl shadow-brand-500/30">
          <Icon name="faceSmile" class="h-7 w-7" />
        </div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Cheil Biometrico</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Panel administrativo de asistencia</p>
      </div>

      <form
        class="rounded-2xl border border-slate-200 bg-white p-7 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900"
        @submit.prevent="onSubmit"
      >
        <div class="space-y-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Correo</label>
            <input
              v-model="email"
              type="email"
              required
              autocomplete="username"
              placeholder="tu@empresa.mx"
              class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition-shadow placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Contraseña</label>
            <div class="relative">
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 pr-10 text-sm text-slate-900 outline-none transition-shadow placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
              />
              <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                @click="showPassword = !showPassword"
              >
                <Icon :name="showPassword ? 'eyeSlash' : 'eye'" class="h-4 w-4" />
              </button>
            </div>
          </div>

          <p
            v-if="auth.error"
            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400"
          >
            {{ auth.error }}
          </p>

          <button
            type="submit"
            :disabled="auth.loading"
            class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/30 transition-opacity hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <Icon v-if="auth.loading" name="spinner" class="h-4 w-4 animate-spin" />
            {{ auth.loading ? 'Entrando...' : 'Iniciar sesion' }}
          </button>
        </div>
      </form>

      <RouterLink
        :to="{ name: 'kiosk' }"
        class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white/60 px-4 py-3 text-sm font-medium text-slate-600 transition-colors hover:border-brand-400 hover:text-brand-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300 dark:hover:border-brand-500 dark:hover:text-brand-300"
      >
        <Icon name="camera" class="h-4 w-4" />
        ¿Vienes a checar? Registro biometrico
      </RouterLink>

      <p class="mt-6 text-center text-xs text-slate-400 dark:text-slate-600">
        Sistema de registro biometrico &middot; entrada y salida por checador ZKTeco + app movil
      </p>
    </div>
  </div>
</template>
