<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import type { ApiEnvelope, CatalogRecord, PaginatedResponse } from '@loan-monitoring/shared-types'
import { ApiClientError } from '@loan-monitoring/api-client'
import { api } from '../api'
import ModalDialog from '../components/ModalDialog.vue'

type CatalogKind = 'loan-categories' | 'down-payment-rates' | 'loan-interest-rates' | 'penalties'
const route = useRoute()
const kind = computed(() => route.meta.catalog as CatalogKind)
const titles: Record<CatalogKind, string> = {
  'loan-categories': 'Loan categories',
  'down-payment-rates': 'Down payment rates',
  'loan-interest-rates': 'Loan interest rates',
  penalties: 'Penalties',
}
const title = computed(() => titles[kind.value])
const items = ref<CatalogRecord[]>([])
const page = ref(1)
const sortBy = ref('name')
const sortDirection = ref<'asc' | 'desc'>('asc')
const lastPage = ref(1)
const total = ref(0)
const search = ref('')
const loading = ref(false)
const showForm = ref(false)
const editingId = ref<number | null>(null)
const error = ref('')
const notice = ref('')
const form = reactive({ name: '', rate: '', penalty_option: 'PER DAY' as 'PER DAY' | 'PER MONTH', description: '' })
let latestLoad = 0
let searchTimer: ReturnType<typeof setTimeout> | undefined

function message(reason: unknown, fallback: string): string {
  return reason instanceof ApiClientError ? reason.message : fallback
}

async function loadItems() {
  const loadId = ++latestLoad
  const requestedKind = kind.value
  const requestedTitle = title.value
  loading.value = true
  error.value = ''
  try {
    const query = new URLSearchParams({ page: String(page.value), sort: sortBy.value, direction: sortDirection.value })
    if (search.value.trim()) query.set('search', search.value.trim())
    const response = await api.request<PaginatedResponse<CatalogRecord>>(`/admin/catalog/${requestedKind}?${query}`)
    if (loadId !== latestLoad || requestedKind !== kind.value) return
    items.value = response.data
    lastPage.value = response.meta.last_page
    total.value = response.meta.total
  } catch (reason) {
    if (loadId === latestLoad && requestedKind === kind.value) {
      error.value = message(reason, `Could not load ${requestedTitle.toLowerCase()}.`)
    }
  } finally {
    if (loadId === latestLoad) loading.value = false
  }
}

function sortCatalog(key: string) {
  sortDirection.value = sortBy.value === key && sortDirection.value === 'asc' ? 'desc' : 'asc'
  sortBy.value = key
  page.value = 1
  void loadItems()
}

function resetForm() {
  editingId.value = null
  Object.assign(form, { name: '', rate: '', penalty_option: 'PER DAY', description: '' })
  showForm.value = false
}

function newItem() {
  resetForm()
  showForm.value = true
}

function editItem(item: CatalogRecord) {
  editingId.value = item.id
  Object.assign(form, {
    name: item.name,
    rate: item.rate === undefined ? '' : String(item.rate),
    penalty_option: item.penalty_option ?? 'PER DAY',
    description: item.description ?? '',
  })
  showForm.value = true
  error.value = ''
}

async function saveItem() {
  const payload: Record<string, string> = { name: form.name }
  if (kind.value !== 'loan-categories') payload.rate = form.rate
  if (kind.value === 'penalties') {
    payload.penalty_option = form.penalty_option
    payload.description = form.description
  }

  try {
    error.value = ''
    notice.value = ''
    const path = `/admin/catalog/${kind.value}${editingId.value ? `/${editingId.value}` : ''}`
    await api.request<ApiEnvelope<CatalogRecord>>(path, {
      method: editingId.value ? 'PUT' : 'POST',
      body: JSON.stringify(payload),
    })
    notice.value = editingId.value ? 'Changes saved.' : `${title.value.slice(0, -1)} created.`
    resetForm()
    page.value = 1
    await loadItems()
  } catch (reason) {
    error.value = message(reason, `Could not save ${title.value.toLowerCase()}.`)
  }
}

async function deleteItem(item: CatalogRecord) {
  if (!window.confirm(`Delete “${item.name}”?`)) return
  try {
    error.value = ''
    await api.request<void>(`/admin/catalog/${kind.value}/${item.id}`, { method: 'DELETE' })
    notice.value = 'Record deleted.'
    if (items.value.length === 1 && page.value > 1) page.value--
    await loadItems()
  } catch (reason) {
    error.value = message(reason, 'Could not delete this record.')
  }
}

function displayRate(rate?: string | number): string {
  return rate === undefined ? '—' : `${(Number(rate) * 100).toLocaleString(undefined, { maximumFractionDigits: 4 })}%`
}

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    void loadItems()
  }, 250)
})

watch(kind, (nextKind, previousKind) => {
  if (nextKind === previousKind) return
  page.value = 1
  sortBy.value = 'name'
  sortDirection.value = 'asc'
  search.value = ''
  items.value = []
  total.value = 0
  showForm.value = false
  editingId.value = null
  error.value = ''
  notice.value = ''
  void loadItems()
}, { immediate: true })
onBeforeUnmount(() => clearTimeout(searchTimer))
</script>

