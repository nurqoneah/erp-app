import { registerSlot } from '@/app/extensions'
import { lazyPage, type FrontendModule } from '@/app/routes'
import { ProductStockInfo } from './ProductStockInfo'

const AdjustmentsPage = lazyPage(() => import('./pages/AdjustmentsPage'), 'AdjustmentsPage')
const StockPage = lazyPage(() => import('./pages/StockPage'), 'StockPage')
const TransferFormPage = lazyPage(() => import('./pages/TransferFormPage'), 'TransferFormPage')
const TransfersPage = lazyPage(() => import('./pages/TransfersPage'), 'TransfersPage')
const WarehousesPage = lazyPage(() => import('./pages/WarehousesPage'), 'WarehousesPage')

export const inventoryModule: FrontendModule = {
  name: 'Inventory',
  routes: [
    { path: 'inventory/stock', component: StockPage, permission: 'inventory.stock.view' },
    { path: 'inventory/transfers', component: TransfersPage, permission: 'inventory.transfer.view' },
    { path: 'inventory/transfers/new', component: TransferFormPage, permission: 'inventory.transfer.manage' },
    { path: 'inventory/transfers/:id', component: TransferFormPage, permission: 'inventory.transfer.view' },
    { path: 'inventory/adjustments', component: AdjustmentsPage, permission: 'inventory.adjustment.manage' },
    { path: 'inventory/warehouses', component: WarehousesPage, permission: 'inventory.warehouse.manage' },
  ],
  setup: () => registerSlot('product.product.form', { module: 'Inventory', component: ProductStockInfo, order: 10 }),
}
