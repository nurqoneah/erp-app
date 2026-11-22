import { lazyPage, type ModuleRoute } from '@/app/routes'

const CategoriesPage = lazyPage(() => import('./pages/CategoriesPage'), 'CategoriesPage')
const ProductFormPage = lazyPage(() => import('./pages/ProductFormPage'), 'ProductFormPage')
const ProductsPage = lazyPage(() => import('./pages/ProductsPage'), 'ProductsPage')
const UomPage = lazyPage(() => import('./pages/UomPage'), 'UomPage')

export const productRoutes: ModuleRoute[] = [
  { path: 'products', component: ProductsPage, permission: 'product.product.view' },
  { path: 'products/new', component: ProductFormPage, permission: 'product.product.manage' },
  { path: 'products/categories', component: CategoriesPage, permission: 'product.product.view' },
  { path: 'products/uom', component: UomPage, permission: 'product.product.view' },
  { path: 'products/:id', component: ProductFormPage, permission: 'product.product.view' },
]
