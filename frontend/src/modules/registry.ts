import type { FrontendModule } from '@/app/routes'
import { baseModule } from './base'
import { contactsModule } from './contacts'
import { membershipModule } from './membership'
import { productModule } from './product'

/**
 * Semua modul frontend. Tambahkan modul baru di sini;
 * modul hanya aktif bila modul backend dengan nama yang sama terpasang.
 */
export const frontendModules: FrontendModule[] = [baseModule, contactsModule, productModule, membershipModule]

let initialized = false

export function setupModules() {
  if (initialized) return
  initialized = true
  frontendModules.forEach((module) => module.setup?.())
}
