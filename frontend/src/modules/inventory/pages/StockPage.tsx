import { SlidersHorizontal } from 'lucide-react'
import { Link } from 'react-router'

import { useAuth } from '@/app/auth'
import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { SearchInput } from '@/components/data/SearchInput'
import { Button } from '@/components/ui/button'
import { NativeSelect } from '@/components/ui/native-select'
import { useListParams } from '@/hooks/useListParams'
import { formatNumber } from '@/lib/format'
import { useLocations, useStock } from '@/modules/inventory/api'
import type { StockRow } from '@/modules/inventory/types'

const columns: Column<StockRow>[] = [
  {
    key: 'product',
    header: 'Produk',
    sortKey: 'products.name',
    cell: (r) => (
      <div>
        <div className="font-medium">{r.product.name}</div>
        {r.product.sku && <div className="text-muted-foreground text-xs">{r.product.sku}</div>}
      </div>
    ),
  },
  { key: 'location', header: 'Lokasi', sortKey: 'stock_locations.complete_name', cell: (r) => r.location.complete_name },
  {
    key: 'quantity',
    header: 'Jumlah',
    sortKey: 'stock_quants.quantity',
    className: 'text-right',
    cell: (r) => (
      <span className={Number(r.quantity) < 0 ? 'text-destructive font-medium tabular-nums' : 'font-medium tabular-nums'}>
        {formatNumber(r.quantity)} <span className="text-muted-foreground font-normal">{r.product.uom}</span>
      </span>
    ),
  },
]

export function StockPage() {
  const { can } = useAuth()
  const [params, setParams] = useListParams({ sort: 'products.name' })
  const { data, isLoading } = useStock(params)
  const { data: locations = [] } = useLocations('internal')

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <PageHeader
        title="Stok"
        actions={
          can('inventory.adjustment.manage') && (
            <Button asChild variant="outline">
              <Link to="/inventory/adjustments">
                <SlidersHorizontal /> Penyesuaian stok
              </Link>
            </Button>
          )
        }
      />
      <div className="mb-3 flex flex-wrap gap-2">
        <SearchInput value={String(params.search ?? '')} onChange={(search) => setParams({ search })} placeholder="Cari produk, SKU, barcode…" />
        <NativeSelect
          value={String(params.location_id ?? '')}
          onChange={(e) => setParams({ location_id: e.target.value })}
          aria-label="Lokasi"
          className="w-56"
        >
          <option value="">Semua lokasi</option>
          {locations.map((l) => (
            <option key={l.id} value={l.id}>
              {l.complete_name}
            </option>
          ))}
        </NativeSelect>
      </div>
      <DataTable
        columns={columns}
        page={data}
        isLoading={isLoading}
        rowKey={(r) => r.id}
        sort={params.sort}
        direction={params.direction}
        onSortChange={(sort, direction) => setParams({ sort, direction })}
        onPageChange={(page) => setParams({ page })}
        empty="Belum ada stok. Stok bertambah dari penerimaan barang atau penyesuaian stok."
      />
    </div>
  )
}
