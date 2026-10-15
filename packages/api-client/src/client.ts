export interface ApiErrorPayload {
  message?: string
  errors?: Record<string, string[]>
}

export class ApiClientError extends Error {
  readonly status?: number
  readonly errors: Record<string, string[]>

  constructor(message: string, status?: number, errors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiClientError'
    this.status = status
    this.errors = errors
  }
}

export interface AuthContext {
  url: string
  method: string
}

export interface AuthAdapter {
  headers(context: AuthContext): Promise<HeadersInit>
  credentials: RequestCredentials
}

function readCookie(name: string): string | undefined {
  const prefix = `${name}=`
  const value = document.cookie.split('; ').find((cookie) => cookie.startsWith(prefix))
  return value ? decodeURIComponent(value.slice(prefix.length)) : undefined
}

export const webAuth: AuthAdapter = {
  credentials: 'include',
  async headers({ url, method }) {
    const headers: Record<string, string> = { 'X-Requested-With': 'XMLHttpRequest' }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase())) {
      if (!readCookie('XSRF-TOKEN')) {
        const apiRoot = url.replace(/\/api\/v1(?:\/.*)?$/, '')
        await fetch(`${apiRoot}/sanctum/csrf-cookie`, { credentials: 'include' })
      }
      const csrfToken = readCookie('XSRF-TOKEN')
      if (csrfToken) headers['X-XSRF-TOKEN'] = csrfToken
    }

    return headers
  },
}

export const mobileAuth = (token: () => Promise<string | null>): AuthAdapter => ({
  credentials: 'omit',
  async headers(): Promise<HeadersInit> {
    const value = await token()
    if (!value) return {}
    return { Authorization: `Bearer ${value}` }
  },
})

export function createApi(baseUrl: string, auth: AuthAdapter) {
  const apiBase = baseUrl.replace(/\/$/, '')

  async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const url = `${apiBase}/${path.replace(/^\//, '')}`
    const method = (init.method ?? 'GET').toUpperCase()
    const headers = new Headers(init.headers)
    headers.set('Accept', 'application/json')
    if (init.body && !(init.body instanceof FormData) && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json')
    }

    const authHeaders = new Headers(await auth.headers({ url, method }))
    authHeaders.forEach((value, key) => headers.set(key, value))

    const controller = new AbortController()
    const abortFromCaller = () => controller.abort(init.signal?.reason)
    init.signal?.addEventListener('abort', abortFromCaller, { once: true })
    if (init.signal?.aborted) abortFromCaller()
    const timeout = setTimeout(() => controller.abort(new DOMException('Request timed out', 'TimeoutError')), 15_000)

    try {
      const response = await fetch(url, {
        ...init,
        method,
        headers,
        signal: controller.signal,
        credentials: auth.credentials,
      })

      if (response.status === 204) return undefined as T

      const body = (await response.json().catch(() => ({}))) as ApiErrorPayload & T
      if (!response.ok) {
        const validationMessage = Object.values(body.errors ?? {}).flat()[0]
        throw new ApiClientError(body.message ?? validationMessage ?? 'The request failed.', response.status, body.errors)
      }

      return body as T
    } catch (error) {
      if (error instanceof ApiClientError) throw error
      if (controller.signal.aborted) {
        throw new ApiClientError('The request timed out or was cancelled.')
      }
      if (error instanceof TypeError) {
        throw new ApiClientError('Network unavailable. Check your connection and try again.')
      }
      throw error
    } finally {
      clearTimeout(timeout)
      init.signal?.removeEventListener('abort', abortFromCaller)
    }
  }

  return { request }
}
