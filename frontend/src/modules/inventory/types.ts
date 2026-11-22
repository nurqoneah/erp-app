export type PickingType = 'receipt' | 'delivery' | 'internal'
export type PickingState = 'draft' | 'done' | 'cancelled'

export const pickingTypeLabels: Record<PickingType, string> = {
  receipt: 'Penerimaan',
  delivery: 'Pengiriman',
  internal: 'Transfer internal',
}

export type Picking = {
  id: number
  reference: string
  type: PickingType
  state: PickingState
  partner_id: number | null
  partner?: { id: number; name: string } | null
  source_location_id: number
  source_location?: { id: number; complete_name: string }
  dest_location_id: number
  dest_location?: { id: number; complete_name: string }
  scheduled_date: string
  done_at: string | null
  origin: string | null
  origin_type: string | null
  origin_id: number | null
  notes: string | null
  is_editable: boolean
  moves?: {
    id: number
    product_id: number
    product: { id: number; name: string; sku: string | null; type: string; uom: string | null }
    quantity: string
    state: string
  }[]
}

export type StockRow = {
  id: number
  product: { id: number; name: string; sku: string | null; uom: string | null }
  location: { id: number; complete_name: string }
  quantity: string
}

export type Location = { id: number; complete_name: string; usage: string; warehouse_id: number | null }

export type Warehouse = {
  id: number
  name: string
  code: string
  is_active: boolean
  stock_location_id: number
  locations: { id: number; name: string; complete_name: string; is_active: boolean }[]
}

export type AdjustmentRow = {
  id: number
  reference: string
  product: { id: number; name: string; uom: string | null }
  location: string
  change: string
  done_at: string
}
