import { useNavigate } from 'react-router'

import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { StateBadge } from '@/components/documents/StateBadge'
import { NativeSelect } from '@/components/ui/native-select'
import { useListParams } from '@/hooks/useListParams'
import { formatMoney } from '@/lib/format'
import { useSessions } from '../api'
import { sessionStateLabels, type PosSession } from '../types'

const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' })

const columns: Column<PosSession>[] = [
  { key: 'name', header: 'Sesi', cell: (s) => <span className="font-medium">{s.name}</span> },
  { key: 'config', header: 'Point of Sale', cell: (s) => s.config?.name },
  { key: 'user', header: 'Dibuka oleh', hideOnMobile: true, cell: (s) => s.user?.name },
  { key: 'opened', header: 'Dibuka', sortKey: 'opened_at', hideOnMobile: true, cell: (s) => (s.opened_at ? dateTime.format(new Date(s.opened_at)) : '-') },
  { key: 'orders', header: 'Penjualan', className: 'text-right', cell: (s) => `${s.orders_count ?? 0} · ${formatMoney(s.orders_amount ?? 0)}` },
  {
    key: 'diff',
    header: 'Selisih kas',
    className: 'text-right',
    hideOnMobile: true,
    cell: (s) => (s.state === 'closed' ? <span className={Number(s.cash_register_difference) !== 0 ? 'text-destructive' : ''}>{formatMoney(s.cash_register_difference)}</span> : '-'),
  },
  { key: 'state', header: 'Status', cell: (s) => <StateBadge label={sessionStateLabels[s.state]} tone={s.state === 'closed' ? 'neutral' : 'success'} /> },
]

export function PosSessionsPage() {
  const navigate = useNavigate()
  const [params, setParams] = useListParams()
  const { data, isLoading } = useSessions(params)

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <PageHeader title="Sesi Kasir" />
      <div className="mb-3">
        <NativeSelect value={String(params.state ?? '')} onChange={(e) => setParams({ state: e.target.value })} aria-label="Status" className="w-48">
          <option value="">Semua status</option>
          <option value="opened">Berjalan</option>
          <option value="closed">Ditutup</option>
        </NativeSelect>
      </div>
      <DataTable
        columns={columns}
        page={data}
        isLoading={isLoading}
        rowKey={(s) => s.id}
        onRowClick={(s) => navigate(`/pos/sessions/${s.id}`)}
        sort={params.sort}
        direction={params.direction}
        onSortChange={(sort, direction) => setParams({ sort, direction })}
        onPageChange={(page) => setParams({ page })}
        empty="Belum ada sesi kasir."
      />
    </div>
  )
}
