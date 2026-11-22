import type { FrontendModule } from '@/app/routes'
import { baseRoutes } from './routes'

export const baseModule: FrontendModule = { name: 'Base', routes: baseRoutes }
