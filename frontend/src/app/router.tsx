import { Suspense } from 'react'
import { Link, Route, Routes, useLocation } from 'react-router'

import { useAuth } from '@/app/auth'
import { GuestOnly, RequireAuth, RequirePermission } from '@/app/guards'
import { AppLayout } from '@/app/layout/AppLayout'
import type { ModuleRoute } from '@/app/routes'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useInstalledModules, useModules } from '@/modules/base/api'
import { ComingSoonPage } from '@/modules/base/pages/ComingSoonPage'
import { HomePage } from '@/modules/base/pages/HomePage'
import { LoginPage } from '@/modules/base/pages/LoginPage'
import { frontendModules, setupModules } from '@/modules/registry'

setupModules()

const moduleRoutes = frontendModules.flatMap((module) => (module.routes ?? []).map((route) => ({ ...route, module: module.name })))

function ModuleNotInstalled({ module }: { module: string }) {
  const { can } = useAuth()
  const { data: modules } = useModules()
  const label = modules?.find((m) => m.name === module)?.label ?? module

  return (
    <div className="px-4 py-24 text-center">
      <h1 className="text-xl font-semibold">Modul {label} belum terpasang</h1>
      <p className="text-muted-foreground mt-1 text-sm">
        {can('base.module.manage') ? 'Pasang modul ini lewat halaman Apps.' : 'Hubungi administrator untuk memasang modul ini.'}
      </p>
      {can('base.module.manage') && (
        <Button asChild className="mt-6">
          <Link to="/settings/modules">Buka Apps</Link>
        </Button>
      )}
    </div>
  )
}

/**
 * Render halaman modul: cek modul terpasang & permission. Diberi key = pathname agar form
 * tidak membawa state lama saat pindah dari /contacts/1 ke /contacts/new (komponen yang sama).
 */
function ModulePage({ route }: { route: ModuleRoute & { module: string } }) {
  const { pathname } = useLocation()
  const installed = useInstalledModules()
  const Page = route.component

  if (!installed) return <Skeleton className="m-6 h-64" />
  if (!installed.has(route.module)) return <ModuleNotInstalled module={route.module} />

  const page = (
    <Suspense fallback={<Skeleton className="m-6 h-64" />}>
      <Page key={pathname} />
    </Suspense>
  )
  return route.permission ? <RequirePermission permission={route.permission}>{page}</RequirePermission> : page
}

export function AppRoutes() {
  return (
    <Routes>
      <Route
        path="/login"
        element={
          <GuestOnly>
            <LoginPage />
          </GuestOnly>
        }
      />
      <Route element={<RequireAuth />}>
        <Route element={<AppLayout />}>
          <Route index element={<HomePage />} />
          {moduleRoutes.map((route) => (
            <Route key={route.path} path={route.path} element={<ModulePage route={route} />} />
          ))}
          <Route path="*" element={<ComingSoonPage />} />
        </Route>
      </Route>
    </Routes>
  )
}
