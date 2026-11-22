import type { ModuleRoute } from '@/app/routes'
import { CompanyPage } from './pages/CompanyPage'
import { ModulesPage } from './pages/ModulesPage'
import { RoleFormPage } from './pages/RoleFormPage'
import { RolesPage } from './pages/RolesPage'
import { SequencesPage } from './pages/SequencesPage'
import { UserFormPage } from './pages/UserFormPage'
import { UsersPage } from './pages/UsersPage'

export const baseRoutes: ModuleRoute[] = [
  { path: 'settings/users', component: UsersPage, permission: 'base.user.view' },
  { path: 'settings/users/new', component: UserFormPage, permission: 'base.user.manage' },
  { path: 'settings/users/:id', component: UserFormPage, permission: 'base.user.view' },
  { path: 'settings/roles', component: RolesPage, permission: 'base.role.manage' },
  { path: 'settings/roles/new', component: RoleFormPage, permission: 'base.role.manage' },
  { path: 'settings/roles/:id', component: RoleFormPage, permission: 'base.role.manage' },
  { path: 'settings/company', component: CompanyPage, permission: 'base.setting.manage' },
  { path: 'settings/sequences', component: SequencesPage, permission: 'base.setting.manage' },
  { path: 'settings/modules', component: ModulesPage },
]
