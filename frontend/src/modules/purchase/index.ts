import { createElement } from 'react'

import { lazyPage, type FrontendModule } from '@/app/routes'
import type { OrderKind } from '@/components/orders/types'

const OrderFormPage = lazyPage(() => import('@/components/orders/OrderFormPage'), 'OrderFormPage')
const OrdersPage = lazyPage(() => import('@/components/orders/OrdersPage'), 'OrdersPage')

export const purchaseKind: OrderKind = {
  apiBase: '/purchase/orders',
  queryKey: 'purchase-orders',
  basePath: '/purchase/orders',
  draftListPath: '/purchase/rfqs',
  permission: { view: 'purchase.order.view', manage: 'purchase.order.manage', confirm: 'purchase.order.confirm' },
  labels: {
    draft: 'RFQ',
    order: 'Pesanan Pembelian',
    partner: 'Pemasok',
    extraDate: 'Perkiraan tiba',
    qtyDone: 'Diterima',
    qtyInvoiced: 'Ditagih',
    invoice: 'Tagihan',
    transfer: 'Penerimaan',
    createInvoice: 'Buat tagihan',
  },
  extraDateField: 'expected_date',
  partnerRole: 'vendor',
  priceField: 'cost',
  productFilters: { purchased: 1 },
  qtyDoneField: 'qty_received',
  qtyInvoicedField: 'qty_billed',
  transferStatusField: 'receipt_status',
  invoiceStatusField: 'bill_status',
  invoicesField: 'bills',
  invoiceAction: 'bill',
  invoiceIdField: 'bill_id',
  invoiceBasePath: '/invoicing/vendor-bills',
}

const Rfqs = () => createElement(OrdersPage, { kind: purchaseKind, drafts: true })
const Orders = () => createElement(OrdersPage, { kind: purchaseKind, drafts: false })
const OrderForm = () => createElement(OrderFormPage, { kind: purchaseKind })

export const purchaseModule: FrontendModule = {
  name: 'Purchase',
  routes: [
    { path: 'purchase/rfqs', component: Rfqs, permission: 'purchase.order.view' },
    { path: 'purchase/orders', component: Orders, permission: 'purchase.order.view' },
    { path: 'purchase/orders/new', component: OrderForm, permission: 'purchase.order.manage' },
    { path: 'purchase/orders/:id', component: OrderForm, permission: 'purchase.order.view' },
  ],
}
