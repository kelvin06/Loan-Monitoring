<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function signOut() {
  const role = auth.user?.role
  try {
    await auth.logout()
  } finally {
    await router.replace(role === 'admin' ? '/admin/login' : '/login')
  }
}
</script>

<template>
  <div class="app-shell">
    <div v-if="auth.user?.role === 'admin'" class="admin-shell">
      <aside class="admin-sidebar">
        <RouterLink class="admin-brand" to="/admin/dashboard">
          <span class="admin-brand-mark">L</span>
          <span><strong>Loan Monitor</strong><small>ADMIN WORKSPACE</small></span>
        </RouterLink>
        <p class="sidebar-label">WORKSPACE</p>
        <nav class="admin-nav" aria-label="Admin navigation">
          <RouterLink to="/admin/dashboard" class="admin-nav-link"><span class="nav-icon">⌂</span>Dashboard</RouterLink>
          <p class="sidebar-label sidebar-label--section">MANAGEMENT</p>
          <RouterLink to="/admin/users" class="admin-nav-link"><span class="nav-icon">♙</span>Users</RouterLink>
          <RouterLink to="/admin/loan-categories" class="admin-nav-link"><span class="nav-icon">▦</span>Loan Category</RouterLink>
          <RouterLink to="/admin/down-payment-rates" class="admin-nav-link"><span class="nav-icon">％</span>Down Payment Rate</RouterLink>
          <RouterLink to="/admin/loan-interest-rates" class="admin-nav-link"><span class="nav-icon">↗</span>Loan Interest Rate</RouterLink>
          <RouterLink to="/admin/penalties" class="admin-nav-link"><span class="nav-icon">!</span>Penalty</RouterLink>
          <RouterLink to="/admin/loans" class="admin-nav-link"><span class="nav-icon">▤</span>Loans</RouterLink>
        </nav>
        <div class="sidebar-account">
          <div class="account-avatar">{{ auth.user.name.slice(0, 1).toUpperCase() }}</div>
          <div class="account-copy"><strong>{{ auth.user.name }}</strong><span>Administrator</span></div>
          <button class="sidebar-signout" type="button" aria-label="Sign out" title="Sign out" @click="signOut">↗</button>
        </div>
      </aside>
      <main class="admin-content"><RouterView /></main>
    </div>

    <template v-else>
    <header v-if="auth.user" class="topbar">
      <RouterLink class="brand" to="/dashboard">
        Loan Monitoring
      </RouterLink>
      <nav aria-label="Main navigation">
        <RouterLink to="/dashboard">Dashboard</RouterLink>
        <RouterLink to="/my-loans">My loans</RouterLink>
        <button class="link-button" type="button" @click="signOut">Sign out</button>
      </nav>
    </header>
    <RouterView />
    </template>
  </div>
</template>
