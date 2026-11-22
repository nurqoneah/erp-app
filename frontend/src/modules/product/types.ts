export type ProductType = 'storable' | 'consumable' | 'service'

export const productTypeLabels: Record<ProductType, string> = {
  storable: 'Barang (stok dilacak)',
  consumable: 'Barang habis pakai',
  service: 'Jasa',
}

export type Product = {
  id: number
  name: string
  sku: string | null
  barcode: string | null
  type: ProductType
  category_id: number
  category?: { id: number; complete_name: string }
  uom_id: number
  uom?: { id: number; name: string }
  purchase_uom_id: number
  purchase_uom?: { id: number; name: string }
  sale_price: string
  cost: string
  can_be_sold: boolean
  can_be_purchased: boolean
  available_in_pos: boolean
  description: string | null
  is_active: boolean
}

export type ProductInput = {
  name: string
  sku: string | null
  barcode: string | null
  type: ProductType
  category_id: number | null
  uom_id: number | null
  purchase_uom_id: number | null
  sale_price: string
  cost: string
  can_be_sold: boolean
  can_be_purchased: boolean
  available_in_pos: boolean
  description: string | null
  is_active: boolean
}

export type ProductCategory = {
  id: number
  name: string
  parent_id: number | null
  complete_name: string
  products_count: number
}

export type UomType = 'reference' | 'bigger' | 'smaller'

export type Uom = {
  id: number
  category_id: number
  name: string
  type: UomType
  ratio: string
  rounding: string
  is_active: boolean
}

export type UomCategory = { id: number; name: string; uoms: Uom[] }
