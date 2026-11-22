import { Plus } from 'lucide-react'
import { Link, useNavigate } from 'react-router'

import { useAuth } from '@/app/auth'
import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { SearchInput } from '@/components/data/SearchInput'
import { StateBadge } from '@/components/documents/StateBadge'
import { Button } from '@/components/ui/button'
import { useListParams } from '@/hooks/useListParams'
import { formatDate, formatMoney } from '@/lib/format'
import { useOrders } from './api'
import type { Order, OrderKind } from './types'

function columns(kind: OrderKind, drafts: boolean): Column<Order>[] {
  return [
    { key: 'number', header: 'Nomor', cell: (o) => <span className="font-medium">{o.number}</span> },
    { key: 'partner', header: kind.labels.partner, cell: (o) => o.partner?.name },
    { key: 'date', header: 'Tanggal', hideOnMobile: true, cell: (o) => formatDate(o.order_date) },
    { key: 'total', header: 'Total', className: 'text-right', cell: (o) => formatMoney(o.amount_total) },
    drafts
      ? { key: 'state', header: 'Status', cell: (o) => (o.state === 'cancelled' ? <StateBadge label="Dibatalkan" tone="danger" /> : <StateBadge label={kind.labels.draft} />) }
      : {
          key: 'status',
          header: 'Status',
          cell: (o) => (
            <div className="flex flex-wrap gap-1">
              {o[kind.transferStatusField] === 'pending' && <StateBadge label={`Belum ${kind.labels.qtyDone.toLowerCase()}`} tone="warning" />}
              {o[kind.transferStatusField] === 'done' && <StateBadge label={`Sudah ${kind.labels.qtyDone.toLowerCase()}`} tone="success" />}
              {o[kind.invoiceStatusField] === `to_${kind.invoiceAction}` && <StateBadge label={`Perlu ${kind.labels.invoice.toLowerCase()}`} tone="info" />}
            </div>
          ),
        },
  ]
}

/** Daftar penawaran / RFQ (draf & dibatalkan) atau pesanan (terkonfirmasi). */
export function OrdersPage({ kind, drafts }: { kind: OrderKind; drafts: boolean }) {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [params, setParams] = useListParams()
  const { data, isLoading } = useOrders(kind, { ...params, state: drafts ? 'draft,cancelled' : 'confirmed' })
  const title = drafts ? kind.labels.draft : kind.labels.order

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <PageHeader
        title={title}
        actions={
          can(kind.permission.manage) && (
            <Button asChild>
              <Link to={`${kind.basePath}/new`}>
                <Plus /> Baru
              </Link>
            </Button>
          )
        }
      />
      <div className="mb-3">
        <SearchInput value={String(params.search ?? '')} onChange={(search) => setParams({ search })} placeholder={`Cari nomor / ${kind.labels.partner.toLowerCase()}…`} />
      </div>
      <DataTable
        columns={columns(kind, drafts)}
        page={data}
        isLoading={isLoading}
        rowKey={(o) => o.id}
        onRowClick={(o) => navigate(`${kind.basePath}/${o.id}`)}
        onPageChange={(page) => setParams({ page })}
        empty={`Belum ada ${title.toLowerCase()}.`}
      />
    </div>
  )
}
