import type { PickerOption } from '@/components/form/RecordPicker'

export type EditableLine = {
  /** Kunci lokal untuk React. */
  key: string
  id?: number
  product: PickerOption | null
  description: string
  quantity: string
  unit_price: string
  discount: string
  tax_rate: string
}

let counter = 0
export function newLine(partial: Partial<EditableLine> = {}): EditableLine {
  counter += 1
  return { key: `new-${counter}`, product: null, description: '', quantity: '1', unit_price: '0', discount: '0', tax_rate: '0', ...partial }
}

type ApiLine = {
  id?: number
  product_id: number | null
  product?: { id: number; name: string; uom?: string | null; type?: string } | null
  description?: string
  quantity: string | number
  unit_price?: string | number
  discount?: string | number
  tax_rate?: string | number
}

/** Ubah baris dari API menjadi baris yang bisa diedit. */
export function toEditable(line: ApiLine): EditableLine {
  return newLine({
    key: `line-${line.id ?? counter}`,
    id: line.id,
    product: line.product ? { id: line.product.id, label: line.product.name, hint: line.product.uom ?? undefined, data: { name: line.product.name, type: line.product.type } } : null,
    description: line.description ?? '',
    quantity: String(Number(line.quantity)),
    unit_price: String(Number(line.unit_price ?? 0)),
    discount: String(Number(line.discount ?? 0)),
    tax_rate: String(Number(line.tax_rate ?? 0)),
  })
}

/** Ubah baris yang diedit menjadi payload API. */
export function toPayload(lines: EditableLine[], withPrices = true) {
  return lines.map((line) => ({
    ...(line.id ? { id: line.id } : {}),
    product_id: line.product?.id ?? null,
    ...(withPrices
      ? { description: line.description || null, unit_price: line.unit_price || '0', discount: line.discount || '0', tax_rate: line.tax_rate || '0' }
      : {}),
    quantity: line.quantity || '0',
  }))
}
