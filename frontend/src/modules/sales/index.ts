import { createElement } from 'react'

import { lazyPage, type FrontendModule } from '@/app/routes'
import type { OrderKind } from '@/components/orders/types'

const OrderFormPage = lazyPage(() => import('@/components/orders/OrderFormPage'), 'OrderFormPage')
const OrdersPage = lazyPage(() => import('@/components/orders/OrdersPage'), 'OrdersPage')

export const salesKind: OrderKind = {
  apiBase: '/sales/orders',
  queryKey: 'sales-orders',
  basePath: '/sales/orders',
  draftListPath: '/sales/quotations',
  permission: { view: 'sales.order.view', manage: 'sales.order.manage', confirm: 'sales.order.confirm' },
  labels: {
    draft: 'Penawaran',
    order: 'Pesanan',
    partner: 'Pelanggan',
    extraDate: 'Berlaku sampai',
    qtyDone: 'Terkirim',
    qtyInvoiced: 'Difakturkan',
    invoice: 'Faktur',
    transfer: 'Pengiriman',
    createInvoice: 'Buat faktur',
  },
  extraDateField: 'validity_date',
  partnerRole: 'customer',
  priceField: 'sale_price',
  productFilters: { sold: 1 },
  qtyDoneField: 'qty_delivered',
  qtyInvoicedField: 'qty_invoiced',
  transferStatusField: 'delivery_status',
  invoiceStatusField: 'invoice_status',
  invoicesField: 'invoices',
  invoiceAction: 'invoice',
  invoiceIdField: 'invoice_id',
  invoiceBasePath: '/invoicing/customer-invoices',
}

const Quotations = () => createElement(OrdersPage, { kind: salesKind, drafts: true })
const Orders = () => createElement(OrdersPage, { kind: salesKind, drafts: false })
const OrderForm = () => createElement(OrderFormPage, { kind: salesKind })

export const salesModule: FrontendModule = {
  name: 'Sales',
  routes: [
    { path: 'sales/quotations', component: Quotations, permission: 'sales.order.view' },
    { path: 'sales/orders', component: Orders, permission: 'sales.order.view' },
    { path: 'sales/orders/new', component: OrderForm, permission: 'sales.order.manage' },
    { path: 'sales/orders/:id', component: OrderForm, permission: 'sales.order.view' },
  ],
}
