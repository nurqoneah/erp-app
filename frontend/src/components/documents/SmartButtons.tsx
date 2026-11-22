import type { ReactNode } from 'react'
import { Link } from 'react-router'

/** Tombol ringkas ke dokumen terkait (seperti "smart button" di Odoo). */
export function SmartButtons({ items }: { items: { label: string; count: number; to: string; icon: ReactNode }[] }) {
  const visible = items.filter((item) => item.count > 0)
  if (!visible.length) return null

  return (
    <div className="flex flex-wrap gap-2">
      {visible.map((item) => (
        <Link
          key={item.label}
          to={item.to}
          className="bg-card hover:bg-muted flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
        >
          <span className="text-primary [&_svg]:size-4">{item.icon}</span>
          <span className="font-semibold tabular-nums">{item.count}</span>
          <span className="text-muted-foreground">{item.label}</span>
        </Link>
      ))}
    </div>
  )
}
