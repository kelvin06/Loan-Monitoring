export type Role = 'admin' | 'user'
export interface User { id: number; name: string; email: string; role: Role; is_active: boolean; created_at: string }
export interface ApiEnvelope<T> { data: T; message?: string }
export interface DueLoanLedger { id: number; loan_id: number; loan_name: string; user_name: string; amount: string; due_date: string }
export interface AdminDashboardMetrics { total_users: number; active_loans: number; due_loans: DueLoanLedger[] }
export interface UserDashboardMetrics { account_status?: string; active_loans: number; near_due_loans: DueLoanLedger[] }
export type DashboardMetrics = AdminDashboardMetrics
export interface PaginatedResponse<T> extends ApiEnvelope<T[]> {
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}
export interface Credentials { email: string; password: string }
export interface CatalogRecord {
  id: number
  name: string
  rate?: string | number
  penalty_option?: 'PER DAY' | 'PER MONTH'
  description?: string
  created_at?: string
}
export interface LoanLedger {
  id: number
  loan_id: number
  amount: string
  payment_date: string | null
  payment_reference: string | null
  due_date: string
  loan_name?: string
}
export interface LoanRecord {
  id: number
  loan_name: string
  loan_amount: string
  months_to_pay: number
  monthly_amortization: string
  balance: string
  loan_date: string
  start_date: string
  down_payment: string
  user_id: number
  user_name: string
  downpayment_rate_id: number
  downpayment_rate_name: string
  loan_category_id: number
  loan_category_name: string
  loan_interest_rate_id: number
  loan_interest_rate_name: string
  penalty_id: number
  penalty_name: string
  ledgers?: LoanLedger[]
  created_at?: string
}
