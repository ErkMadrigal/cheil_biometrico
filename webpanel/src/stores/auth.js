import { defineStore } from 'pinia'
import client from '../api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('cheil-token') || null,
    user: JSON.parse(localStorage.getItem('cheil-user') || 'null'),
    loading: false,
    error: null,
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    initials: (state) => {
      if (!state.user?.name) return '?'
      return state.user.name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join('')
    },
  },
  actions: {
    async login(email, password) {
      this.loading = true
      this.error = null
      try {
        const { data } = await client.post('/auth/login', { email, password })
        this.token = data.token
        this.user = data.user
        localStorage.setItem('cheil-token', data.token)
        localStorage.setItem('cheil-user', JSON.stringify(data.user))
        return true
      } catch (err) {
        this.error = err.response?.data?.message || 'No se pudo iniciar sesion. Intenta de nuevo.'
        return false
      } finally {
        this.loading = false
      }
    },
    logout() {
      this.token = null
      this.user = null
      localStorage.removeItem('cheil-token')
      localStorage.removeItem('cheil-user')
    },
  },
})
