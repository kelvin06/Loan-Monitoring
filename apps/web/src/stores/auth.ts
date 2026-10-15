import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { ApiEnvelope, Credentials, Role, User } from '@loan-monitoring/shared-types'
import { api } from '../api'

interface LoginResponse {
  user: User
  token?: string
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loaded = ref(false)

  async function loadUser(): Promise<User | null> {
    if (loaded.value) return user.value

    try {
      const response = await api.request<ApiEnvelope<{ user: User }>>('/auth/me')
      user.value = response.data.user
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }

    return user.value
  }

  async function login(role: Role, credentials: Credentials): Promise<User> {
    const endpoint = role === 'admin' ? '/auth/admin/login' : '/auth/login'
    const response = await api.request<ApiEnvelope<LoginResponse>>(endpoint, {
      method: 'POST',
      body: JSON.stringify(credentials),
    })
    user.value = response.data.user
    loaded.value = true
    return response.data.user
  }

  async function logout(): Promise<void> {
    try {
      await api.request('/auth/logout', { method: 'POST' })
    } finally {
      user.value = null
      loaded.value = true
    }
  }

  return { user, loaded, loadUser, login, logout }
})
