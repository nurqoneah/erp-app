import { formatMoney } from '@/lib/format'

export function AmountSummary({
  untaxed,
  tax,
  total,
  extra = [],
}: {
  untaxed: number | string
  tax: number | string
  total: number | string
  /** Baris tambahan di bawah total, mis. sudah dibayar / sisa. */
  extra?: { label: string; value: number | string; strong?: boolean }[]
}) {
  return (
    <dl className="ml-auto grid w-full max-w-xs grid-cols-[1fr_auto] gap-y-1 text-sm [&_dd]:pl-6">
      <dt className="text-muted-foreground">Subtotal</dt>
      <dd className="text-right tabular-nums">{formatMoney(untaxed)}</dd>
      <dt className="text-muted-foreground">Pajak</dt>
      <dd className="text-right tabular-nums">{formatMoney(tax)}</dd>
      <dt className="border-t pt-1 font-semibold">Total</dt>
      <dd className="border-t pt-1 text-right text-base font-semibold tabular-nums">{formatMoney(total)}</dd>
      {extra.map((row) => (
        <div key={row.label} className="contents">
          <dt className={row.strong ? 'font-semibold' : 'text-muted-foreground'}>{row.label}</dt>
          <dd className={'text-right tabular-nums ' + (row.strong ? 'font-semibold' : '')}>{formatMoney(row.value)}</dd>
        </div>
      ))}
    </dl>
  )
}
