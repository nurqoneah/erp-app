/** Perhitungan nominal baris dokumen di sisi klien (sama dengan backend LineAmounts). */
export function round(value: number, digits = 2): number {
  const factor = 10 ** digits
  return Math.round((value + Number.EPSILON) * factor) / factor
}

export function lineAmounts(quantity: unknown, unitPrice: unknown, discount: unknown = 0, taxRate: unknown = 0) {
  const subtotal = round(Number(quantity || 0) * Number(unitPrice || 0) * (1 - Number(discount || 0) / 100))
  const tax = round((subtotal * Number(taxRate || 0)) / 100)
  return { subtotal, tax, total: round(subtotal + tax) }
}

export function documentTotals(lines: { quantity: unknown; unit_price: unknown; discount?: unknown; tax_rate?: unknown }[]) {
  let untaxed = 0
  let tax = 0
  for (const line of lines) {
    const amounts = lineAmounts(line.quantity, line.unit_price, line.discount, line.tax_rate)
    untaxed += amounts.subtotal
    tax += amounts.tax
  }
  return { untaxed: round(untaxed), tax: round(tax), total: round(untaxed + tax) }
}
