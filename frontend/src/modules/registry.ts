import type { FrontendModule } from '@/app/routes'
import { baseModule } from './base'
import { contactsModule } from './contacts'
import { inventoryModule } from './inventory'
import { invoicingModule } from './invoicing'
import { membershipModule } from './membership'
import { productModule } from './product'
import { purchaseModule } from './purchase'
import { salesModule } from './sales'

/**
 * Semua modul frontend. Tambahkan modul baru di sini;
 * modul hanya aktif bila modul backend dengan nama yang sama terpasang.
 */
export const frontendModules: FrontendModule[] = [
  baseModule,
  contactsModule,
  productModule,
  inventoryModule,
  invoicingModule,
  salesModule,
  purchaseModule,
  membershipModule,
]

let initialized = false

export function setupModules() {
  if (initialized) return
  initialized = true
  frontendModules.forEach((module) => module.setup?.())
}
