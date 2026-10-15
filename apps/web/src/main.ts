import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import type { Role } from '@loan-monitoring/shared-types'
import App from './App.vue'
import { useAuthStore } from './stores/auth'
import './style.css'
import './admin.css'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    role?: Role
    catalog?: 'loan-categories' | 'down-payment-rates' | 'loan-interest-rates' | 'penalties'
  }
}

const routes = [
  { path: '/login', name: 'user-login', component: () => import('./views/LoginView.vue'), meta: { role: 'user' as const } },
  { path: '/admin/login', name: 'admin-login', component: () => import('./views/LoginView.vue'), meta: { role: 'admin' as const } },
  { path: '/dashboard', name: 'user-dashboard', component: () => import('./views/DashboardView.vue'), meta: { requiresAuth: true, role: 'user' as const } },
  { path: '/my-loans', name: 'user-loans', component: () => import('./views/UserLoansView.vue'), meta: { requiresAuth: true, role: 'user' as const } },
  { path: '/admin/dashboard', name: 'admin-dashboard', component: () => import('./views/DashboardView.vue'), meta: { requiresAuth: true, role: 'admin' as const } },
  { path: '/admin/users', name: 'admin-users', component: () => import('./views/UserMaintenanceView.vue'), meta: { requiresAuth: true, role: 'admin' as const } },
  { path: '/admin/loan-categories', name: 'admin-loan-categories', component: () => import('./views/CatalogMaintenanceView.vue'), meta: { requiresAuth: true, role: 'admin' as const, catalog: 'loan-categories' as const } },
  { path: '/admin/down-payment-rates', name: 'admin-down-payment-rates', component: () => import('./views/CatalogMaintenanceView.vue'), meta: { requiresAuth: true, role: 'admin' as const, catalog: 'down-payment-rates' as const } },
  { path: '/admin/loan-interest-rates', name: 'admin-loan-interest-rates', component: () => import('./views/CatalogMaintenanceView.vue'), meta: { requiresAuth: true, role: 'admin' as const, catalog: 'loan-interest-rates' as const } },
  { path: '/admin/penalties', name: 'admin-penalties', component: () => import('./views/CatalogMaintenanceView.vue'), meta: { requiresAuth: true, role: 'admin' as const, catalog: 'penalties' as const } },
  { path: '/admin/loans', name: 'admin-loans', component: () => import('./views/LoanMaintenanceView.vue'), meta: { requiresAuth: true, role: 'admin' as const } },
  { path: '/', redirect: '/login' },
  { path: '/:pathMatch(.*)*', redirect: '/login' },
]

const router = createRouter({ history: createWebHistory(), routes })
const pinia = createPinia()
const auth = useAuthStore(pinia)

router.beforeEach(async (to) => {
  if (!to.meta.requiresAuth) return true

  const user = await auth.loadUser()
  if (!user) return to.meta.role === 'admin' ? '/admin/login' : '/login'
  if (to.meta.role && user.role !== to.meta.role) {
    return user.role === 'admin' ? '/admin/dashboard' : '/dashboard'
  }

  return true
})

createApp(App).use(pinia).use(router).mount('#app')
