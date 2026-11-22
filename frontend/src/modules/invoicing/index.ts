import { createElement } from 'react'

import { lazyPage, type FrontendModule } from '@/app/routes'
import { invoiceKinds } from './types'

const CustomerInvoices = () => createElement(InvoicesPage, { kind: invoiceKinds.customer })
const CustomerInvoiceForm = () => createElement(InvoiceFormPage, { kind: invoiceKinds.customer })
const VendorBills = () => createElement(InvoicesPage, { kind: invoiceKinds.vendor })
const VendorBillForm = () => createElement(InvoiceFormPage, { kind: invoiceKinds.vendor })

const InvoiceFormPage = lazyPage(() => import('./pages/InvoiceFormPage'), 'InvoiceFormPage')
const InvoicesPage = lazyPage(() => import('./pages/InvoicesPage'), 'InvoicesPage')

export const invoicingModule: FrontendModule = {
  name: 'Invoicing',
  routes: [
    { path: 'invoicing/customer-invoices', component: CustomerInvoices, permission: 'invoicing.invoice.view' },
    { path: 'invoicing/customer-invoices/new', component: CustomerInvoiceForm, permission: 'invoicing.invoice.manage' },
    { path: 'invoicing/customer-invoices/:id', component: CustomerInvoiceForm, permission: 'invoicing.invoice.view' },
    { path: 'invoicing/vendor-bills', component: VendorBills, permission: 'invoicing.invoice.view' },
    { path: 'invoicing/vendor-bills/new', component: VendorBillForm, permission: 'invoicing.invoice.manage' },
    { path: 'invoicing/vendor-bills/:id', component: VendorBillForm, permission: 'invoicing.invoice.view' },
  ],
}
