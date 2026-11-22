import { useEffect, useReducer } from 'react'

import { lineAmounts, round } from '@/components/documents/amounts'

export type CartLine = {
  key: string
  product: { id: number; name: string; uom: string | null }
  quantity: number
  unit_price: number
  discount: number
  /** Diisi untuk baris retur: baris pesanan asal. */
  refunded_line_id?: number | null
  /** Baris retur: jumlah maksimal yang boleh dikembalikan. */
  refund_max?: number
}

export type CartState = {
  lines: CartLine[]
  selectedKey: string | null
  partner: { id: number; name: string } | null
  toInvoice: boolean
  /** Pesanan asal bila keranjang ini adalah retur. */
  refundOf: { id: number; name: string } | null
}

export type NumpadMode = 'quantity' | 'discount' | 'unit_price'

type Action =
  | { type: 'add'; product: CartLine['product'] & { price: number } }
  | { type: 'select'; key: string }
  | { type: 'set'; key: string; field: NumpadMode; value: number }
  | { type: 'remove'; key: string }
  | { type: 'partner'; partner: CartState['partner'] }
  | { type: 'toInvoice'; value: boolean }
  | { type: 'loadRefund'; order: { id: number; name: string }; lines: Omit<CartLine, 'key'>[]; partner: CartState['partner'] }
  | { type: 'clear' }

export const emptyCart: CartState = { lines: [], selectedKey: null, partner: null, toInvoice: false, refundOf: null }

let counter = 0
const newKey = () => `l${Date.now().toString(36)}${(counter++).toString(36)}`

function reducer(state: CartState, action: Action): CartState {
  switch (action.type) {
    case 'add': {
      if (state.refundOf) return state // keranjang retur tidak bisa ditambah produk baru
      // Seperti Odoo: produk yang sama (tanpa diskon/harga berbeda) menambah jumlah baris yang ada.
      const existing = state.lines.find((l) => l.product.id === action.product.id && l.discount === 0 && l.unit_price === action.product.price)
      if (existing) {
        return {
          ...state,
          selectedKey: existing.key,
          lines: state.lines.map((l) => (l.key === existing.key ? { ...l, quantity: round(l.quantity + 1, 4) } : l)),
        }
      }
      const line: CartLine = {
        key: newKey(),
        product: { id: action.product.id, name: action.product.name, uom: action.product.uom },
        quantity: 1,
        unit_price: action.product.price,
        discount: 0,
      }
      return { ...state, lines: [...state.lines, line], selectedKey: line.key }
    }
    case 'select':
      return { ...state, selectedKey: action.key }
    case 'set':
      return { ...state, lines: state.lines.map((l) => (l.key === action.key ? { ...l, [action.field]: action.value } : l)) }
    case 'remove': {
      const lines = state.lines.filter((l) => l.key !== action.key)
      return { ...state, lines, selectedKey: lines.at(-1)?.key ?? null, refundOf: lines.length ? state.refundOf : null }
    }
    case 'partner':
      return { ...state, partner: action.partner, toInvoice: action.partner ? state.toInvoice : false }
    case 'toInvoice':
      return { ...state, toInvoice: action.value }
    case 'loadRefund': {
      const lines = action.lines.map((l) => ({ ...l, key: newKey() }))
      return { lines, selectedKey: lines[0]?.key ?? null, partner: action.partner, toInvoice: false, refundOf: action.order }
    }
    case 'clear':
      return emptyCart
  }
}

export function cartTotals(lines: CartLine[]) {
  let untaxed = 0
  let count = 0
  for (const line of lines) {
    untaxed += lineAmounts(line.quantity, line.unit_price, line.discount).subtotal
    count += line.quantity
  }
  return { total: round(untaxed), count: round(count, 4) }
}

/** Keranjang kasir yang disimpan di browser per sesi (bertahan saat halaman dimuat ulang). */
export function useCart(sessionId: number) {
  const storageKey = `erp.pos.cart.${sessionId}`
  const [state, dispatch] = useReducer(reducer, storageKey, (key) => {
    try {
      const saved = localStorage.getItem(key)
      return saved ? { ...emptyCart, ...(JSON.parse(saved) as CartState) } : emptyCart
    } catch {
      return emptyCart
    }
  })

  useEffect(() => {
    try {
      localStorage.setItem(storageKey, JSON.stringify(state))
    } catch {
      // penyimpanan penuh / diblokir: keranjang tetap berjalan di memori
    }
  }, [state, storageKey])

  return [state, dispatch] as const
}

export type CartDispatch = ReturnType<typeof useCart>[1]
