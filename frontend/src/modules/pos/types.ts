export type PaymentMethod = { id: number; name: string; type: 'cash' | 'bank'; sequence?: number; is_active?: boolean }

export type PosConfig = {
  id: number
  name: string
  warehouse: { id: number; name: string }
  payment_methods: PaymentMethod[]
  receipt_header: string | null
  receipt_footer: string | null
  set_maximum_difference: boolean
  amount_authorized_diff: string
  is_active: boolean
  cash_control: boolean
  current_session: { id: number; name: string | null; state: SessionState; user: string; opened_at: string | null } | null
  last_closing: { closed_at: string; cash: string } | null
}

export type SessionState = 'opening_control' | 'opened' | 'closing_control' | 'closed'

export const sessionStateLabels: Record<SessionState, string> = {
  opening_control: 'Kontrol Pembukaan',
  opened: 'Berjalan',
  closing_control: 'Kontrol Penutupan',
  closed: 'Ditutup',
}

export type PosSession = {
  id: number
  name: string
  state: SessionState
  config?: { id: number; name: string }
  user?: { id: number; name: string }
  opened_at: string | null
  closed_at: string | null
  cash_register_balance_start: string
  cash_register_balance_end: string
  cash_register_balance_end_real: string
  cash_register_difference: string
  opening_notes: string | null
  closing_notes: string | null
  orders_count?: number
  orders_amount?: string
}

export type SessionSummary = {
  orders: { count: number; amount: number }
  cash: {
    payment_method_id: number
    name: string
    opening: number
    payments: number
    moves: { id: number; amount: number; reason: string; created_at: string }[]
    expected: number
  } | null
  non_cash: { payment_method_id: number; name: string; amount: number; count: number }[]
  max_difference: number | null
}

export type PosProduct = {
  id: number
  name: string
  sku: string | null
  barcode: string | null
  price: string
  type: string
  uom: string | null
  category_id: number
  category: string | null
}

export type PosOrderState = 'draft' | 'paid' | 'done' | 'cancel'

export const orderStateLabels: Record<PosOrderState, string> = { draft: 'Baru', paid: 'Dibayar', done: 'Diposting', cancel: 'Dibatalkan' }

export type PosOrder = {
  id: number
  name: string
  state: PosOrderState
  session_id: number
  session?: { id: number; name: string }
  config?: { id: number; name: string }
  user?: { id: number; name: string }
  partner_id: number | null
  partner?: { id: number; name: string } | null
  date_order: string
  amount_untaxed: string
  amount_tax: string
  amount_total: string
  amount_paid: string
  amount_return: string
  to_invoice: boolean
  invoice?: { id: number; number: string | null } | null
  is_refund: boolean
  refunded_order?: { id: number; name: string } | null
  notes: string | null
  lines?: {
    id: number
    product_id: number
    product: { id: number; name: string; uom: string | null }
    description: string
    quantity: string
    unit_price: string
    discount: string
    tax_rate: string
    subtotal: string
    total: string
    refundable_quantity: number | null
  }[]
  payments?: { id: number; method: PaymentMethod; amount: string; is_change: boolean }[]
  pickings?: { id: number; reference: string; state: string }[]
}
