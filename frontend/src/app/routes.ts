import { lazy, type ComponentType } from 'react'

/** Definisi halaman yang didaftarkan setiap modul frontend. */
export type ModuleRoute = {
  path: string
  component: ComponentType
  /** Permission yang wajib dimiliki untuk membuka halaman. */
  permission?: string
  /** Tampil layar penuh tanpa bar navigasi aplikasi (mis. layar kasir POS). */
  fullscreen?: boolean
}

/**
 * Modul frontend. `name` harus sama dengan "name" di backend module.json;
 * halaman & perluasannya hanya aktif bila modul itu terpasang.
 */
export type FrontendModule = {
  name: string
  routes?: ModuleRoute[]
  /** Dipanggil sekali saat aplikasi dimuat, mis. untuk mendaftarkan slot perluasan. */
  setup?: () => void
}

/**
 * Halaman yang dimuat saat pertama dibuka (code splitting), mis.
 * `lazyPage(() => import('./pages/StockPage'), 'StockPage')`.
 */
export function lazyPage<M extends Record<string, unknown>, K extends keyof M>(loader: () => Promise<M>, name: K) {
  return lazy(async () => ({ default: (await loader())[name] as ComponentType<Record<string, unknown>> }))
}
