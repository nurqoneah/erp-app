export type InvoiceType = 'out_invoice' | 'in_invoice'

export type Invoice = {
  id: number
  type: InvoiceType
  number: string | null
  display_name: string
  partner_id: number
  partner?: { id: number; name: string }
  invoice_date: string | null
  due_date: string | null
  vendor_reference: string | null
  state: 'draft' | 'posted' | 'cancelled'
  payment_state: 'not_paid' | 'partial' | 'paid'
  origin: string | null
  origin_type: string | null
  origin_id: number | null
  amount_untaxed: string
  amount_tax: string
  amount_total: string
  amount_paid: string
  amount_due: string
  notes: string | null
  lines?: {
    id: number
    product_id: number | null
    product: { id: number; name: string; uom: string | null } | null
    description: string
    quantity: string
    unit_price: string
    discount: string
    tax_rate: string
    subtotal: string
  }[]
  payments?: { id: number; date: string; amount: string; method: 'cash' | 'bank' | 'other'; reference: string | null }[]
}

/** Konfigurasi halaman per jenis faktur. */
export type InvoiceKind = {
  type: InvoiceType
  basePath: string
  title: string
  singular: string
  partnerLabel: string
  partnerRole: 'customer' | 'vendor'
  priceField: 'sale_price' | 'cost'
  productFilters: Record<string, number>
}

export const invoiceKinds: Record<'customer' | 'vendor', InvoiceKind> = {
  customer: {
    type: 'out_invoice',
    basePath: '/invoicing/customer-invoices',
    title: 'Faktur Pelanggan',
    singular: 'Faktur',
    partnerLabel: 'Pelanggan',
    partnerRole: 'customer',
    priceField: 'sale_price',
    productFilters: { sold: 1 },
  },
  vendor: {
    type: 'in_invoice',
    basePath: '/invoicing/vendor-bills',
    title: 'Tagihan Pemasok',
    singular: 'Tagihan',
    partnerLabel: 'Pemasok',
    partnerRole: 'vendor',
    priceField: 'cost',
    productFilters: { purchased: 1 },
  },
}

export const paymentMethodLabels = { cash: 'Tunai', bank: 'Transfer bank', other: 'Lainnya' } as const
