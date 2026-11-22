import { ArrowRight, Plus } from 'lucide-react'
import { Link, useNavigate } from 'react-router'

import { useAuth } from '@/app/auth'
import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { SearchInput } from '@/components/data/SearchInput'
import { StateBadge } from '@/components/documents/StateBadge'
import { stateOf } from '@/components/documents/states'
import { Button } from '@/components/ui/button'
import { NativeSelect } from '@/components/ui/native-select'
import { useListParams } from '@/hooks/useListParams'
import { formatDate } from '@/lib/format'
import { cn } from '@/lib/utils'
import { usePickings } from '@/modules/inventory/api'
import { pickingTypeLabels, type Picking, type PickingType } from '@/modules/inventory/types'

const columns: Column<Picking>[] = [
  {
    key: 'reference',
    header: 'Referensi',
    sortKey: 'reference',
    cell: (p) => (
      <div>
        <div className="font-medium">{p.reference}</div>
        <div className="text-muted-foreground text-xs">{pickingTypeLabels[p.type]}</div>
      </div>
    ),
  },
  { key: 'partner', header: 'Mitra', hideOnMobile: true, cell: (p) => p.partner?.name },
  {
    key: 'route',
    header: 'Dari → Ke',
    hideOnMobile: true,
    cell: (p) => (
      <span className="text-muted-foreground flex items-center gap-1 text-xs">
        {p.source_location?.complete_name} <ArrowRight className="size-3" /> {p.dest_location?.complete_name}
      </span>
    ),
  },
  { key: 'scheduled_date', header: 'Tanggal', sortKey: 'scheduled_date', cell: (p) => formatDate(p.scheduled_date) },
  { key: 'origin', header: 'Dokumen asal', hideOnMobile: true, cell: (p) => p.origin },
  { key: 'state', header: 'Status', cell: (p) => <StateBadge {...stateOf('picking', p.state)} /> },
]

const tabs: [string, string][] = [
  ['', 'Semua'],
  ['receipt', 'Penerimaan'],
  ['delivery', 'Pengiriman'],
  ['internal', 'Internal'],
]

export function TransfersPage() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [params, setParams] = useListParams({ state: 'draft' })
  // 'all' = semua status (tanpa filter ke API).
  const { data, isLoading } = usePickings({ ...params, state: params.state === 'all' ? undefined : params.state })
  const type = String(params.type ?? '')

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <PageHeader
        title="Transfer"
        actions={
          can('inventory.transfer.manage') && (
            <Button asChild>
              <Link to={`/inventory/transfers/new${type ? `?type=${type}` : ''}`}>
                <Plus /> Baru
              </Link>
            </Button>
          )
        }
      />
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <div className="bg-muted inline-flex rounded-lg p-[3px]" role="tablist" aria-label="Tipe transfer">
          {tabs.map(([value, label]) => (
            <button
              key={value}
              role="tab"
              aria-selected={type === value}
              onClick={() => setParams({ type: value })}
              className={cn('rounded-md px-3 py-1 text-sm font-medium', type === value ? 'bg-background shadow-sm' : 'text-muted-foreground')}
            >
              {label}
            </button>
          ))}
        </div>
        <SearchInput value={String(params.search ?? '')} onChange={(search) => setParams({ search })} placeholder="Cari referensi / dokumen asal…" />
        <NativeSelect value={String(params.state ?? 'draft')} onChange={(e) => setParams({ state: e.target.value })} aria-label="Status" className="w-36">
          <option value="draft">Siap</option>
          <option value="done">Selesai</option>
          <option value="cancelled">Dibatalkan</option>
          <option value="all">Semua status</option>
        </NativeSelect>
      </div>
      <DataTable
        columns={columns}
        page={data}
        isLoading={isLoading}
        rowKey={(p) => p.id}
        onRowClick={(p) => navigate(`/inventory/transfers/${p.id}`)}
        sort={params.sort}
        direction={params.direction}
        onSortChange={(sort, direction) => setParams({ sort, direction })}
        onPageChange={(page) => setParams({ page })}
        empty={type ? `Tidak ada ${pickingTypeLabels[type as PickingType].toLowerCase()}.` : 'Tidak ada transfer.'}
      />
    </div>
  )
}
