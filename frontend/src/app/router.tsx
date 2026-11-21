import { Route, Routes } from 'react-router'

import { GuestOnly, RequireAuth } from '@/app/guards'
import { AppLayout } from '@/app/layout/AppLayout'
import { ComingSoonPage } from '@/modules/base/pages/ComingSoonPage'
import { HomePage } from '@/modules/base/pages/HomePage'
import { LoginPage } from '@/modules/base/pages/LoginPage'
import { ModulesPage } from '@/modules/base/pages/ModulesPage'

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
          <Route path="settings/modules" element={<ModulesPage />} />
          {/* Halaman modul lain akan didaftarkan di sini per fase. */}
          <Route path="*" element={<ComingSoonPage />} />
        </Route>
      </Route>
    </Routes>
  )
}
