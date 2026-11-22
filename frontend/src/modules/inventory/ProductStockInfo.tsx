import { Warehouse } from 'lucide-react'
import { Link } from 'react-router'

import type { FormExtensionProps } from '@/app/extensions'
import { formatNumber } from '@/lib/format'
import { useOnHand } from './api'

/** Disisipkan ke form Produk: jumlah stok tersedia (hanya barang stok). */
export function ProductStockInfo({ recordId, values }: FormExtensionProps) {
  const { data } = useOnHand(recordId && values.type === 'storable' ? [recordId] : [])
  if (!recordId || values.type !== 'storable') return null

  return (
    <div className="bg-muted/50 flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-sm">
      <Warehouse className="text-primary size-4" />
      <span>
        Stok tersedia: <strong className="tabular-nums">{data ? formatNumber(data[recordId] ?? 0) : '…'}</strong>
      </span>
      <Link to={`/inventory/stock?search=${encodeURIComponent(String(values.name ?? ''))}`} className="text-primary ml-auto hover:underline">
        Lihat per lokasi
      </Link>
    </div>
  )
}
