import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router'

import type { ListParams } from '@/lib/pagination'

/**
 * State list (pencarian, halaman, sort, filter) disimpan di URL agar bisa dibagikan
 * dan tetap ada saat kembali dari halaman detail.
 */
export function useListParams(defaults: ListParams = {}) {
  const [searchParams, setSearchParams] = useSearchParams()

  const params = useMemo<ListParams>(() => {
    const result: ListParams = { ...defaults }
    searchParams.forEach((value, key) => {
      result[key] = key === 'page' || key === 'per_page' ? Number(value) : value
    })
    return result
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams])

  const setParams = useCallback(
    (changes: ListParams, { resetPage = true } = {}) => {
      setSearchParams(
        (prev) => {
          const next = new URLSearchParams(prev)
          for (const [key, value] of Object.entries(changes)) {
            if (value === undefined || value === '' || value === defaults[key]) next.delete(key)
            else next.set(key, String(value))
          }
          if (resetPage && !('page' in changes)) next.delete('page')
          return next
        },
        { replace: true },
      )
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [setSearchParams],
  )

  return [params, setParams] as const
}
