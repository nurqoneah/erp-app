import { Construction } from 'lucide-react'
import { Link, useLocation } from 'react-router'

import { findActiveGroup } from '@/app/layout/menu'
import { Button } from '@/components/ui/button'
import { useMenu } from '@/modules/base/api'

/** Placeholder untuk halaman modul yang belum dibangun (atau path yang tidak dikenal). */
export function ComingSoonPage() {
  const { pathname } = useLocation()
  const { data: groups = [] } = useMenu()
  const group = findActiveGroup(groups, pathname)
  const item = group?.items.find((i) => i.path === pathname)

  return (
    <div className="flex flex-col items-center justify-center px-4 py-24 text-center">
      <Construction className="text-muted-foreground mb-4 size-12" strokeWidth={1.5} />
      {item ? (
        <>
          <h1 className="text-xl font-semibold">{item.label}</h1>
          <p className="text-muted-foreground mt-1 max-w-md text-sm">
            Halaman ini bagian dari modul {group?.label} dan akan dibangun di fase berikutnya.
          </p>
        </>
      ) : (
        <>
          <h1 className="text-xl font-semibold">Halaman tidak ditemukan</h1>
          <p className="text-muted-foreground mt-1 text-sm">
            Halaman ini tidak ada atau modulnya belum diaktifkan.
          </p>
        </>
      )}
      <Button asChild variant="outline" className="mt-6">
        <Link to="/">Kembali ke beranda</Link>
      </Button>
    </div>
  )
}
