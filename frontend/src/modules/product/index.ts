import type { FrontendModule } from '@/app/routes'
import { productRoutes } from './routes'

export const productModule: FrontendModule = { name: 'Product', routes: productRoutes }
