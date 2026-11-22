import { Route, Routes, useLocation } from 'react-router'

import { GuestOnly, RequireAuth, RequirePermission } from '@/app/guards'
import { AppLayout } from '@/app/layout/AppLayout'
import type { ModuleRoute } from '@/app/routes'
import { ComingSoonPage } from '@/modules/base/pages/ComingSoonPage'
import { HomePage } from '@/modules/base/pages/HomePage'
import { LoginPage } from '@/modules/base/pages/LoginPage'
import { baseRoutes } from '@/modules/base/routes'
import { contactsRoutes } from '@/modules/contacts/routes'
import { productRoutes } from '@/modules/product/routes'

/** Halaman dari semua modul frontend. Tambahkan modul baru di sini. */
const moduleRoutes: ModuleRoute[] = [...baseRoutes, ...contactsRoutes, ...productRoutes]

/**
 * Render halaman modul. Diberi key = pathname agar form tidak membawa state lama
 * saat pindah dari /contacts/1 ke /contacts/new (komponen yang sama).
 */
function ModulePage({ route }: { route: ModuleRoute }) {
  const { pathname } = useLocation()
  const Page = route.component
  const page = <Page key={pathname} />

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
