import { ShoppingBag } from 'lucide-react'
import { useParams } from 'react-router'

import { NotFound } from '@/components/data/NotFound'
import { PageHeader } from '@/components/data/PageHeader'
import { SmartButtons } from '@/components/documents/SmartButtons'
import { StateBadge } from '@/components/documents/StateBadge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { formatMoney } from '@/lib/format'
import { cn } from '@/lib/utils'
import { useSession } from '../api'
import { sessionStateLabels } from '../types'

const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' })

export function PosSessionPage() {
  const { id } = useParams()
  const { data, isLoading, isError } = useSession(Number(id))

  if (isLoading) return <Skeleton className="m-6 h-96" />
  if (isError || !data) return <NotFound backTo="/pos/sessions" />

  const { data: session, summary } = data
  const closed = session.state === 'closed'

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <PageHeader crumbs={[{ label: 'Sesi Kasir', to: '/pos/sessions' }]} title={session.name} />
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <StateBadge label={sessionStateLabels[session.state]} tone={closed ? 'neutral' : 'success'} />
        <SmartButtons items={[{ label: 'Pesanan', count: summary.orders.count, to: `/pos/orders?session_id=${session.id}`, icon: <ShoppingBag /> }]} />
      </div>
      <div className="grid gap-4 md:grid-cols-2">
        <Card className="gap-3">
          <CardHeader>
            <CardTitle>Sesi</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-1 text-sm">
            <Row label="Point of Sale" value={session.config?.name ?? '-'} />
            <Row label="Dibuka oleh" value={session.user?.name ?? '-'} />
            <Row label="Dibuka" value={session.opened_at ? dateTime.format(new Date(session.opened_at)) : '-'} />
            <Row label="Ditutup" value={session.closed_at ? dateTime.format(new Date(session.closed_at)) : '-'} />
            <Row label="Penjualan" value={`${summary.orders.count} pesanan · ${formatMoney(summary.orders.amount)}`} />
            {session.opening_notes && <Row label="Catatan pembukaan" value={session.opening_notes} />}
            {session.closing_notes && <Row label="Catatan penutupan" value={session.closing_notes} />}
          </CardContent>
        </Card>
        <Card className="gap-3">
          <CardHeader>
            <CardTitle>Kas & pembayaran</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-1 text-sm">
            {summary.cash && (
              <>
                <Row label="Kas awal" value={formatMoney(summary.cash.opening)} />
                <Row label={`${summary.cash.name} (bersih kembalian)`} value={formatMoney(summary.cash.payments)} />
                {summary.cash.moves.map((m) => (
                  <Row key={m.id} label={`${m.amount > 0 ? 'Kas masuk' : 'Kas keluar'}: ${m.reason}`} value={formatMoney(m.amount)} />
                ))}
                <Row label="Kas seharusnya" value={formatMoney(closed ? session.cash_register_balance_end : summary.cash.expected)} strong />
                {closed && (
                  <>
                    <Row label="Kas terhitung" value={formatMoney(session.cash_register_balance_end_real)} />
                    <Row
                      label="Selisih"
                      value={formatMoney(session.cash_register_difference)}
                      className={Number(session.cash_register_difference) !== 0 ? 'text-destructive' : ''}
                      strong
                    />
                  </>
                )}
              </>
            )}
            {summary.non_cash.map((m) => (
              <Row key={m.payment_method_id} label={`${m.name} (${m.count})`} value={formatMoney(m.amount)} />
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

function Row({ label, value, strong, className }: { label: string; value: React.ReactNode; strong?: boolean; className?: string }) {
  return (
    <div className={cn('flex justify-between gap-4', strong && 'border-t pt-1 font-semibold', className)}>
      <span className={strong ? '' : 'text-muted-foreground'}>{label}</span>
      <span className="text-right tabular-nums">{value}</span>
    </div>
  )
}