<template>
  <main class="page-layout admin-page">
    <section class="page-heading">
      <div><p class="eyebrow">LOAN SETUP</p><h1>{{ title }}</h1><p class="muted">Maintain the options used when creating loans.</p></div>
      <button class="admin-primary" type="button" @click="newItem">＋ New {{ kind === 'penalties' ? 'penalty' : kind === 'loan-categories' ? 'category' : 'rate' }}</button>
    </section>

    <p v-if="notice" class="success notice" role="status">{{ notice }}</p>
    <p v-if="error" class="error notice" role="alert">{{ error }}</p>

    <ModalDialog v-model:open="showForm" :title="`${editingId ? 'Edit' : 'Create'} ${kind === 'penalties' ? 'penalty' : kind === 'loan-categories' ? 'category' : 'rate'}`">
    <section v-if="showForm" class="card admin-form-card">
      <div class="admin-section-heading"><div><p class="eyebrow">{{ editingId ? 'EDIT RECORD' : 'NEW RECORD' }}</p><h2>{{ editingId ? 'Update' : 'Create' }} {{ kind === 'penalties' ? 'penalty' : kind === 'loan-categories' ? 'category' : 'rate' }}</h2></div></div>
      <form class="admin-form-grid" @submit.prevent="saveItem">
        <p v-if="error" class="error form-notice field-label--wide" role="alert">{{ error }}</p>
        <label class="field-label">Name<input v-model.trim="form.name" required maxlength="255" placeholder="Enter a name"></label>
        <label v-if="kind !== 'loan-categories'" class="field-label">Rate <span class="field-hint">(decimal; 0.10 = 10%)</span><input v-model="form.rate" type="number" min="0" max="9999.999999" step="0.000001" required placeholder="0.100000"></label>
        <label v-if="kind === 'penalties'" class="field-label">Penalty option<select v-model="form.penalty_option"><option>PER DAY</option><option>PER MONTH</option></select></label>
        <label v-if="kind === 'penalties'" class="field-label field-label--wide">Description<textarea v-model.trim="form.description" rows="4" required placeholder="Describe when and how this penalty applies."></textarea></label>
        <div class="form-actions field-label--wide"><button class="admin-primary" type="submit">{{ editingId ? 'Save changes' : 'Create record' }}</button><button class="secondary" type="button" @click="resetForm">Cancel</button></div>
      </form>
    </section>
    </ModalDialog>

    <section class="card admin-table-card">
      <div class="admin-toolbar">
        <div><h2>{{ title }}</h2><p class="muted">{{ total }} {{ total === 1 ? 'record' : 'records' }}</p></div>
        <div class="admin-search"><input v-model="search" type="search" placeholder="Search by name" aria-label="Search by name"></div>
      </div>
      <p v-if="loading" class="muted admin-loading">Loading records…</p>
      <div v-else class="table-scroll"><table class="admin-table">
        <thead><tr>
          <th><button class="table-sort" type="button" @click="sortCatalog('name')">Name <span>{{ sortBy === 'name' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th v-if="kind !== 'loan-categories'"><button class="table-sort" type="button" @click="sortCatalog('rate')">Rate <span>{{ sortBy === 'rate' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th v-if="kind === 'penalties'"><button class="table-sort" type="button" @click="sortCatalog('penalty_option')">Penalty option <span>{{ sortBy === 'penalty_option' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th v-if="kind === 'penalties'"><button class="table-sort" type="button" @click="sortCatalog('description')">Description <span>{{ sortBy === 'description' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th><th class="actions-column">Actions</th></tr></thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td class="primary-cell">{{ item.name }}</td>
            <td v-if="kind !== 'loan-categories'">{{ displayRate(item.rate) }} <span class="table-subtext">({{ item.rate }})</span></td>
            <td v-if="kind === 'penalties'"><span class="status-pill">{{ item.penalty_option }}</span></td>
            <td v-if="kind === 'penalties'" class="description-cell">{{ item.description }}</td>
            <td class="row-actions"><button class="icon-action" type="button" :aria-label="`Edit ${item.name}`" title="Edit" @click="editItem(item)">✎</button><button class="icon-action icon-action--danger" type="button" :aria-label="`Delete ${item.name}`" title="Delete" @click="deleteItem(item)"><svg aria-hidden="true" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2m-11 0 1 15h12l1-15M10 10v7m4-7v7" /></svg></button></td>
          </tr>
          <tr v-if="!items.length"><td :colspan="kind === 'penalties' ? 5 : kind === 'loan-categories' ? 2 : 3" class="empty">No {{ title.toLowerCase() }} found.</td></tr>
        </tbody>
      </table></div>
      <footer class="pagination"><span>Page {{ page }} of {{ lastPage }}</span><div><button class="secondary" type="button" :disabled="page <= 1" @click="page--; loadItems()">Previous</button><button class="secondary" type="button" :disabled="page >= lastPage" @click="page++; loadItems()">Next</button></div></footer>
    </section>
  </main>
</template>
