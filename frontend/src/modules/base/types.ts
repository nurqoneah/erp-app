export type User = {
  id: number
  name: string
  email: string
  is_active: boolean
  roles: string[]
  permissions: string[]
}

export type ModuleInfo = {
  name: string
  slug: string
  label: string
  description: string
  version: string
  icon: string | null
  depends: string[]
  required: boolean
  enabled: boolean
}

export type MenuItem = { label: string; path: string }

export type MenuGroup = {
  module: string
  slug: string
  label: string
  icon: string | null
  items: MenuItem[]
}
