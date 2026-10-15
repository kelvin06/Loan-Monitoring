<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import type { ApiEnvelope, CatalogRecord, LoanLedger, LoanRecord, PaginatedResponse, User } from '@loan-monitoring/shared-types'
import { ApiClientError } from '@loan-monitoring/api-client'
import { api } from '../api'
import ModalDialog from '../components/ModalDialog.vue'

const loans = ref<LoanRecord[]>([])
const users = ref<User[]>([])
const categories = ref<CatalogRecord[]>([])
const downpaymentRates = ref<CatalogRecord[]>([])
const interestRates = ref<CatalogRecord[]>([])
const penalties = ref<CatalogRecord[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const search = ref('')
const categoryFilter = ref('')
const sortBy = ref('id')
const sortDirection = ref<'asc' | 'desc'>('desc')
const loading = ref(false)
const saving = ref(false)
const showForm = ref(false)
const editingId = ref<number | null>(null)
const selectedLoan = ref<LoanRecord | null>(null)
const selectedLedgerForPayment = ref<LoanLedger | null>(null)
const showPaymentForm = ref(false)
const ledgerSortBy = ref('due_date')
const ledgerSortDirection = ref<'asc' | 'desc'>('asc')
const showReferencePreview = ref(false)
const referencePreviewUrl = ref('')
const paymentDate = ref(localToday())
const paymentFile = ref<File | null>(null)
const error = ref('')
const notice = ref('')
const apiRoot = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1').replace(/\/api\/v1\/?$/, '')
let filterTimer: ReturnType<typeof setTimeout> | undefined

function localToday(): string {
  const date = new Date()
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset())
  return date.toISOString().slice(0, 10)
}

const form = reactive({
  loan_name: '', loan_amount: '', months_to_pay: '12', loan_date: localToday(), start_date: localToday(),
  user_id: '', downpayment_rate_id: '', loan_category_id: '', loan_interest_rate_id: '', penalty_id: '',
})

const selectedDownpaymentRate = computed(() => downpaymentRates.value.find((rate) => String(rate.id) === form.downpayment_rate_id))
const selectedInterestRate = computed(() => interestRates.value.find((rate) => String(rate.id) === form.loan_interest_rate_id))
const estimatedDownPayment = computed(() => {
  const amount = Number(form.loan_amount)
  return amount > 0 && selectedDownpaymentRate.value ? (amount * Number(selectedDownpaymentRate.value.rate)).toFixed(4) : '0.0000'
})
const estimatedAmortization = computed(() => {
  const amount = Number(form.loan_amount)
  const months = Number(form.months_to_pay)
  return amount > 0 && months > 0 && selectedInterestRate.value
    ? ((Number(selectedInterestRate.value.rate) * amount) + (amount / months)).toFixed(4)
    : '0.0000'
})
const sortedLedgers = computed(() => [...(selectedLoan.value?.ledgers ?? [])].sort((left, right) => {
  const leftValue = left[ledgerSortBy.value as keyof LoanLedger] ?? ''
  const rightValue = right[ledgerSortBy.value as keyof LoanLedger] ?? ''
  const compared = String(leftValue).localeCompare(String(rightValue), undefined, { numeric: true })
  return ledgerSortDirection.value === 'asc' ? compared : -compared
}))

function message(reason: unknown, fallback: string): string {
  return reason instanceof ApiClientError ? reason.message : fallback
}

watch([search, categoryFilter], () => {
  clearTimeout(filterTimer)
  filterTimer = setTimeout(() => {
    page.value = 1
    void loadLoans()
  }, 250)
})

async function loadOptions() {
  const [usersResponse, categoriesResponse, downpaymentResponse, interestResponse, penaltiesResponse] = await Promise.all([
    api.request<PaginatedResponse<User>>('/admin/users?role=user&status=1&per_page=100'),
    api.request<PaginatedResponse<CatalogRecord>>('/admin/catalog/loan-categories?per_page=100'),
    api.request<PaginatedResponse<CatalogRecord>>('/admin/catalog/down-payment-rates?per_page=100'),
    api.request<PaginatedResponse<CatalogRecord>>('/admin/catalog/loan-interest-rates?per_page=100'),
    api.request<PaginatedResponse<CatalogRecord>>('/admin/catalog/penalties?per_page=100'),
  ])
  users.value = usersResponse.data
  categories.value = categoriesResponse.data
  downpaymentRates.value = downpaymentResponse.data
  interestRates.value = interestResponse.data
  penalties.value = penaltiesResponse.data
}

