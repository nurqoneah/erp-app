import { LayoutGrid, LogOut, Menu as MenuIcon } from 'lucide-react'
import { Link, NavLink, Outlet, useLocation } from 'react-router'

import { useAuth } from '@/app/auth'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { ModuleIcon } from '@/components/ModuleIcon'
import { findActiveGroup } from '@/app/layout/menu'
import { cn } from '@/lib/utils'
import { useMenu } from '@/modules/base/api'

function initials(name: string) {
  return name
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

export function AppLayout() {
  const { user, logout } = useAuth()
  const { pathname } = useLocation()
  const { data: groups = [] } = useMenu()
  const active = pathname === '/' ? undefined : findActiveGroup(groups, pathname)

  return (
    <div className="flex min-h-svh flex-col">
      <header className="bg-card sticky top-0 z-40 border-b">
        <div className="flex h-12 items-center gap-1 px-2 sm:px-4">
          <Button variant="ghost" size="icon" asChild aria-label="Semua aplikasi">
            <Link to="/">
              <LayoutGrid />
            </Link>
          </Button>

          {active && (
            <>
              <span className="mr-2 flex items-center gap-2 px-1 font-semibold">
                <ModuleIcon icon={active.icon} className="text-primary size-4" />
                {active.label}
              </span>

              <nav className="hidden items-center gap-0.5 md:flex" aria-label={`Menu ${active.label}`}>
                {active.items.map((item) => (
                  <NavLink
                    key={item.path}
                    to={item.path}
                    end
                    className={({ isActive }) =>
                      cn(
                        'rounded-md px-3 py-1.5 text-sm transition-colors',
                        isActive
                          ? 'bg-accent text-accent-foreground font-medium'
                          : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                      )
                    }
                  >
                    {item.label}
                  </NavLink>
                ))}
              </nav>

              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="icon" className="md:hidden" aria-label="Menu modul">
                    <MenuIcon />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                  {active.items.map((item) => (
                    <DropdownMenuItem key={item.path} asChild>
                      <Link to={item.path}>{item.label}</Link>
                    </DropdownMenuItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>
            </>
          )}

          <div className="ml-auto">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="gap-2 px-2">
                  <span className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-full text-xs font-semibold">
                    {user ? initials(user.name) : '?'}
                  </span>
                  <span className="hidden text-sm sm:inline">{user?.name}</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>
                  <div className="font-medium">{user?.name}</div>
                  <div className="text-muted-foreground truncate text-xs font-normal">{user?.email}</div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onSelect={() => void logout()}>
                  <LogOut />
                  Keluar
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </header>

      <main className="flex-1">
        <Outlet />
      </main>
    </div>
  )
}
