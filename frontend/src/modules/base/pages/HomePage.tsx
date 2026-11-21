import { Link } from 'react-router'

import { Skeleton } from '@/components/ui/skeleton'
import { ModuleIcon } from '@/components/ModuleIcon'
import { moduleTone } from '@/lib/icons'
import { cn } from '@/lib/utils'
import { useMenu } from '@/modules/base/api'

/** Layar utama berisi ikon aplikasi untuk setiap modul aktif. */
export function HomePage() {
  const { data: groups, isLoading } = useMenu()

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:py-16">
      <div className="grid grid-cols-3 gap-x-4 gap-y-8 sm:grid-cols-4 md:grid-cols-6">
        {isLoading &&
          Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="flex flex-col items-center gap-2">
              <Skeleton className="size-16 rounded-2xl" />
              <Skeleton className="h-3 w-14" />
            </div>
          ))}

        {groups?.map((group) => {
          return (
            <Link
              key={group.module}
              to={group.items[0].path}
              className="group flex flex-col items-center gap-2 rounded-xl p-1 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
              <span
                className={cn(
                  'flex size-16 items-center justify-center rounded-2xl text-white shadow-sm transition-transform group-hover:-translate-y-0.5 group-hover:shadow-md',
                  moduleTone(group.icon),
                )}
              >
                <ModuleIcon icon={group.icon} className="size-8" strokeWidth={1.75} />
              </span>
              <span className="text-center text-sm font-medium">{group.label}</span>
            </Link>
          )
        })}
      </div>

      {groups && groups.length <= 1 && (
        <p className="text-muted-foreground mt-12 text-center text-sm">
          Belum ada modul bisnis yang aktif. Aktifkan lewat <strong>Pengaturan → Modul</strong>.
        </p>
      )}
    </div>
  )
}
