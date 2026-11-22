import { registerSlot } from '@/app/extensions'
import type { FrontendModule } from '@/app/routes'
import { MembershipFields } from './MembershipFields'

/**
 * Contoh modul kustom: tidak punya halaman sendiri, hanya memperluas form Kontak.
 */
export const membershipModule: FrontendModule = {
  name: 'Membership',
  setup: () => registerSlot('contacts.partner.form', { module: 'Membership', component: MembershipFields }),
}
