<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import type { ApiEnvelope, PaginatedResponse, Role, User } from '@loan-monitoring/shared-types'
import { ApiClientError } from '@loan-monitoring/api-client'
import { api } from '../api'
import ModalDialog from '../components/ModalDialog.vue'

const users = ref<User[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const search = ref('')
const roleFilter = ref('')
const statusFilter = ref('')
const sortBy = ref('created_at')
const sortDirection = ref<'asc' | 'desc'>('desc')
const showForm = ref(false)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const editingId = ref<number | null>(null)
const form = reactive({ name: '', email: '', password: '', role: 'user' as Role })
let filterTimer: ReturnType<typeof setTimeout> | undefined

async function loadUsers() {
  busy.value = true
  error.value = ''
  try {
    const query = new URLSearchParams({ page: String(page.value), sort: sortBy.value, direction: sortDirection.value })
    if (search.value.trim()) query.set('search', search.value.trim())
    if (roleFilter.value) query.set('role', roleFilter.value)
    if (statusFilter.value) query.set('status', statusFilter.value)
    const response = await api.request<PaginatedResponse<User>>(`/admin/users?${query}`)
    users.value = response.data
    lastPage.value = response.meta.last_page
    total.value = response.meta.total
  } catch (reason) {
    error.value = reason instanceof ApiClientError ? reason.message : 'Could not load users.'
  } finally {
    busy.value = false
  }
}

function sortUsers(key: string) {
  sortDirection.value = sortBy.value === key && sortDirection.value === 'asc' ? 'desc' : 'asc'
  sortBy.value = key
  page.value = 1
  void loadUsers()
}

function resetForm() {
  editingId.value = null
  Object.assign(form, { name: '', email: '', password: '', role: 'user' })
  showForm.value = false
}

function newUser() {
  resetForm()
  showForm.value = true
}

function editUser(user: User) {
  editingId.value = user.id
  Object.assign(form, { name: user.name, email: user.email, password: '', role: user.role })
  showForm.value = true
}

async function saveUser() {
  error.value = ''
  notice.value = ''
  const payload: Record<string, string> = { name: form.name, email: form.email, role: form.role }
  if (!editingId.value || form.password) payload.password = form.password
  try {
    const path = editingId.value ? `/admin/users/${editingId.value}` : '/admin/users'
    const method = editingId.value ? 'PUT' : 'POST'
    await api.request<ApiEnvelope<User>>(path, { method, body: JSON.stringify(payload) })
    notice.value = editingId.value ? 'User updated.' : 'User created.'
    resetForm()
    await loadUsers()
  } catch (reason) {
    error.value = reason instanceof ApiClientError ? reason.message : 'Could not save this user.'
  }
}

async function toggleStatus(user: User) {
  error.value = ''
  try {
    await api.request<ApiEnvelope<User>>(`/admin/users/${user.id}/status`, {
      method: 'PATCH', body: JSON.stringify({ is_active: !user.is_active }),
    })
    await loadUsers()
  } catch (reason) {
    error.value = reason instanceof ApiClientError ? reason.message : 'Could not change account status.'
  }
}

async function deleteUser(user: User) {
  if (!window.confirm(`Delete ${user.name}? This cannot be undone.`)) return
  error.value = ''
  try {
    await api.request<void>(`/admin/users/${user.id}`, { method: 'DELETE' })
    notice.value = 'User deleted.'
    await loadUsers()
  } catch (reason) {
    error.value = reason instanceof ApiClientError ? reason.message : 'Could not delete this user.'
  }
}

watch([search, roleFilter, statusFilter], () => {
  clearTimeout(filterTimer)
  filterTimer = setTimeout(() => {
    page.value = 1
    void loadUsers()
  }, 250)
})

onMounted(loadUsers)
onBeforeUnmount(() => clearTimeout(filterTimer))
</script>

<template>
  <main class="page-layout admin-page">
    <section class="page-heading"><div><p class="eyebrow">ADMINISTRATION</p><h1>Users</h1><p class="muted">Manage {{ total }} accounts and access.</p></div><button class="admin-primary" type="button" @click="newUser">＋ New user</button></section>
    <p v-if="notice" class="success notice" role="status">{{ notice }}</p>
    <p v-if="error" class="error notice" role="alert">{{ error }}</p>
    <ModalDialog v-model:open="showForm" :title="editingId ? 'Edit user' : 'Create user'">
    <section v-if="showForm" class="card form-card admin-form-card">
      <h2>{{ editingId ? 'Edit user' : 'Create user' }}</h2>
      <form class="user-form" @submit.prevent="saveUser">
        <p v-if="error" class="error form-notice" role="alert">{{ error }}</p>
        <label>Name<input v-model.trim="form.name" required maxlength="255"></label>
        <label>Email<input v-model.trim="form.email" type="email" required maxlength="255"></label>
        <label>Password<input v-model="form.password" type="password" :required="!editingId" :minlength="12" autocomplete="new-password" :placeholder="editingId ? 'Leave blank to keep current' : 'At least 12 characters'"></label>
        <label>Role<select v-model="form.role"><option value="user">User</option><option value="admin">Admin</option></select></label>
        <div class="form-actions"><button type="submit">{{ editingId ? 'Save changes' : 'Create user' }}</button><button v-if="editingId" class="secondary" type="button" @click="resetForm">Cancel</button></div>
      </form>
    </section>
    </ModalDialog>
    <section class="card table-card admin-table-card">
      <div class="filters admin-toolbar--filters">
        <label>Search<input v-model="search" placeholder="Name or email"></label>
        <label>Role<select v-model="roleFilter"><option value="">All roles</option><option value="admin">Admin</option><option value="user">User</option></select></label>
        <label>Status<select v-model="statusFilter"><option value="">All statuses</option><option value="1">Active</option><option value="0">Inactive</option></select></label>
      </div>
      <p v-if="busy" class="muted">Loading users…</p>
      <div v-else class="table-scroll"><table><thead><tr>
        <th><button class="table-sort" type="button" @click="sortUsers('name')">Name <span>{{ sortBy === 'name' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
        <th><button class="table-sort" type="button" @click="sortUsers('email')">Email <span>{{ sortBy === 'email' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
        <th><button class="table-sort" type="button" @click="sortUsers('role')">Role <span>{{ sortBy === 'role' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
        <th><button class="table-sort" type="button" @click="sortUsers('is_active')">Status <span>{{ sortBy === 'is_active' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th><th>Actions</th></tr></thead>
        <tbody><tr v-for="user in users" :key="user.id"><td>{{ user.name }}</td><td>{{ user.email }}</td><td>{{ user.role }}</td><td>{{ user.is_active ? 'Active' : 'Inactive' }}</td><td class="row-actions"><button class="icon-action" type="button" :aria-label="`Edit ${user.name}`" title="Edit" @click="editUser(user)">✎</button><button class="icon-action" type="button" :aria-label="user.is_active ? `Deactivate ${user.name}` : `Activate ${user.name}`" :title="user.is_active ? 'Deactivate' : 'Activate'" @click="toggleStatus(user)">{{ user.is_active ? '⏸' : '▶' }}</button><button class="icon-action icon-action--danger" type="button" :aria-label="`Delete ${user.name}`" title="Delete" @click="deleteUser(user)"><svg aria-hidden="true" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2m-11 0 1 15h12l1-15M10 10v7m4-7v7" /></svg></button></td></tr>
        <tr v-if="!users.length"><td colspan="5" class="empty">No users match these filters.</td></tr></tbody></table></div>
      <footer class="pagination"><span>Page {{ page }} of {{ lastPage }}</span><div><button class="secondary" type="button" :disabled="page <= 1" @click="page--; loadUsers()">Previous</button><button class="secondary" type="button" :disabled="page >= lastPage" @click="page++; loadUsers()">Next</button></div></footer>
    </section>
  </main>
</template>