async function loadLoans() {
  loading.value = true
  error.value = ''
  try {
    const query = new URLSearchParams({ page: String(page.value), per_page: '20', sort: sortBy.value, direction: sortDirection.value })
    if (search.value.trim()) query.set('search', search.value.trim())
    if (categoryFilter.value) query.set('loan_category_id', categoryFilter.value)
    const response = await api.request<PaginatedResponse<LoanRecord>>(`/admin/loans?${query}`)
    loans.value = response.data
    lastPage.value = response.meta.last_page
    total.value = response.meta.total
  } catch (reason) {
    error.value = message(reason, 'Could not load loans.')
  } finally {
    loading.value = false
  }
}

function sortLoans(key: string) {
  sortDirection.value = sortBy.value === key && sortDirection.value === 'asc' ? 'desc' : 'asc'
  sortBy.value = key
  page.value = 1
  void loadLoans()
}

function sortLedger(key: string) {
  ledgerSortDirection.value = ledgerSortBy.value === key && ledgerSortDirection.value === 'asc' ? 'desc' : 'asc'
  ledgerSortBy.value = key
}

function resetForm() {
  editingId.value = null
  Object.assign(form, {
    loan_name: '', loan_amount: '', months_to_pay: '12', loan_date: localToday(), start_date: localToday(),
    user_id: '', downpayment_rate_id: '', loan_category_id: '', loan_interest_rate_id: '', penalty_id: '',
  })
  showForm.value = false
}

function editLoan(loan: LoanRecord) {
  editingId.value = loan.id
  Object.assign(form, {
    loan_name: loan.loan_name,
    loan_amount: loan.loan_amount,
    months_to_pay: String(loan.months_to_pay),
    loan_date: loan.loan_date,
    start_date: loan.start_date,
    user_id: String(loan.user_id),
    downpayment_rate_id: String(loan.downpayment_rate_id),
    loan_category_id: String(loan.loan_category_id),
    loan_interest_rate_id: String(loan.loan_interest_rate_id),
    penalty_id: String(loan.penalty_id),
  })
  showForm.value = true
  error.value = ''
}

async function saveLoan() {
  saving.value = true
  error.value = ''
  notice.value = ''
  const payload = {
    loan_name: form.loan_name,
    loan_amount: form.loan_amount,
    months_to_pay: Number(form.months_to_pay),
    loan_date: form.loan_date,
    start_date: form.start_date,
    user_id: Number(form.user_id),
    downpayment_rate_id: Number(form.downpayment_rate_id),
    loan_category_id: Number(form.loan_category_id),
    loan_interest_rate_id: Number(form.loan_interest_rate_id),
    penalty_id: Number(form.penalty_id),
  }

  try {
    const path = `/admin/loans${editingId.value ? `/${editingId.value}` : ''}`
    const response = await api.request<ApiEnvelope<LoanRecord>>(path, {
      method: editingId.value ? 'PUT' : 'POST',
      body: JSON.stringify(payload),
    })
    const createdOrUpdated = response.data
    notice.value = editingId.value ? 'Loan and schedule updated.' : 'Loan created with a generated payment schedule.'
    resetForm()
    page.value = 1
    await loadLoans()
    await openLedger(createdOrUpdated.id)
  } catch (reason) {
    error.value = message(reason, 'Could not save this loan.')
  } finally {
    saving.value = false
  }
}

async function openLedger(id: number) {
  try {
    error.value = ''
    const response = await api.request<ApiEnvelope<LoanRecord>>(`/admin/loans/${id}`)
    selectedLoan.value = response.data
  } catch (reason) {
    error.value = message(reason, 'Could not load the payment schedule.')
  }
}

async function deleteLoan(loan: LoanRecord) {
  if (!window.confirm(`Delete “${loan.loan_name}” and its payment schedule?`)) return
  try {
    error.value = ''
    await api.request<void>(`/admin/loans/${loan.id}`, { method: 'DELETE' })
    notice.value = 'Loan deleted.'
    if (selectedLoan.value?.id === loan.id) selectedLoan.value = null
    if (loans.value.length === 1 && page.value > 1) page.value--
    await loadLoans()
  } catch (reason) {
    error.value = message(reason, 'Could not delete this loan.')
  }
}

function openPaymentForm(ledger: LoanLedger) {
  selectedLedgerForPayment.value = ledger
  paymentDate.value = localToday()
  paymentFile.value = null
  showPaymentForm.value = true
}

