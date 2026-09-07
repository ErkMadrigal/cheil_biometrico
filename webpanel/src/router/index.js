import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
    meta: { public: true },
  },
  {
    path: '/registro-biometrico',
    name: 'kiosk',
    component: () => import('../views/KioskView.vue'),
    meta: { public: true },
  },
  {
    path: '/enrolar/:token',
    name: 'enroll-token',
    component: () => import('../views/EnrollTokenView.vue'),
    meta: { public: true },
  },
  {
    path: '/',
    component: () => import('../layouts/AppLayout.vue'),
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('../views/DashboardView.vue'),
        meta: { title: 'Dashboard' },
      },
      {
        path: 'empleados',
        name: 'empleados',
        component: () => import('../views/EmployeesView.vue'),
        meta: { title: 'Empleados' },
      },
      {
        path: 'asistencia',
        name: 'asistencia',
        component: () => import('../views/AttendanceView.vue'),
        meta: { title: 'Asistencia' },
      },
      {
        path: 'mapa',
        name: 'mapa',
        component: () => import('../views/LocationsView.vue'),
        meta: { title: 'Mapa en vivo' },
      },
      {
        path: 'reportes',
        name: 'reportes',
        component: () => import('../views/ReportsView.vue'),
        meta: { title: 'Reportes' },
      },
      {
        path: 'dispositivos',
        name: 'dispositivos',
        component: () => import('../views/DevicesView.vue'),
        meta: { title: 'Dispositivos' },
      },
      {
        path: 'zonas',
        name: 'zonas',
        component: () => import('../views/ZonesView.vue'),
        meta: { title: 'Zonas geograficas' },
      },
      {
        path: 'reportes-exportacion',
        name: 'reportes-exportacion',
        component: () => import('../views/ExportReportView.vue'),
        meta: { title: 'Reporte de exportacion' },
      },
      {
        path: 'movilidad',
        name: 'movilidad',
        component: () => import('../views/MobilityView.vue'),
        meta: { title: 'Movilidad' },
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (!to.meta.public && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
  return true
})

export default router
