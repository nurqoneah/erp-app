import type { ComponentType } from 'react'

/** Definisi halaman yang didaftarkan setiap modul frontend. */
export type ModuleRoute = {
  path: string
  component: ComponentType
  /** Permission yang wajib dimiliki untuk membuka halaman. */
  permission?: string
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
