import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiClient from '@/api/client'
import type { ApiResponse, Admin } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('admin_token'))
  const user = ref<Admin | null>(null)

  const isAuthenticated = computed(() => !!token.value)

  async function login(email: string, password: string) {
    const { data } = await apiClient.post<ApiResponse<{ token: string; admin: Admin }>>('/admin/login', { email, password })

    if (data.success && data.data) {
      setToken(data.data.token)
      user.value = data.data.admin
      return true
    }

    throw new Error(data.error?.message || 'Login failed')
  }

  async function fetchProfile() {
    try {
      const { data } = await apiClient.get<ApiResponse<Admin>>('/admin/me')
      if (data.success && data.data) {
        user.value = data.data
      }
    } catch {
      logout()
    }
  }

  function setToken(newToken: string) {
    token.value = newToken
    localStorage.setItem('admin_token', newToken)
  }

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('admin_token')
  }

  return { token, user, isAuthenticated, login, fetchProfile, setToken, logout }
})
