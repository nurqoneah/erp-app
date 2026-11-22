import { Plus } from 'lucide-react'
import { Link, useNavigate } from 'react-router'

import { useAuth } from '@/app/auth'
import { ArchiveFilter } from '@/components/data/ArchiveFilter'
import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { SearchInput } from '@/components/data/SearchInput'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useListParams } from '@/hooks/useListParams'
import { formatDate } from '@/lib/format'
import { useUsers } from '@/modules/base/api'
import type { User } from '@/modules/base/types'

const columns: Column<User>[] = [
  { key: 'name', header: 'Nama', sortKey: 'name', cell: (u) => <span className="font-medium">{u.name}</span> },
  { key: 'email', header: 'Email', sortKey: 'email', cell: (u) => u.email },
  {
    key: 'roles',
    header: 'Role',
    hideOnMobile: true,
    cell: (u) => (
      <div className="flex flex-wrap gap-1">
        {u.roles.map((r) => (
          <Badge key={r} variant="secondary">
            {r}
          </Badge>
        ))}
      </div>
    ),
  },
  { key: 'created_at', header: 'Dibuat', sortKey: 'created_at', hideOnMobile: true, cell: (u) => formatDate(u.created_at) },
]

export function UsersPage() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [params, setParams] = useListParams({ sort: 'name', direction: 'asc' })
  const { data, isLoading } = useUsers(params)

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <PageHeader
        title="Pengguna"
        actions={
          can('base.user.manage') && (
            <Button asChild>
              <Link to="/settings/users/new">
                <Plus /> Baru
              </Link>
            </Button>
          )
        }
      />
      <div className="mb-3 flex flex-wrap gap-2">
        <SearchInput value={String(params.search ?? '')} onChange={(search) => setParams({ search })} placeholder="Cari nama / email…" />
        <ArchiveFilter value={params.active as string | undefined} onChange={(active) => setParams({ active })} />
      </div>
      <DataTable
        columns={columns}
        page={data}
        isLoading={isLoading}
        rowKey={(u) => u.id}
        onRowClick={(u) => navigate(`/settings/users/${u.id}`)}
        sort={params.sort}
        direction={params.direction}
        onSortChange={(sort, direction) => setParams({ sort, direction })}
        onPageChange={(page) => setParams({ page })}
        empty="Tidak ada pengguna."
      />
    </div>
  )
}
