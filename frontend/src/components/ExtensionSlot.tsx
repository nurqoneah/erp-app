import { slotEntries, type FormExtensionProps } from '@/app/extensions'
import { useInstalledModules } from '@/modules/base/api'

/** Render semua perluasan yang terdaftar di titik ini dari modul yang terpasang. */
export function ExtensionSlot({ point, ...props }: { point: string } & FormExtensionProps) {
  const installed = useInstalledModules()
  if (!installed) return null

  return slotEntries(point)
    .filter((entry) => installed.has(entry.module))
    .map(({ module, component: Component }) => <Component key={module} {...props} />)
}
