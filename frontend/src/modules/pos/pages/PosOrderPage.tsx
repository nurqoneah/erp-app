import { FileText, Truck } from 'lucide-react'
import { Link, useParams } from 'react-router'

import { NotFound } from '@/components/data/NotFound'
import { PageHeader } from '@/components/data/PageHeader'
import { AmountSummary } from '@/components/documents/AmountSummary'
import { SmartButtons } from '@/components/documents/SmartButtons'
import { StateBadge } from '@/components/documents/StateBadge'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatMoney, formatNumber } from '@/lib/format'
import { usePosOrder } from '../api'
import { orderStateLabels } from '../types'

const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' })

export function PosOrderPage() {
  const { id } = useParams()
  const { data: order, isLoading, isError } = usePosOrder(Number(id))

  if (isLoading) return <Skeleton className="m-6 h-96" />
  if (isError || !order) return <NotFound backTo="/pos/orders" />

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <PageHeader crumbs={[{ label: 'Pesanan POS', to: '/pos/orders' }]} title={order.name} />
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <StateBadge label={orderStateLabels[order.state]} tone={order.state === 'done' ? 'success' : 'info'} />
          {order.is_refund && order.refunded_order && (
            <span className="text-sm">
              Retur dari{' '}
              <Link to={`/pos/orders/${order.refunded_order.id}`} className="text-primary hover:underline">
                {order.refunded_order.name}
              </Link>
            </span>
          )}
        </div>
        <SmartButtons
          items={[
            { label: 'Transfer', count: order.pickings?.length ?? 0, to: order.pickings?.length === 1 ? `/inventory/transfers/${order.pickings[0].id}` : `/inventory/transfers?search=${encodeURIComponent(order.name)}&state=all`, icon: <Truck /> },
            { label: 'Faktur', count: order.invoice ? 1 : 0, to: `/invoicing/customer-invoices/${order.invoice?.id}`, icon: <FileText /> },
          ]}
        />
      </div>
      <Card>
        <CardContent className="grid gap-6">
          <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
            <Info label="Tanggal" value={dateTime.format(new Date(order.date_order))} />
            <Info label="Sesi" value={order.session ? <Link className="text-primary hover:underline" to={`/pos/sessions/${order.session.id}`}>{order.session.name}</Link> : '-'} />
            <Info label="Kasir" value={order.user?.name ?? '-'} />
            <Info label="Pelanggan" value={order.partner?.name ?? '-'} />
          </dl>
          <div className="min-w-0 rounded-md border">
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead>Produk</TableHead>
                  <TableHead className="text-right">Jumlah</TableHead>
                  <TableHead className="text-right">Harga</TableHead>
                  <TableHead className="text-right">Diskon %</TableHead>
                  <TableHead className="text-right">Subtotal</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {order.lines?.map((l) => (
                  <TableRow key={l.id}>
                    <TableCell className="font-medium">{l.description}</TableCell>
                    <TableCell className="text-right tabular-nums">
                      {formatNumber(l.quantity)} {l.product.uom}
                    </TableCell>
                    <TableCell className="text-right tabular-nums">{formatMoney(l.unit_price)}</TableCell>
                    <TableCell className="text-right tabular-nums">{formatNumber(l.discount)}</TableCell>
                    <TableCell className="text-right tabular-nums">{formatMoney(l.subtotal)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
          <div className="flex flex-wrap items-start gap-6">
            <div className="min-w-56 flex-1">
              <h2 className="mb-1 text-sm font-semibold">Pembayaran</h2>
              <ul className="divide-y text-sm">
                {order.payments?.map((p) => (
                  <li key={p.id} className="flex justify-between py-1.5">
                    <span>{p.is_change ? `Kembalian (${p.method.name})` : p.method.name}</span>
                    <span className="tabular-nums">{formatMoney(p.amount)}</span>
                  </li>
                ))}
              </ul>
            </div>
            <AmountSummary untaxed={order.amount_untaxed} tax={order.amount_tax} total={order.amount_total} />
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

function Info({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div>
      <dt className="text-muted-foreground text-xs">{label}</dt>
      <dd className="font-medium">{value}</dd>
    </div>
  )
}
