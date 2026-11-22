/** Data pesanan (penjualan / pembelian) dalam bentuk umum. */
export type Order = {
  id: number
  number: string
  state: 'draft' | 'confirmed' | 'cancelled'
  partner_id: number
  partner?: { id: number; name: string }
  order_date: string
  validity_date?: string | null
  expected_date?: string | null
  amount_untaxed: string
  amount_tax: string
  amount_total: string
  notes: string | null
  /** Penjualan: delivery_status/invoice_status; pembelian: receipt_status/bill_status. */
  [key: string]: unknown
  lines?: (Record<string, unknown> & {
    id: number
    product_id: number
    product: { id: number; name: string; type: string; uom: string | null }
    description: string
    quantity: string
    unit_price: string
    discount: string
    tax_rate: string
  })[]
  pickings?: { id: number; reference: string; state: string }[]
}

export type RelatedInvoice = { id: number; display_name: string; state: string; payment_state: string; amount_total: string }

/**
 * Konfigurasi modul pesanan. Penjualan dan Pembelian memakai halaman yang sama
 * dengan istilah, endpoint, dan field yang berbeda.
 */
export type OrderKind = {
  apiBase: string
  queryKey: string
  basePath: string
  draftListPath: string
  permission: { view: string; manage: string; confirm: string }
  labels: {
    draft: string
    order: string
    partner: string
    extraDate: string
    qtyDone: string
    qtyInvoiced: string
    invoice: string
    transfer: string
    createInvoice: string
  }
  extraDateField: 'validity_date' | 'expected_date'
  partnerRole: 'customer' | 'vendor'
  priceField: 'sale_price' | 'cost'
  productFilters: Record<string, number>
  qtyDoneField: string
  qtyInvoicedField: string
  transferStatusField: string
  invoiceStatusField: string
  invoicesField: string
  invoiceAction: string
  invoiceIdField: string
  invoiceBasePath: string
}
