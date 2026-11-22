import { useCallback, useState } from 'react'
import { AxiosError } from 'axios'

export type FieldErrors = Record<string, string>

/**
 * State form sederhana + pemetaan error validasi Laravel (422) ke field.
 */
export function useFormState<T extends Record<string, unknown>>(initial: T) {
  const [values, setValues] = useState<T>(initial)
  const [errors, setErrors] = useState<FieldErrors>({})

  const set = useCallback(<K extends keyof T>(key: K, value: T[K]) => {
    setValues((prev) => ({ ...prev, [key]: value }))
    setErrors((prev) => {
      if (!(key as string in prev)) return prev
      const next = { ...prev }
      delete next[key as string]
      return next
    })
  }, [])

  /** Ambil error per field dari respons 422. Return true bila error validasi. */
  const applyServerErrors = useCallback((error: unknown): boolean => {
    if (error instanceof AxiosError && error.response?.status === 422) {
      const fieldErrors = (error.response.data as { errors?: Record<string, string[]> }).errors
      if (fieldErrors) {
        setErrors(Object.fromEntries(Object.entries(fieldErrors).map(([k, v]) => [k, v[0]])))
        return true
      }
    }
    return false
  }, [])

  return { values, setValues, set, errors, setErrors, applyServerErrors }
}
