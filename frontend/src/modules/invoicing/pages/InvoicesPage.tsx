import { Plus } from 'lucide-react'
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
import { formatDate, formatMoney, todayISO } from '@/lib/format'
import { useInvoices } from '@/modules/invoicing/api'
import type { Invoice, InvoiceKind } from '@/modules/invoicing/types'

function columns(kind: InvoiceKind): Column<Invoice>[] {
  const today = todayISO()
  return [
    {
      key: 'number',
      header: 'Nomor',
      sortKey: 'invoices.number',
      cell: (i) => <span className={i.number ? 'font-medium' : 'text-muted-foreground italic'}>{i.display_name}</span>,
    },
    { key: 'partner', header: kind.partnerLabel, sortKey: 'partners.name', cell: (i) => i.partner?.name },
    { key: 'date', header: 'Tanggal', sortKey: 'invoices.invoice_date', hideOnMobile: true, cell: (i) => formatDate(i.invoice_date) },
    {
      key: 'due',
      header: 'Jatuh tempo',
      sortKey: 'invoices.due_date',
      hideOnMobile: true,
      cell: (i) => (
        <span className={i.state === 'posted' && i.payment_state !== 'paid' && i.due_date && i.due_date < today ? 'text-destructive font-medium' : ''}>
          {formatDate(i.due_date)}
        </span>
      ),
    },
    { key: 'origin', header: 'Asal', hideOnMobile: true, cell: (i) => i.origin },
    { key: 'total', header: 'Total', sortKey: 'invoices.amount_total', className: 'text-right', cell: (i) => formatMoney(i.amount_total) },
    {
      key: 'state',
      header: 'Status',
      cell: (i) => <StateBadge {...(i.state === 'posted' ? stateOf('payment', i.payment_state) : stateOf('invoice', i.state))} />,
    },
  ]
}

export function InvoicesPage({ kind }: { kind: InvoiceKind }) {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [params, setParams] = useListParams()
  const { data, isLoading } = useInvoices({ ...params, type: kind.type })

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <PageHeader
        title={kind.title}
        actions={
          can('invoicing.invoice.manage') && (
            <Button asChild>
              <Link to={`${kind.basePath}/new`}>
                <Plus /> Baru
              </Link>
            </Button>
          )
        }
      />
      <div className="mb-3 flex flex-wrap gap-2">
        <SearchInput value={String(params.search ?? '')} onChange={(search) => setParams({ search })} placeholder={`Cari nomor, ${kind.partnerLabel.toLowerCase()}, asal…`} />
        <NativeSelect
          value={String(params.state ?? '')}
          onChange={(e) => setParams({ state: e.target.value })}
          aria-label="Status"
          className="w-40"
        >
          <option value="">Semua status</option>
          <option value="draft">Draf</option>
          <option value="posted">Diposting</option>
          <option value="cancelled">Dibatalkan</option>
        </NativeSelect>
        <NativeSelect
          value={String(params.payment_state ?? '')}
          onChange={(e) => setParams({ payment_state: e.target.value })}
          aria-label="Pembayaran"
          className="w-40"
        >
          <option value="">Semua pembayaran</option>
          <option value="not_paid">Belum dibayar</option>
          <option value="partial">Sebagian</option>
          <option value="paid">Lunas</option>
        </NativeSelect>
      </div>
      <DataTable
        columns={columns(kind)}
        page={data}
        isLoading={isLoading}
        rowKey={(i) => i.id}
        onRowClick={(i) => navigate(`${kind.basePath}/${i.id}`)}
        sort={params.sort}
        direction={params.direction}
        onSortChange={(sort, direction) => setParams({ sort, direction })}
        onPageChange={(page) => setParams({ page })}
        empty={`Belum ada ${kind.singular.toLowerCase()}.`}
      />
    </div>
  )
}
