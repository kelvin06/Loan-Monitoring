<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import type { AdminDashboardMetrics, ApiEnvelope, DueLoanLedger, UserDashboardMetrics } from '@loan-monitoring/shared-types'
import { ApiClientError } from '@loan-monitoring/api-client'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'
import SortableTable from '../components/SortableTable.vue'

const UserLoansView = defineAsyncComponent(() => import('./UserLoansView.vue'))

const auth = useAuthStore()
const adminMetrics = ref<AdminDashboardMetrics | null>(null)
const userMetrics = ref<UserDashboardMetrics | null>(null)
const loading = ref(true)
const error = ref('')
const search = ref('')
const dueFilter = ref('all')
const sortBy = ref('due_date')
const sortDirection = ref<'asc' | 'desc'>('asc')
const isAdmin = computed(() => auth.user?.role === 'admin')
const sourceRows = computed(() => isAdmin.value ? adminMetrics.value?.due_loans ?? [] : userMetrics.value?.near_due_loans ?? [])
const visibleRows = computed(() => {
  const today = new Date(); today.setHours(0, 0, 0, 0)
  return sourceRows.value
    .filter((row) => `${row.loan_name} ${row.user_name}`.toLowerCase().includes(search.value.trim().toLowerCase()))
    .filter((row) => {
      if (dueFilter.value === 'all') return true
      const overdue = new Date(`${row.due_date}T00:00:00`) < today
      return dueFilter.value === 'overdue' ? overdue : !overdue
    })
    .slice()
    .sort((left, right) => {
      const a = left[sortBy.value as keyof DueLoanLedger]
      const b = right[sortBy.value as keyof DueLoanLedger]
      const result = String(a).localeCompare(String(b), undefined, { numeric: true })
      return sortDirection.value === 'asc' ? result : -result
    })
})
const dueColumns = [
  { key: 'loan_name', label: 'Loan name' },
  { key: 'user_name', label: 'User name' },
  { key: 'amount', label: 'Amount' },
  { key: 'due_date', label: 'Due date' },
]

function currency(value: string): string {
  return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })
}

function sortTable(key: string) {
  if (sortBy.value === key) sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  else { sortBy.value = key; sortDirection.value = 'asc' }
}

onMounted(async () => {
  try {
    if (isAdmin.value) {
      adminMetrics.value = (await api.request<ApiEnvelope<AdminDashboardMetrics>>('/admin/dashboard')).data
    } else {
      userMetrics.value = (await api.request<ApiEnvelope<UserDashboardMetrics>>('/user/dashboard')).data
    }
  } catch (reason) {
    error.value = reason instanceof ApiClientError ? reason.message : 'Could not load the dashboard.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page-layout admin-page">
    <section class="page-heading">
      <div><p class="eyebrow">{{ isAdmin ? 'ADMINISTRATION' : 'YOUR ACCOUNT' }}</p>
        <h1>Welcome, {{ auth.user?.name }}</h1><p class="muted">{{ auth.user?.email }}</p></div>
    </section>

    <p v-if="loading" class="notice">Loading your dashboard…</p>
    <p v-else-if="error" class="error notice" role="alert">{{ error }}</p>
    <template v-else-if="isAdmin && adminMetrics">
      <section class="metrics-grid dashboard-metrics" aria-label="Admin metrics">
        <article><strong>{{ adminMetrics.total_users }}</strong><span>Users</span></article>
        <article><strong>{{ adminMetrics.active_loans }}</strong><span>Active loans</span></article>
      </section>
      <section class="card admin-table-card dashboard-due">
        <div class="admin-toolbar"><div><p class="eyebrow">PAYMENT FOLLOW-UP</p><h2>Loans due</h2></div>
          <div class="admin-search"><input v-model="search" type="search" aria-label="Search due loans" placeholder="Search loan or user">
            <select v-model="dueFilter" aria-label="Filter due loans"><option value="all">All due</option><option value="upcoming">Upcoming</option><option value="overdue">Overdue</option></select></div>
        </div>
        <SortableTable :columns="dueColumns" :rows="visibleRows" :sort-by="sortBy" :sort-direction="sortDirection" empty-text="No unpaid loans are due in the next three days." @sort="sortTable">
          <template #cell-amount="{ row }">{{ currency(String(row.amount)) }}</template>
        </SortableTable>
      </section>
    </template>
    <template v-else-if="userMetrics">
      <section class="metrics-grid dashboard-metrics" aria-label="User metrics">
        <article><strong>{{ userMetrics.active_loans }}</strong><span>Active loans</span></article>
      </section>
      <section class="card admin-table-card dashboard-due">
        <div class="admin-toolbar"><div><p class="eyebrow">UPCOMING PAYMENTS</p><h2>Near due date</h2><p class="muted">Unpaid installments overdue or due within three days.</p></div>
          <input v-model="search" class="dashboard-search" type="search" aria-label="Search upcoming payments" placeholder="Search loan"></div>
        <SortableTable :columns="dueColumns" :rows="visibleRows" :sort-by="sortBy" :sort-direction="sortDirection" empty-text="No payments are due within the next three days." @sort="sortTable">
          <template #cell-amount="{ row }">{{ currency(String(row.amount)) }}</template>
        </SortableTable>
      </section>
      <UserLoansView :embedded="true" />
    </template>
  </div>
</template>
