import axios, { AxiosError } from 'axios'

const TOKEN_KEY = 'erp.token'

export const tokenStorage = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = tokenStorage.get()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

/** Dipanggil saat API membalas 401 (token kedaluwarsa / dicabut). */
let onUnauthorized: (() => void) | null = null
export function setUnauthorizedHandler(handler: (() => void) | null) {
  onUnauthorized = handler
}

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401 && tokenStorage.get()) {
      tokenStorage.clear()
      onUnauthorized?.()
    }
    return Promise.reject(error)
  },
)

type LaravelError = { message?: string; errors?: Record<string, string[]> }

/** Ambil pesan error yang ramah dari respons Laravel. */
export function errorMessage(error: unknown, fallback = 'Terjadi kesalahan. Coba lagi.'): string {
  if (error instanceof AxiosError) {
    const data = error.response?.data as LaravelError | undefined
    const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
    if (firstFieldError) return firstFieldError
    if (data?.message) return data.message
    if (!error.response) return 'Tidak dapat terhubung ke server.'
  }
  return fallback
}
