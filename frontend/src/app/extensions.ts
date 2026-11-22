import type { ComponentType } from 'react'

/**
 * Konteks yang diterima komponen perluasan form.
 * `values` berisi semua field record (termasuk field tambahan dari modul perluasan).
 */
export type FormExtensionProps = {
  /** ID record yang sedang dibuka (kosong untuk record baru). */
  recordId?: number
  values: Record<string, unknown>
  set: (field: string, value: unknown) => void
  errors: Record<string, string>
  readOnly: boolean
}

type SlotEntry = {
  /** Modul pemilik perluasan; hanya tampil bila modul ini terpasang. */
  module: string
  component: ComponentType<FormExtensionProps>
  order?: number
}

const slots = new Map<string, SlotEntry[]>()

/**
 * Daftarkan komponen ke titik perluasan (slot), mirip pewarisan view di Odoo.
 *
 * Slot yang tersedia:
 * - `contacts.partner.form` — bagian tambahan di form Kontak
 * - `product.product.form`  — bagian tambahan di form Produk
 */
export function registerSlot(point: string, entry: SlotEntry) {
  const list = slots.get(point) ?? []
  list.push(entry)
  list.sort((a, b) => (a.order ?? 100) - (b.order ?? 100))
  slots.set(point, list)
}

export function slotEntries(point: string): SlotEntry[] {
  return slots.get(point) ?? []
}
