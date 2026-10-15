<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { ApiEnvelope, LoanLedger, PaginatedResponse } from '@loan-monitoring/shared-types'
import { ApiClientError } from '@loan-monitoring/api-client'
import { api } from '../api'
import ModalDialog from '../components/ModalDialog.vue'
import SortableTable from '../components/SortableTable.vue'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const ledgers = ref<LoanLedger[]>([])
const page = ref(0)
const lastPage = ref(1)
const total = ref(0)
const search = ref('')
const status = ref('all')
const sortBy = ref('due_date')
const direction = ref<'asc' | 'desc'>('asc')
const loading = ref(false)
const error = ref('')
const notice = ref('')
const selectedLedger = ref<LoanLedger | null>(null)
const paymentDate = ref(today())
const paymentFile = ref<File | null>(null)
const saving = ref(false)
const showPaymentForm = ref(false)
const loadMoreTarget = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null
let searchTimer: ReturnType<typeof setTimeout> | undefined
let requestId = 0

function today() {
  const date = new Date()
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset())
  return date.toISOString().slice(0, 10)
}
function amount(value: string) { return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 }) }
function message(reason: unknown, fallback: string) { return reason instanceof ApiClientError ? reason.message : fallback }

async function loadPage(nextPage: number, append = false) {
  if (loading.value || nextPage > lastPage.value) return
  const currentRequest = ++requestId
  loading.value = true
  error.value = ''
  try {
    const query = new URLSearchParams({ page: String(nextPage), per_page: '20', sort: sortBy.value, direction: direction.value })
    if (search.value.trim()) query.set('search', search.value.trim())
    if (status.value !== 'all') query.set('status', status.value)
    const result = await api.request<PaginatedResponse<LoanLedger>>(`/user/loan-ledgers?${query}`)
    if (currentRequest !== requestId) return
    ledgers.value = append ? [...ledgers.value, ...result.data] : result.data
    page.value = result.meta.current_page
    lastPage.value = result.meta.last_page
    total.value = result.meta.total
  } catch (reason) {
    if (currentRequest === requestId) error.value = message(reason, 'Could not load your loan ledger.')
  } finally {
    if (currentRequest === requestId) loading.value = false
  }
}
function resetAndLoad() {
  requestId++
  loading.value = false
  page.value = 0
  lastPage.value = 1
  ledgers.value = []
  void loadPage(1)
}
function sortTable(key: string) {
  direction.value = sortBy.value === key && direction.value === 'asc' ? 'desc' : 'asc'
  sortBy.value = key
  resetAndLoad()
}
function beginPayment(ledger: LoanLedger) {
  selectedLedger.value = ledger
  paymentDate.value = today()
  paymentFile.value = null
  showPaymentForm.value = true
}
function beginPaymentRow(row: Record<string, unknown>) { beginPayment(row as unknown as LoanLedger) }
function selectFile(event: Event) { paymentFile.value = (event.target as HTMLInputElement).files?.[0] ?? null }
async function submitPayment() {
  if (!selectedLedger.value || !paymentFile.value) return
  saving.value = true
  error.value = ''
  const payload = new FormData()
  payload.set('payment_date', paymentDate.value)
  payload.set('payment_reference', paymentFile.value)
  try {
    await api.request<ApiEnvelope<LoanLedger>>(`/user/loan-ledgers/${selectedLedger.value.id}/payment`, { method: 'POST', body: payload })
    notice.value = 'Payment reference uploaded.'
    showPaymentForm.value = false
    resetAndLoad()
  } catch (reason) {
    error.value = message(reason, 'Could not upload this payment reference.')
  } finally { saving.value = false }
}

watch([search, status], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(resetAndLoad, 250)
})
onMounted(() => {
  resetAndLoad()
  if ('IntersectionObserver' in window && loadMoreTarget.value) {
    observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting) && !loading.value && page.value < lastPage.value) void loadPage(page.value + 1, true)
    }, { rootMargin: '180px' })
    observer.observe(loadMoreTarget.value)
  }
})
onBeforeUnmount(() => { observer?.disconnect(); clearTimeout(searchTimer) })

const columns = [
  { key: 'loan_name', label: 'Loan' }, { key: 'amount', label: 'Amount due' },
  { key: 'due_date', label: 'Due date' }, { key: 'payment_date', label: 'Payment status' }, { key: 'actions', label: 'Action', sortable: false },
]
</script>

<template>
  <component :is="embedded ? 'section' : 'main'" class="page-layout admin-page" :class="{ 'user-loans-embedded': embedded }">
    <section class="page-heading"><div><p class="eyebrow">YOUR ACCOUNT</p><h1>My loans</h1><p class="muted">Review installments and upload a reference after payment.</p></div></section>
    <p v-if="notice" class="success notice" role="status">{{ notice }}</p>
    <p v-if="error" class="error notice" role="alert">{{ error }}</p>
    <section class="card admin-table-card">
      <div class="admin-toolbar loan-ledger-filters"><div><h2>Loan installments</h2><p class="muted">{{ total }} records</p></div>
        <div class="admin-search"><input v-model="search" type="search" placeholder="Search loan name" aria-label="Search loans">
          <select v-model="status" aria-label="Filter by payment status"><option value="all">All installments</option><option value="unpaid">Unpaid</option><option value="paid">Paid</option></select></div>
      </div>
      <SortableTable :columns="columns" :rows="ledgers" :sort-by="sortBy" :sort-direction="direction" empty-text="No installments found." @sort="sortTable">
        <template #cell-amount="{ row }">{{ amount(String(row.amount)) }}</template>
        <template #cell-payment_date="{ row }">{{ row.payment_date ? `Paid · ${row.payment_date}` : 'Unpaid' }}</template>
        <template #cell-actions="{ row }">
          <button v-if="!row.payment_date" class="icon-action icon-action--primary" type="button" :aria-label="`Upload payment reference for ${row.loan_name}`" title="Upload payment reference" @click="beginPaymentRow(row)">↑</button>
          <span v-else class="table-subtext">Reference submitted</span>
        </template>
      </SortableTable>
      <p v-if="loading" class="muted admin-loading">Loading installments…</p>
      <div ref="loadMoreTarget" class="lazy-load-sentinel" aria-hidden="true"></div>
      <button v-if="page < lastPage && !loading" class="secondary load-more-button" type="button" @click="loadPage(page + 1, true)">Load more</button>
    </section>
    <ModalDialog v-model:open="showPaymentForm" title="Upload payment reference">
      <form v-if="selectedLedger" class="payment-modal-form" @submit.prevent="submitPayment">
        <p class="payment-modal-help">{{ selectedLedger.loan_name }} · {{ amount(selectedLedger.amount) }} due on {{ selectedLedger.due_date }}. Uploading records this installment as paid.</p>
        <label class="field-label">Payment date<input v-model="paymentDate" type="date" required></label>
        <label class="field-label">Payment reference image<input type="file" accept="image/*" required @change="selectFile"><span class="field-hint">Image file, up to 5 MB.</span></label>
        <div class="form-actions"><button class="admin-primary" type="submit" :disabled="saving || !paymentFile">{{ saving ? 'Uploading…' : 'Upload reference' }}</button><button class="secondary" type="button" @click="showPaymentForm = false">Cancel</button></div>
      </form>
    </ModalDialog>
  </component>
</template>
