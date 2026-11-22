export type User = {
  id: number
  name: string
  email: string
  is_active: boolean
  roles: string[]
  /** Hanya ada di /auth/me. */
  permissions?: string[]
  created_at?: string
}

export type Role = {
  id: number
  name: string
  is_system: boolean
  permissions_count: number
  users_count: number
  permissions?: string[] | null
}

export type PermissionGroup = {
  module: string
  label: string
  permissions: { name: string; label: string }[]
}

export type Company = {
  id: number
  name: string
  legal_name: string | null
  tax_id: string | null
  email: string | null
  phone: string | null
  website: string | null
  street: string | null
  city: string | null
  state: string | null
  zip: string | null
  country: string
  currency: string
}

export type Sequence = {
  id: number
  code: string
  name: string
  module: string | null
  module_label: string | null
  prefix: string
  suffix: string
  padding: number
  next_number: number
  reset: 'never' | 'yearly' | 'monthly'
  preview: string
}

export type ModuleState = 'installed' | 'uninstalled' | 'to_upgrade'

export type ModuleInfo = {
  name: string
  slug: string
  label: string
  description: string
  category: string
  author: string
  version: string
  installed_version: string | null
  icon: string | null
  depends: string[]
  required: boolean
  state: ModuleState
  installed: boolean
}

export type MenuItem = { label: string; path: string }

export type MenuGroup = {
  module: string
  slug: string
  label: string
  icon: string | null
  items: MenuItem[]
}
