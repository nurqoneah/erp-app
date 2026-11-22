const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 })
const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 6 })
const date = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' })

export function formatMoney(value: string | number | null | undefined): string {
  const n = Number(value ?? 0)
  return money.format(Number.isFinite(n) ? n : 0).replace(/,00$/, '')
}

export function formatNumber(value: string | number | null | undefined): string {
  const n = Number(value ?? 0)
  return number.format(Number.isFinite(n) ? n : 0)
}

export function formatDate(value: string | null | undefined): string {
  return value ? date.format(new Date(value)) : '-'
}
