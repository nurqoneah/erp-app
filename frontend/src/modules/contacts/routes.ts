import type { ModuleRoute } from '@/app/routes'
import { ContactFormPage } from './pages/ContactFormPage'
import { ContactsPage } from './pages/ContactsPage'

export const contactsRoutes: ModuleRoute[] = [
  { path: 'contacts', component: ContactsPage, permission: 'contacts.partner.view' },
  { path: 'contacts/new', component: ContactFormPage, permission: 'contacts.partner.manage' },
  { path: 'contacts/:id', component: ContactFormPage, permission: 'contacts.partner.view' },
]
