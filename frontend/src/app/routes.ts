import type { ComponentType } from 'react'

/** Definisi halaman yang didaftarkan setiap modul frontend. */
export type ModuleRoute = {
  path: string
  component: ComponentType
  /** Permission yang wajib dimiliki untuk membuka halaman. */
  permission?: string
}
