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
