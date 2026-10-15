import { createApi, webAuth } from '@loan-monitoring/api-client'

const baseUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1'
export const api = createApi(baseUrl, webAuth)
