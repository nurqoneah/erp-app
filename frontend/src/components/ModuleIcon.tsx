import { createElement } from 'react'
import type { LucideProps } from 'lucide-react'

import { moduleIcon } from '@/lib/icons'

export function ModuleIcon({ icon, ...props }: { icon?: string | null } & Omit<LucideProps, 'name'>) {
  return createElement(moduleIcon(icon), props)
}
