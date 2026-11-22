import { useState } from 'react'
import { ArrowLeft, Printer, Undo2 } from 'lucide-react'

import { SearchInput } from '@/components/data/SearchInput'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { formatMoney, formatNumber } from '@/lib/format'
import { cn } from '@/lib/utils'
import { usePosOrder, usePosOrders } from '../api'
import type { PosOrder } from '../types'
import type { CartLine } from './cart'

const time = new Intl.DateTimeFormat('id-ID', { dateStyle: 'short', timeStyle: 'short' })

/** Riwayat pesanan POS: cetak ulang struk atau retur barang. */
export function OrdersScreen({
  configId,
  onBack,
  onReprint,
  onRefund,
}: {
  configId: number
  onBack: () => void
  onReprint: (order: PosOrder) => void
  onRefund: (order: PosOrder, lines: Omit<CartLine, 'key'>[]) => void
}) {
  const [search, setSearch] = useState('')
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const { data, isLoading } = usePosOrders({ config_id: configId, search, per_page: 30 })
  const { data: order } = usePosOrder(selectedId ?? undefined)
  const [refundQty, setRefundQty] = useState<Record<number, string>>({})

  const refundLines =
    order?.lines
      ?.filter((l) => Number(refundQty[l.id] || 0) > 0)
      .map((l) => ({
        product: { id: l.product.id, name: l.product.name, uom: l.product.uom },
        quantity: -Number(refundQty[l.id]),
        unit_price: Number(l.unit_price),
        discount: Number(l.discount),
        refunded_line_id: l.id,
        refund_max: l.refundable_quantity ?? 0,
      })) ?? []

  return (
    <div className="grid h-full min-h-0 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
      <div className="flex min-h-0 flex-col gap-3">
        <div className="flex items-center gap-2">
          <Button variant="ghost" onClick={onBack}>
            <ArrowLeft /> Kembali
          </Button>
          <SearchInput value={search} onChange={setSearch} placeholder="Cari nomor / pelanggan…" className="sm:w-auto sm:flex-1" />
        </div>
        <ul className="min-h-0 flex-1 divide-y overflow-y-auto rounded-lg border">
          {isLoading && <Skeleton className="m-3 h-20" />}
          {data?.data.map((o) => (
            <li key={o.id}>
              <button
                type="button"
                onClick={() => {
                  setSelectedId(o.id)
                  setRefundQty({})
                }}
                className={cn('hover:bg-muted flex w-full flex-wrap items-center gap-x-3 px-3 py-2 text-left text-sm', selectedId === o.id && 'bg-accent')}
              >
                <span className="font-medium">{o.name}</span>
                {o.is_refund && <Badge variant="outline">Retur</Badge>}
                <span className="text-muted-foreground">{time.format(new Date(o.date_order))}</span>
                <span className="text-muted-foreground">{o.partner?.name}</span>
                <span className="ml-auto font-medium tabular-nums">{formatMoney(o.amount_total)}</span>
              </button>
            </li>
          ))}
          {data && data.data.length === 0 && <li className="text-muted-foreground p-6 text-center text-sm">Tidak ada pesanan.</li>}
        </ul>
      </div>

      <div className="flex min-h-0 flex-col gap-3 overflow-y-auto">
        {!order ? (
          <p className="text-muted-foreground p-6 text-center text-sm">Pilih pesanan untuk melihat detail.</p>
        ) : (
          <>
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="flex-1 text-lg font-semibold">{order.name}</h2>
              <Button variant="outline" onClick={() => onReprint(order)}>
                <Printer /> Cetak struk
              </Button>
            </div>
            <ul className="divide-y rounded-lg border text-sm">
              {order.lines?.map((line) => (
                <li key={line.id} className="flex flex-wrap items-center gap-2 px-3 py-2">
                  <div className="min-w-40 flex-1">
                    <div className="font-medium">{line.description}</div>
                    <div className="text-muted-foreground text-xs">
                      {formatNumber(line.quantity)} x {formatMoney(line.unit_price)}
                      {!order.is_refund && ` · bisa diretur ${formatNumber(line.refundable_quantity ?? 0)}`}
                    </div>
                  </div>
                  {!order.is_refund && (line.refundable_quantity ?? 0) > 0 && (
                    <Input
                      type="number"
                      min={0}
                      max={line.refundable_quantity ?? 0}
                      step="any"
                      value={refundQty[line.id] ?? ''}
                      placeholder="0"
                      onChange={(e) => {
                        const max = line.refundable_quantity ?? 0
                        const value = Math.min(Number(e.target.value || 0), max)
                        setRefundQty({ ...refundQty, [line.id]: e.target.value === '' ? '' : String(value) })
                      }}
                      aria-label={`Jumlah retur ${line.description}`}
                      className="h-8 w-24 text-right"
                    />
                  )}
                  <span className="w-28 text-right tabular-nums">{formatMoney(line.total)}</span>
                </li>
              ))}
            </ul>
            {!order.is_refund && (
              <Button disabled={refundLines.length === 0} onClick={() => onRefund(order, refundLines)}>
                <Undo2 /> Retur {refundLines.length > 0 && `(${refundLines.length} produk)`}
              </Button>
            )}
          </>
        )}
      </div>
    </div>
  )
}
