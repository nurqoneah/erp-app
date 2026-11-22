import type { ReactNode } from 'react'
import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router'

type Crumb = { label: string; to?: string }

export function PageHeader({ title, crumbs = [], actions }: { title: string; crumbs?: Crumb[]; actions?: ReactNode }) {
  return (
    <div className="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2">
      <div className="min-w-0 flex-1">
        <h1 className="flex min-w-0 flex-wrap items-center gap-1 text-xl font-semibold tracking-tight">
          {crumbs.map((crumb) => (
            <span key={crumb.label} className="text-muted-foreground flex items-center gap-1 font-normal">
              {crumb.to ? (
                <Link to={crumb.to} className="hover:text-foreground hover:underline">
                  {crumb.label}
                </Link>
              ) : (
                crumb.label
              )}
              <ChevronRight className="size-4" />
            </span>
          ))}
          <span className="truncate">{title}</span>
        </h1>
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}
