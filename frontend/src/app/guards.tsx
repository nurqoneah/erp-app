import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router'

import { useAuth } from '@/app/auth'

function FullPageSpinner() {
  return (
    <div className="flex min-h-svh items-center justify-center">
      <div className="border-primary size-8 animate-spin rounded-full border-2 border-t-transparent" />
    </div>
  )
}

export function RequireAuth() {
  const { user, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) return <FullPageSpinner />
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />

  return <Outlet />
}

export function GuestOnly({ children }: { children: ReactNode }) {
  const { user, isLoading } = useAuth()

  if (isLoading) return <FullPageSpinner />
  if (user) return <Navigate to="/" replace />

  return children
}

/** Tampilkan halaman hanya bila user punya permission tersebut. */
export function RequirePermission({ permission, children }: { permission: string; children: ReactNode }) {
  const { can } = useAuth()

  if (!can(permission)) {
    return (
      <div className="px-4 py-24 text-center">
        <h1 className="text-xl font-semibold">Akses ditolak</h1>
        <p className="text-muted-foreground mt-1 text-sm">Anda tidak punya izin untuk membuka halaman ini.</p>
      </div>
    )
  }

  return children
}
