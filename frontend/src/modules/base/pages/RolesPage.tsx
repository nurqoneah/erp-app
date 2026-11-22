import { Plus } from 'lucide-react'
import { Link, useNavigate } from 'react-router'

import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { SearchInput } from '@/components/data/SearchInput'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useListParams } from '@/hooks/useListParams'
import { useRoles } from '@/modules/base/api'
import type { Role } from '@/modules/base/types'

const columns: Column<Role>[] = [
  {
    key: 'name',
    header: 'Nama',
    sortKey: 'name',
    cell: (r) => (
      <span className="flex items-center gap-2 font-medium">
        {r.name}
        {r.is_system && <Badge variant="secondary">Sistem</Badge>}
      </span>
    ),
  },
  { key: 'permissions', header: 'Izin', cell: (r) => (r.is_system ? 'Semua' : r.permissions_count) },
  { key: 'users', header: 'Pengguna', cell: (r) => r.users_count },
]

export function RolesPage() {
  const navigate = useNavigate()
  const [params, setParams] = useListParams({ sort: 'name' })
  const { data, isLoading } = useRoles(params)

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <PageHeader
        title="Role"
        actions={
          <Button asChild>
            <Link to="/settings/roles/new">
              <Plus /> Baru
            </Link>
          </Button>
        }
      />
      <div className="mb-3">
        <SearchInput value={String(params.search ?? '')} onChange={(search) => setParams({ search })} placeholder="Cari role…" />
      </div>
      <DataTable
        columns={columns}
        page={data}
        isLoading={isLoading}
        rowKey={(r) => r.id}
        onRowClick={(r) => navigate(`/settings/roles/${r.id}`)}
        sort={params.sort}
        direction={params.direction}
        onSortChange={(sort, direction) => setParams({ sort, direction })}
        onPageChange={(page) => setParams({ page })}
      />
    </div>
  )
}
