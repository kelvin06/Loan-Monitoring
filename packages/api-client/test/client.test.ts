import assert from 'node:assert/strict'
import test from 'node:test'
import { ApiClientError, createApi } from '../src/client.ts'

const auth = {
  credentials: 'omit' as RequestCredentials,
  headers: async () => ({ Authorization: 'Bearer test-token' }),
}

test('sends normalized API requests with the platform auth headers', async (context) => {
  let requestUrl = ''
  let requestInit: RequestInit | undefined
  context.mock.method(globalThis, 'fetch', async (input: string | URL | Request, init?: RequestInit) => {
    requestUrl = String(input)
    requestInit = init
    return new Response(JSON.stringify({ data: { ok: true } }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    })
  })

  const api = createApi('https://api.example.test/api/v1/', auth)
  const result = await api.request<{ data: { ok: boolean } }>('/auth/me')

  assert.equal(requestUrl, 'https://api.example.test/api/v1/auth/me')
  assert.equal(new Headers(requestInit?.headers).get('Authorization'), 'Bearer test-token')
  assert.equal(requestInit?.credentials, 'omit')
  assert.deepEqual(result, { data: { ok: true } })
})

test('converts Laravel validation responses into typed client errors', async (context) => {
  context.mock.method(globalThis, 'fetch', async () => new Response(JSON.stringify({
    message: 'The given data was invalid.',
    errors: { email: ['Enter a valid email address.'] },
  }), { status: 422, headers: { 'Content-Type': 'application/json' } }))

  const api = createApi('https://api.example.test/api/v1', auth)

  await assert.rejects(api.request('/auth/login', { method: 'POST', body: '{}' }), (error: unknown) => {
    assert.ok(error instanceof ApiClientError)
    assert.equal(error.status, 422)
    assert.deepEqual(error.errors.email, ['Enter a valid email address.'])
    return true
  })
})

test('accepts empty 204 responses', async (context) => {
  context.mock.method(globalThis, 'fetch', async () => new Response(null, { status: 204 }))
  const api = createApi('https://api.example.test/api/v1', auth)

  assert.equal(await api.request<void>('/admin/users/1', { method: 'DELETE' }), undefined)
})

test('lets the browser set multipart headers for form uploads', async (context) => {
  let requestInit: RequestInit | undefined
  context.mock.method(globalThis, 'fetch', async (_input: string | URL | Request, init?: RequestInit) => {
    requestInit = init
    return new Response(JSON.stringify({ data: { saved: true } }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    })
  })

  const api = createApi('https://api.example.test/api/v1', auth)
  const form = new FormData()
  form.set('payment_date', '2026-10-15')
  form.set('payment_reference', new Blob(['receipt']), 'receipt.png')
  await api.request('/admin/loan-ledgers/1/payment', { method: 'POST', body: form })

  assert.ok(requestInit?.body instanceof FormData)
  assert.equal(new Headers(requestInit?.headers).has('Content-Type'), false)
})
