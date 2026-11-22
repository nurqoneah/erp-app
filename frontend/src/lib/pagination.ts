/** Bentuk respons list yang seragam di frontend. */
export type Page<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }
}

type LaravelPage<T> = {
  data: T[]
  meta?: Page<T>['meta']
  current_page?: number
  last_page?: number
  per_page?: number
  total?: number
  from?: number | null
  to?: number | null
}

/**
 * Laravel mengirim paginasi dalam `meta` (API Resource) atau di level atas (paginator biasa).
 * Fungsi ini menyeragamkan keduanya.
 */
export function toPage<T>(raw: LaravelPage<T>): Page<T> {
  if (raw.meta) return { data: raw.data, meta: raw.meta }
  return {
    data: raw.data,
    meta: {
      current_page: raw.current_page ?? 1,
      last_page: raw.last_page ?? 1,
      per_page: raw.per_page ?? raw.data.length,
      total: raw.total ?? raw.data.length,
      from: raw.from ?? null,
      to: raw.to ?? null,
    },
  }
}

export type ListParams = {
  search?: string
  page?: number
  per_page?: number
  sort?: string
  direction?: 'asc' | 'desc'
  [filter: string]: string | number | boolean | undefined
}

/** Buang parameter kosong agar URL API rapi. */
export function cleanParams(params: ListParams): Record<string, string | number | boolean> {
  return Object.fromEntries(
    Object.entries(params).filter(([, v]) => v !== undefined && v !== '' && v !== null),
  ) as Record<string, string | number | boolean>
}