function setPaymentFile(event: Event) {
  const input = event.target as HTMLInputElement
  paymentFile.value = input.files?.[0] ?? null
}

async function recordPayment(ledger: LoanLedger) {
  const file = paymentFile.value
  const date = paymentDate.value
  if (!file || !date) {
    error.value = 'Choose the payment date and an image of the payment reference.'
    return
  }

  const payload = new FormData()
  payload.set('payment_date', date)
  payload.set('payment_reference', file)
  try {
    error.value = ''
    await api.request(`/admin/loan-ledgers/${ledger.id}/payment`, { method: 'POST', body: payload })
    notice.value = 'Payment recorded.'
    showPaymentForm.value = false
    selectedLedgerForPayment.value = null
    await openLedger(ledger.loan_id)
    await loadLoans()
  } catch (reason) {
    error.value = message(reason, 'Could not record the payment.')
  }
}

function recordSelectedPayment() {
  if (selectedLedgerForPayment.value) void recordPayment(selectedLedgerForPayment.value)
}

function referenceUrl(ledgerId: number): string {
  return `${apiRoot}/api/v1/admin/loan-ledgers/${ledgerId}/payment-reference`
}

async function viewPaymentReference(ledgerId: number) {
  error.value = ''
  try {
    const response = await fetch(referenceUrl(ledgerId), { credentials: 'include' })
    if (!response.ok) {
      throw new Error(response.status === 401 || response.status === 403
        ? 'Your session expired. Sign in again to view this payment reference.'
        : 'Could not load this payment reference.')
    }
    if (referencePreviewUrl.value) URL.revokeObjectURL(referencePreviewUrl.value)
    referencePreviewUrl.value = URL.createObjectURL(await response.blob())
    showReferencePreview.value = true
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Could not load this payment reference.'
  }
}

function currency(value: string): string {
  return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })
}

onMounted(async () => {
  try {
    await loadOptions()
    await loadLoans()
  } catch (reason) {
    error.value = message(reason, 'Could not load loan setup options. Create categories, rates, and penalties first.')
  }
})
onBeforeUnmount(() => clearTimeout(filterTimer))
</script>

