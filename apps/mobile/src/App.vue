<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  IonApp, IonButton, IonContent, IonHeader, IonInput, IonPage, IonText, IonTitle, IonToolbar,
} from '@ionic/vue'
import { Preferences } from '@capacitor/preferences'
import type { ApiEnvelope, User } from '@loan-monitoring/shared-types'
import { ApiClientError, createApi, mobileAuth } from '@loan-monitoring/api-client'

interface MobileLogin { user: User; token: string }

const email = ref('')
const password = ref('')
const error = ref('')
const profile = ref<User | null>(null)
const loading = ref(true)
const busy = ref(false)
const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1'
const api = createApi(apiUrl, mobileAuth(async () => (await Preferences.get({ key: 'access_token' })).value))

function errorMessage(reason: unknown): string {
  return reason instanceof ApiClientError ? reason.message : 'The request failed. Check your connection and try again.'
}

async function signIn() {
  busy.value = true
  error.value = ''

  try {
    const response = await api.request<ApiEnvelope<MobileLogin>>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email: email.value, password: password.value }),
    })
    await Preferences.set({ key: 'access_token', value: response.data.token })
    profile.value = response.data.user
    password.value = ''
  } catch (reason) {
    error.value = errorMessage(reason)
  } finally {
    busy.value = false
  }
}

async function signOut() {
  busy.value = true
  try {
    await api.request('/auth/logout', { method: 'POST' })
  } catch (reason) {
    error.value = errorMessage(reason)
  } finally {
    await Preferences.remove({ key: 'access_token' })
    profile.value = null
    password.value = ''
    busy.value = false
  }
}

async function restoreSession() {
  try {
    const token = (await Preferences.get({ key: 'access_token' })).value
    if (!token) return

    const response = await api.request<ApiEnvelope<{ user: User }>>('/auth/me')
    profile.value = response.data.user
  } catch (reason) {
    if (reason instanceof ApiClientError && reason.status === 401) {
      await Preferences.remove({ key: 'access_token' })
    } else if (reason instanceof ApiClientError) {
      error.value = reason.message
    }
  } finally {
    loading.value = false
  }
}

onMounted(restoreSession)
</script>

<template>
  <ion-app>
    <ion-page>
      <ion-header><ion-toolbar color="primary"><ion-title>Loan Monitoring</ion-title></ion-toolbar></ion-header>
      <ion-content class="ion-padding">
        <section v-if="loading" class="mobile-card"><p>Restoring your session…</p></section>
        <section v-else-if="!profile" class="mobile-card">
          <h1>Sign in</h1>
          <form @submit.prevent="signIn">
            <ion-input v-model.trim="email" label="Email" label-placement="stacked" type="email" autocomplete="username" required />
            <ion-input v-model="password" label="Password" label-placement="stacked" type="password" autocomplete="current-password" required />
            <ion-text v-if="error" color="danger"><p role="alert">{{ error }}</p></ion-text>
            <ion-button expand="block" type="submit" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</ion-button>
          </form>
        </section>
        <section v-else class="mobile-card">
          <h1>Hello, {{ profile.name }}</h1>
          <p>{{ profile.email }}</p>
          <p>Account status: {{ profile.is_active ? 'Active' : 'Inactive' }}</p>
          <ion-text v-if="error" color="danger"><p role="alert">{{ error }}</p></ion-text>
          <ion-button expand="block" fill="outline" :disabled="busy" @click="signOut">Sign out</ion-button>
        </section>
      </ion-content>
    </ion-page>
  </ion-app>
</template>
