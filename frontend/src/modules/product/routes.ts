import type { ModuleRoute } from '@/app/routes'
import { CategoriesPage } from './pages/CategoriesPage'
import { ProductFormPage } from './pages/ProductFormPage'
import { ProductsPage } from './pages/ProductsPage'
import { UomPage } from './pages/UomPage'

export const productRoutes: ModuleRoute[] = [
  { path: 'products', component: ProductsPage, permission: 'product.product.view' },
  { path: 'products/new', component: ProductFormPage, permission: 'product.product.manage' },
  { path: 'products/categories', component: CategoriesPage, permission: 'product.product.view' },
  { path: 'products/uom', component: UomPage, permission: 'product.product.view' },
  { path: 'products/:id', component: ProductFormPage, permission: 'product.product.view' },
]