<template>
  <main class="page-layout admin-page">
    <section class="page-heading">
      <div><p class="eyebrow">LOAN MANAGEMENT</p><h1>Loans</h1><p class="muted">Create loan accounts and manage their repayment schedules.</p></div>
      <button class="admin-primary" type="button" @click="resetForm(); showForm = true">＋ New loan</button>
    </section>

    <p v-if="notice" class="success notice" role="status">{{ notice }}</p>
    <p v-if="error" class="error notice" role="alert">{{ error }}</p>

    <ModalDialog v-model:open="showForm" :title="editingId ? 'Edit loan' : 'Create loan'">
    <section v-if="showForm" class="card admin-form-card loan-form-card">
      <div class="admin-section-heading"><div><p class="eyebrow">{{ editingId ? 'UPDATE LOAN' : 'NEW LOAN' }}</p><h2>{{ editingId ? 'Edit loan details' : 'Loan details' }}</h2></div><button class="secondary" type="button" @click="resetForm">Cancel</button></div>
      <form class="admin-form-grid loan-form-grid" @submit.prevent="saveLoan">
        <p v-if="error" class="error form-notice field-label--wide" role="alert">{{ error }}</p>
        <label class="field-label">Loan name<input v-model.trim="form.loan_name" required maxlength="255" placeholder="e.g. Home improvement loan"></label>
        <label class="field-label">Borrower<select v-model="form.user_id" required><option disabled value="">Choose a user</option><option v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.name }} · {{ user.email }}</option></select></label>
        <label class="field-label">Loan category<select v-model="form.loan_category_id" required><option disabled value="">Choose a category</option><option v-for="item in categories" :key="item.id" :value="String(item.id)">{{ item.name }}</option></select></label>
        <label class="field-label">Loan amount<input v-model="form.loan_amount" type="number" min="0.0001" step="0.0001" required placeholder="0.0000"></label>
        <label class="field-label">Months to pay<input v-model="form.months_to_pay" type="number" min="1" max="600" step="1" required></label>
        <label class="field-label">Loan date<input v-model="form.loan_date" type="date" required></label>
        <label class="field-label">First payment due date<input v-model="form.start_date" type="date" required></label>
        <label class="field-label">Down payment rate<select v-model="form.downpayment_rate_id" required><option disabled value="">Choose a rate</option><option v-for="rate in downpaymentRates" :key="rate.id" :value="String(rate.id)">{{ rate.name }} · {{ (Number(rate.rate) * 100).toLocaleString() }}%</option></select></label>
        <label class="field-label">Loan interest rate<select v-model="form.loan_interest_rate_id" required><option disabled value="">Choose a rate</option><option v-for="rate in interestRates" :key="rate.id" :value="String(rate.id)">{{ rate.name }} · {{ (Number(rate.rate) * 100).toLocaleString() }}%</option></select></label>
        <label class="field-label">Penalty<select v-model="form.penalty_id" required><option disabled value="">Choose a penalty</option><option v-for="penalty in penalties" :key="penalty.id" :value="String(penalty.id)">{{ penalty.name }} · {{ penalty.penalty_option?.toLowerCase() }}</option></select></label>

        <div class="loan-estimate field-label--wide">
          <div><span>Estimated down payment</span><strong>{{ currency(estimatedDownPayment) }}</strong></div>
          <div><span>Monthly amortization</span><strong>{{ currency(estimatedAmortization) }}</strong></div>
          <p>Calculated as (interest rate × loan amount) + (loan amount ÷ months to pay). The server recalculates these values when saved.</p>
        </div>
        <div class="form-actions field-label--wide"><button class="admin-primary" type="submit" :disabled="saving || !users.length || !categories.length || !downpaymentRates.length || !interestRates.length || !penalties.length">{{ saving ? 'Saving…' : editingId ? 'Save loan' : 'Create loan and schedule' }}</button></div>
        <p v-if="!users.length || !categories.length || !downpaymentRates.length || !interestRates.length || !penalties.length" class="field-hint field-label--wide">Add at least one active user, category, down payment rate, interest rate, and penalty before creating a loan.</p>
      </form>
    </section>
    </ModalDialog>

    <section class="card admin-table-card">
      <div class="admin-toolbar admin-toolbar--filters">
        <div><h2>Loan records</h2><p class="muted">{{ total }} {{ total === 1 ? 'loan' : 'loans' }}</p></div>
        <div class="admin-search"><input v-model="search" type="search" placeholder="Search loan or borrower" aria-label="Search loan or borrower"><select v-model="categoryFilter" aria-label="Filter by category"><option value="">All categories</option><option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option></select></div>
      </div>
      <p v-if="loading" class="muted admin-loading">Loading loans…</p>
      <div v-else class="table-scroll"><table class="admin-table loan-table">
        <thead><tr>
          <th><button class="table-sort" type="button" @click="sortLoans('loan_name')">Loan <span>{{ sortBy === 'loan_name' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th><button class="table-sort" type="button" @click="sortLoans('user_name')">Borrower <span>{{ sortBy === 'user_name' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th><button class="table-sort" type="button" @click="sortLoans('loan_category_name')">Category <span>{{ sortBy === 'loan_category_name' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th><button class="table-sort" type="button" @click="sortLoans('loan_amount')">Principal <span>{{ sortBy === 'loan_amount' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th><button class="table-sort" type="button" @click="sortLoans('balance')">Balance <span>{{ sortBy === 'balance' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th><button class="table-sort" type="button" @click="sortLoans('monthly_amortization')">Monthly <span>{{ sortBy === 'monthly_amortization' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th><button class="table-sort" type="button" @click="sortLoans('months_to_pay')">Months <span>{{ sortBy === 'months_to_pay' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th><button class="table-sort" type="button" @click="sortLoans('start_date')">First due <span>{{ sortBy === 'start_date' ? (sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th><th class="actions-column">Actions</th></tr></thead>
        <tbody>
          <tr v-for="loan in loans" :key="loan.id">
            <td class="primary-cell">{{ loan.loan_name }}</td><td>{{ loan.user_name }}</td><td>{{ loan.loan_category_name }}</td>
            <td>{{ currency(loan.loan_amount) }}</td><td class="primary-cell">{{ currency(loan.balance) }}</td><td>{{ currency(loan.monthly_amortization) }}</td><td>{{ loan.months_to_pay }}</td><td>{{ loan.start_date }}</td>
            <td class="row-actions"><button class="icon-action" type="button" :aria-label="`Open ledger for ${loan.loan_name}`" title="Ledger" @click="openLedger(loan.id)">▤</button><button class="icon-action" type="button" :aria-label="`Edit ${loan.loan_name}`" title="Edit" @click="editLoan(loan)">✎</button><button class="icon-action icon-action--danger" type="button" :aria-label="`Delete ${loan.loan_name}`" title="Delete" @click="deleteLoan(loan)"><svg aria-hidden="true" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2m-11 0 1 15h12l1-15M10 10v7m4-7v7" /></svg></button></td>
          </tr>
          <tr v-if="!loans.length"><td colspan="9" class="empty">No loans found. Create a loan to generate its payment schedule.</td></tr>
        </tbody>
      </table></div>
      <footer class="pagination"><span>Page {{ page }} of {{ lastPage }}</span><div><button class="secondary" type="button" :disabled="page <= 1" @click="page--; loadLoans()">Previous</button><button class="secondary" type="button" :disabled="page >= lastPage" @click="page++; loadLoans()">Next</button></div></footer>
    </section>

    <section v-if="selectedLoan" class="card ledger-card">
      <div class="admin-toolbar"><div><p class="eyebrow">PAYMENT SCHEDULE</p><h2>{{ selectedLoan.loan_name }}</h2><p class="muted">{{ selectedLoan.user_name }} · {{ selectedLoan.ledgers?.length ?? 0 }} installments · {{ currency(selectedLoan.balance) }} remaining</p></div><button class="secondary" type="button" @click="selectedLoan = null">Close</button></div>
      <div class="table-scroll"><table class="admin-table">
        <thead><tr><th>#</th>
          <th><button class="table-sort" type="button" @click="sortLedger('amount')">Amount due <span>{{ ledgerSortBy === 'amount' ? (ledgerSortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th><button class="table-sort" type="button" @click="sortLedger('due_date')">Due date <span>{{ ledgerSortBy === 'due_date' ? (ledgerSortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th><button class="table-sort" type="button" @click="sortLedger('payment_date')">Payment date <span>{{ ledgerSortBy === 'payment_date' ? (ledgerSortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
          <th><button class="table-sort" type="button" @click="sortLedger('payment_reference')">Payment reference <span>{{ ledgerSortBy === 'payment_reference' ? (ledgerSortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th></tr></thead>
        <tbody><tr v-for="(ledger, index) in sortedLedgers" :key="ledger.id">
          <td>{{ String(index + 1).padStart(2, '0') }}</td><td class="primary-cell">{{ currency(ledger.amount) }}</td><td>{{ ledger.due_date }}</td>
          <td>{{ ledger.payment_date ?? 'Unpaid' }}</td>
          <td v-if="ledger.payment_reference"><button class="icon-action" type="button" :aria-label="`View payment reference for installment ${index + 1}`" title="View payment reference" @click="viewPaymentReference(ledger.id)">▧</button></td>
          <td v-else><button class="icon-action icon-action--primary" type="button" :aria-label="`Record payment for installment ${index + 1}`" title="Record payment" @click="openPaymentForm(ledger)">＋</button></td>
        </tr></tbody>
      </table></div>
    </section>

    <ModalDialog v-model:open="showPaymentForm" title="Record installment payment">
      <form v-if="selectedLedgerForPayment" class="payment-modal-form" @submit.prevent="recordSelectedPayment">
        <p v-if="error" class="error form-notice" role="alert">{{ error }}</p>
        <p class="payment-modal-help">Installment {{ String((selectedLoan?.ledgers?.findIndex((item) => item.id === selectedLedgerForPayment?.id) ?? 0) + 1).padStart(2, '0') }} · {{ selectedLoan?.loan_name }} · {{ currency(selectedLedgerForPayment.amount) }} due on {{ selectedLedgerForPayment.due_date }}</p>
        <label class="field-label">Payment date<input v-model="paymentDate" type="date" required></label>
        <label class="field-label">Payment reference image<input type="file" accept="image/*" required @change="setPaymentFile"><span class="field-hint">Choose an image file up to 5 MB.</span></label>
        <div class="form-actions"><button class="admin-primary" type="submit">Save payment</button><button class="secondary" type="button" @click="showPaymentForm = false">Cancel</button></div>
      </form>
    </ModalDialog>

    <ModalDialog v-model:open="showReferencePreview" title="Payment reference">
      <img v-if="referencePreviewUrl" class="payment-reference-preview" :src="referencePreviewUrl" alt="Uploaded payment reference">
    </ModalDialog>
  </main>
</template>
