import { lazyPage, type ModuleRoute } from '@/app/routes'

const UsersPage = lazyPage(() => import('./pages/UsersPage'), 'UsersPage')
const UserFormPage = lazyPage(() => import('./pages/UserFormPage'), 'UserFormPage')
const RolesPage = lazyPage(() => import('./pages/RolesPage'), 'RolesPage')
const RoleFormPage = lazyPage(() => import('./pages/RoleFormPage'), 'RoleFormPage')
const CompanyPage = lazyPage(() => import('./pages/CompanyPage'), 'CompanyPage')
const SequencesPage = lazyPage(() => import('./pages/SequencesPage'), 'SequencesPage')
const ModulesPage = lazyPage(() => import('./pages/ModulesPage'), 'ModulesPage')

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
