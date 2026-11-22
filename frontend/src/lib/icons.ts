import {
  BadgeCheck,
  Boxes,
  LayoutGrid,
  Monitor,
  Package,
  Settings,
  ShoppingCart,
  Truck,
  Users,
  Warehouse,
  type LucideIcon,
} from 'lucide-react'

const icons: Record<string, LucideIcon> = {
  settings: Settings,
  users: Users,
  package: Package,
  warehouse: Warehouse,
  'shopping-cart': ShoppingCart,
  truck: Truck,
  monitor: Monitor,
  boxes: Boxes,
  badge: BadgeCheck,
}

export function moduleIcon(name?: string | null): LucideIcon {
  return (name && icons[name]) || LayoutGrid
}

/** Warna latar ikon aplikasi di layar utama (mirip ikon app di Odoo). */
const tones: Record<string, string> = {
  settings: 'bg-slate-500',
  users: 'bg-sky-500',
  package: 'bg-amber-500',
  warehouse: 'bg-orange-500',
  'shopping-cart': 'bg-emerald-500',
  truck: 'bg-violet-500',
  monitor: 'bg-rose-500',
  badge: 'bg-teal-500',
}

export function moduleTone(name?: string | null): string {
  return (name && tones[name]) || 'bg-indigo-500'
}
