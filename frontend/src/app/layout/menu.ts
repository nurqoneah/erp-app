import type { MenuGroup } from '@/modules/base/types'

/** Cari modul yang sedang dibuka berdasarkan path (prefix terpanjang). */
export function findActiveGroup(groups: MenuGroup[], pathname: string): MenuGroup | undefined {
  let best: { group: MenuGroup; length: number } | undefined
  for (const group of groups) {
    for (const item of group.items) {
      const matches = pathname === item.path || pathname.startsWith(item.path + '/')
      if (matches && (!best || item.path.length > best.length)) {
        best = { group, length: item.path.length }
      }
    }
  }
  return best?.group
}
