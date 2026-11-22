import { formatMoney, formatNumber } from '@/lib/format'
import type { PosOrder } from '../types'

const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'short', timeStyle: 'short' })

/** Struk kasir. Saat dicetak hanya elemen ini yang tampil (lihat .print-area di index.css). */
export function Receipt({ order, companyName, header, footer }: { order: PosOrder; companyName: string; header?: string | null; footer?: string | null }) {
  const payments = order.payments?.filter((p) => !p.is_change) ?? []

  return (
    <div className="print-area mx-auto w-full max-w-xs bg-white p-4 font-mono text-xs leading-relaxed text-black shadow-sm" data-testid="receipt">
      <div className="text-center">
        <div className="text-sm font-bold">{companyName}</div>
        {header && <div className="whitespace-pre-line">{header}</div>}
        <div className="mt-1">{order.name}</div>
        <div>{dateTime.format(new Date(order.date_order))}</div>
        {order.user && <div>Kasir: {order.user.name}</div>}
        {order.partner && <div>Pelanggan: {order.partner.name}</div>}
        {order.is_refund && <div className="font-bold">*** RETUR {order.refunded_order?.name} ***</div>}
      </div>
      <hr className="my-2 border-dashed border-black" />
      {order.lines?.map((line) => (
        <div key={line.id} className="mb-1">
          <div>{line.description}</div>
          <div className="flex justify-between">
            <span>
              {formatNumber(line.quantity)} x {formatMoney(line.unit_price)}
              {Number(line.discount) > 0 && ` (-${formatNumber(line.discount)}%)`}
            </span>
            <span>{formatMoney(line.total)}</span>
          </div>
        </div>
      ))}
      <hr className="my-2 border-dashed border-black" />
      <div className="flex justify-between text-sm font-bold">
        <span>TOTAL</span>
        <span>{formatMoney(order.amount_total)}</span>
      </div>
      {payments.map((p) => (
        <div key={p.id} className="flex justify-between">
          <span>{p.method.name}</span>
          <span>{formatMoney(p.amount)}</span>
        </div>
      ))}
      {Number(order.amount_return) > 0 && (
        <div className="flex justify-between font-bold">
          <span>KEMBALI</span>
          <span>{formatMoney(order.amount_return)}</span>
        </div>
      )}
      {order.invoice?.number && <div className="mt-1 text-center">Faktur: {order.invoice.number}</div>}
      {footer && (
        <>
          <hr className="my-2 border-dashed border-black" />
          <div className="text-center whitespace-pre-line">{footer}</div>
        </>
      )}
    </div>
  )
}
