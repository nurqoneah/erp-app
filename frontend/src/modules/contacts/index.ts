import type { FrontendModule } from '@/app/routes'
import { contactsRoutes } from './routes'

export const contactsModule: FrontendModule = { name: 'Contacts', routes: contactsRoutes }
