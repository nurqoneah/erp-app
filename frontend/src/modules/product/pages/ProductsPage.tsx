import { Plus } from 'lucide-react'
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
import { formatMoney } from '@/lib/format'
import { useProducts } from '@/modules/product/api'
import { productTypeLabels, type Product, type ProductType } from '@/modules/product/types'

const columns: Column<Product>[] = [
  {
    key: 'name',
    header: 'Produk',
    sortKey: 'name',
    cell: (p) => (
      <div className="min-w-0">
        <div className="font-medium">{p.name}</div>
        {p.sku && <div className="text-muted-foreground text-xs">{p.sku}</div>}
      </div>
    ),
  },
  { key: 'category', header: 'Kategori', hideOnMobile: true, cell: (p) => p.category?.complete_name },
  {
    key: 'type',
    header: 'Tipe',
    hideOnMobile: true,
    cell: (p) => <Badge variant={p.type === 'service' ? 'outline' : 'secondary'}>{productTypeLabels[p.type].split(' (')[0]}</Badge>,
  },
  { key: 'uom', header: 'Satuan', hideOnMobile: true, cell: (p) => p.uom?.name },
  { key: 'sale_price', header: 'Harga Jual', sortKey: 'sale_price', className: 'text-right', cell: (p) => formatMoney(p.sale_price) },
  { key: 'cost', header: 'Harga Pokok', sortKey: 'cost', className: 'text-right', hideOnMobile: true, cell: (p) => formatMoney(p.cost) },
]

export function ProductsPage() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [params, setParams] = useListParams({ sort: 'name', direction: 'asc' })
  const { data, isLoading } = useProducts(params)

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <PageHeader
        title="Produk"
        actions={
          can('product.product.manage') && (
            <Button asChild>
              <Link to="/products/new">
                <Plus /> Baru
              </Link>
            </Button>
          )
        }
      />
      <div className="mb-3 flex flex-wrap gap-2">
        <SearchInput value={String(params.search ?? '')} onChange={(search) => setParams({ search })} placeholder="Cari nama, kode, barcode…" />
        <NativeSelect value={String(params.type ?? '')} onChange={(e) => setParams({ type: e.target.value })} aria-label="Tipe" className="w-48">
          <option value="">Semua tipe</option>
          {(Object.keys(productTypeLabels) as ProductType[]).map((t) => (
            <option key={t} value={t}>
              {productTypeLabels[t]}
            </option>
          ))}
        </NativeSelect>
        <ArchiveFilter value={params.active as string | undefined} onChange={(active) => setParams({ active })} />
      </div>
      <DataTable
        columns={columns}
        page={data}
        isLoading={isLoading}
        rowKey={(p) => p.id}
        onRowClick={(p) => navigate(`/products/${p.id}`)}
        sort={params.sort}
        direction={params.direction}
        onSortChange={(sort, direction) => setParams({ sort, direction })}
        onPageChange={(page) => setParams({ page })}
        empty="Belum ada produk."
      />
    </div>
  )
}
