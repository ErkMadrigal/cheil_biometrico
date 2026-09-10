<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import Icon from '../components/Icon.vue'
import ThemeToggle from '../components/ThemeToggle.vue'
import ToastHost from '../components/ui/ToastHost.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const sidebarOpen = ref(false)
const userMenuOpen = ref(false)

const nav = [
  { name: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
  { name: 'empleados', label: 'Empleados', icon: 'users' },
  { name: 'asistencia', label: 'Asistencia', icon: 'mapPin' },
  { name: 'mapa', label: 'Mapa en vivo', icon: 'map' },
  { name: 'reportes', label: 'Reportes', icon: 'chart' },
  { name: 'reportes-exportacion', label: 'Exportar reporte', icon: 'documentText' },
  { name: 'dispositivos', label: 'Dispositivos', icon: 'device' },
  { name: 'zonas', label: 'Zonas geograficas', icon: 'mapPin' },
  { name: 'movilidad', label: 'Movilidad', icon: 'compass' },
  { name: 'departamentos', label: 'Departamentos', icon: 'building' },
  { name: 'incidencias', label: 'Incidencias', icon: 'alertTriangle' },
]

function logout() {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950">
    <!-- Overlay movil -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-200 dark:border-slate-800 dark:bg-slate-900 lg:static lg:translate-x-0"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex h-16 items-center gap-2.5 border-b border-slate-200 px-5 dark:border-slate-800">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg shadow-brand-500/30">
          <Icon name="faceSmile" class="h-5 w-5" />
        </div>
        <div class="leading-tight">
          <p class="text-sm font-bold text-slate-900 dark:text-white">Cheil Biometrico</p>
          <p class="text-xs text-slate-400 dark:text-slate-500">Panel administrativo</p>
        </div>
      </div>

      <nav class="scroll-thin flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">
          General
        </p>
        <RouterLink
          v-for="item in nav"
          :key="item.name"
          :to="{ name: item.name }"
          class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
          :class="
            route.name === item.name
              ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'
          "
          @click="sidebarOpen = false"
        >
          <Icon
            :name="item.icon"
            class="h-5 w-5"
            :class="route.name === item.name ? 'text-brand-600 dark:text-brand-300' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300'"
          />
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="border-t border-slate-200 p-3 dark:border-slate-800">
        <button
          type="button"
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-red-50 hover:text-red-600 dark:text-slate-400 dark:hover:bg-red-500/10 dark:hover:text-red-400"
          @click="logout"
        >
          <Icon name="logout" class="h-5 w-5" />
          Cerrar sesion
        </button>
      </div>
    </aside>

    <!-- Contenido -->
    <div class="flex flex-1 flex-col overflow-hidden">
      <header class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white/80 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80 sm:px-6">
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 lg:hidden"
            @click="sidebarOpen = true"
          >
            <Icon name="menu" class="h-5 w-5" />
          </button>
          <h1 class="text-lg font-semibold text-slate-900 dark:text-white">
            {{ route.meta.title || 'Dashboard' }}
          </h1>
        </div>

        <div class="flex items-center gap-3 sm:gap-4">
          <ThemeToggle />

          <div class="relative">
            <button
              type="button"
              class="flex items-center gap-2.5 rounded-full py-1 pl-1 pr-2.5 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
              @click="userMenuOpen = !userMenuOpen"
            >
              <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-xs font-bold text-white">
                {{ auth.initials }}
              </span>
              <span class="hidden text-left sm:block">
                <span class="block text-sm font-medium leading-tight text-slate-800 dark:text-slate-100">
                  {{ auth.user?.name }}
                </span>
                <span class="block text-xs capitalize leading-tight text-slate-400 dark:text-slate-500">
                  {{ auth.user?.role }}
                </span>
              </span>
              <Icon name="chevronDown" class="hidden h-4 w-4 text-slate-400 sm:block" />
            </button>

            <div
              v-if="userMenuOpen"
              class="absolute right-0 mt-2 w-44 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-800"
              @click="userMenuOpen = false"
            >
              <button
                type="button"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                @click="logout"
              >
                <Icon name="logout" class="h-4 w-4" />
                Cerrar sesion
              </button>
            </div>
          </div>
        </div>
      </header>

      <main class="scroll-thin flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
        <RouterView />
      </main>
    </div>

    <ToastHost />
  </div>
</template>
