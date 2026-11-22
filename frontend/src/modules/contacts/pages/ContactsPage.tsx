import { Building2, Plus, User } from 'lucide-react'
import { Link, useNavigate } from 'react-router'

import { useAuth } from '@/app/auth'
import { ArchiveFilter } from '@/components/data/ArchiveFilter'
import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { SearchInput } from '@/components/data/SearchInput'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { NativeSelect } from '@/components/ui/native-select'
import { useListParams } from '@/hooks/useListParams'
import { usePartners } from '@/modules/contacts/api'
import type { Partner } from '@/modules/contacts/types'

const columns: Column<Partner>[] = [
  {
    key: 'name',
    header: 'Nama',
    sortKey: 'name',
    cell: (p) => (
      <span className="flex items-center gap-2">
        {p.type === 'company' ? (
          <Building2 className="text-muted-foreground size-4 shrink-0" />
        ) : (
          <User className="text-muted-foreground size-4 shrink-0" />
        )}
        <span className="font-medium">{p.display_name}</span>
      </span>
    ),
  },
  { key: 'email', header: 'Email', sortKey: 'email', hideOnMobile: true, cell: (p) => p.email },
  { key: 'phone', header: 'Telepon', hideOnMobile: true, cell: (p) => p.phone || p.mobile },
  { key: 'city', header: 'Kota', sortKey: 'city', hideOnMobile: true, cell: (p) => p.city },
  {
    key: 'roles',
    header: 'Peran',
    cell: (p) => (
      <div className="flex flex-wrap gap-1">
        {p.is_customer && <Badge variant="success">Pelanggan</Badge>}
        {p.is_vendor && <Badge variant="secondary">Pemasok</Badge>}
      </div>
    ),
  },
]

export function ContactsPage() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [params, setParams] = useListParams({ sort: 'name', direction: 'asc' })
  const { data, isLoading } = usePartners(params)

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <PageHeader
        title="Kontak"
        actions={
          can('contacts.partner.manage') && (
            <Button asChild>
              <Link to="/contacts/new">
                <Plus /> Baru
              </Link>
            </Button>
          )
        }
      />
      <div className="mb-3 flex flex-wrap gap-2">
        <SearchInput
          value={String(params.search ?? '')}
          onChange={(search) => setParams({ search })}
          placeholder="Cari nama, email, telepon, kota…"
        />
        <NativeSelect value={String(params.role ?? '')} onChange={(e) => setParams({ role: e.target.value })} aria-label="Peran" className="w-36">
          <option value="">Semua peran</option>
          <option value="customer">Pelanggan</option>
          <option value="vendor">Pemasok</option>
        </NativeSelect>
        <NativeSelect value={String(params.type ?? '')} onChange={(e) => setParams({ type: e.target.value })} aria-label="Tipe" className="w-36">
          <option value="">Semua tipe</option>
          <option value="company">Perusahaan</option>
          <option value="person">Perorangan</option>
        </NativeSelect>
        <ArchiveFilter value={params.active as string | undefined} onChange={(active) => setParams({ active })} />
      </div>
      <DataTable
        columns={columns}
        page={data}
        isLoading={isLoading}
        rowKey={(p) => p.id}
        onRowClick={(p) => navigate(`/contacts/${p.id}`)}
        sort={params.sort}
        direction={params.direction}
        onSortChange={(sort, direction) => setParams({ sort, direction })}
        onPageChange={(page) => setParams({ page })}
        empty="Belum ada kontak."
      />
    </div>
  )
}
