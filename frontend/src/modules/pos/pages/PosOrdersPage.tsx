import { useNavigate } from 'react-router'

import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { SearchInput } from '@/components/data/SearchInput'
import { StateBadge } from '@/components/documents/StateBadge'
import { Badge } from '@/components/ui/badge'
import { NativeSelect } from '@/components/ui/native-select'
import { useListParams } from '@/hooks/useListParams'
import { formatMoney } from '@/lib/format'
import { usePosOrders } from '../api'
import { orderStateLabels, type PosOrder } from '../types'

const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' })

const columns: Column<PosOrder>[] = [
  {
    key: 'name',
    header: 'Nomor',
    sortKey: 'pos_orders.date_order',
    cell: (o) => (
      <span className="flex items-center gap-2 font-medium">
        {o.name}
        {o.is_refund && <Badge variant="outline">Retur</Badge>}
      </span>
    ),
  },
  { key: 'date', header: 'Tanggal', hideOnMobile: true, cell: (o) => dateTime.format(new Date(o.date_order)) },
  { key: 'session', header: 'Sesi', hideOnMobile: true, cell: (o) => o.session?.name },
  { key: 'cashier', header: 'Kasir', hideOnMobile: true, cell: (o) => o.user?.name },
  { key: 'partner', header: 'Pelanggan', hideOnMobile: true, cell: (o) => o.partner?.name },
  { key: 'total', header: 'Total', sortKey: 'pos_orders.amount_total', className: 'text-right', cell: (o) => formatMoney(o.amount_total) },
  {
    key: 'state',
    header: 'Status',
    cell: (o) => <StateBadge label={o.invoice ? 'Difakturkan' : orderStateLabels[o.state]} tone={o.state === 'done' ? 'success' : 'info'} />,
  },
]

export function PosOrdersPage() {
  const navigate = useNavigate()
  const [params, setParams] = useListParams()
  const { data, isLoading } = usePosOrders(params)

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <PageHeader title="Pesanan POS" />
      <div className="mb-3 flex flex-wrap gap-2">
        <SearchInput value={String(params.search ?? '')} onChange={(search) => setParams({ search })} placeholder="Cari nomor / pelanggan…" />
        <NativeSelect value={String(params.state ?? '')} onChange={(e) => setParams({ state: e.target.value })} aria-label="Status" className="w-40">
          <option value="">Semua status</option>
          <option value="paid">Dibayar</option>
          <option value="done">Diposting</option>
        </NativeSelect>
      </div>
      <DataTable
        columns={columns}
        page={data}
        isLoading={isLoading}
        rowKey={(o) => o.id}
        onRowClick={(o) => navigate(`/pos/orders/${o.id}`)}
        sort={params.sort}
        direction={params.direction}
        onSortChange={(sort, direction) => setParams({ sort, direction })}
        onPageChange={(page) => setParams({ page })}
        empty="Belum ada pesanan POS."
      />
    </div>
  )
}
