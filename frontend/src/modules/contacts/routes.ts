import { lazyPage, type ModuleRoute } from '@/app/routes'

const ContactFormPage = lazyPage(() => import('./pages/ContactFormPage'), 'ContactFormPage')
const ContactsPage = lazyPage(() => import('./pages/ContactsPage'), 'ContactsPage')

export const contactsRoutes: ModuleRoute[] = [
  { path: 'contacts', component: ContactsPage, permission: 'contacts.partner.view' },
  { path: 'contacts/new', component: ContactFormPage, permission: 'contacts.partner.manage' },
  { path: 'contacts/:id', component: ContactFormPage, permission: 'contacts.partner.view' },
]
