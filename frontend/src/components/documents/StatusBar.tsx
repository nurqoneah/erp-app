import { cn } from '@/lib/utils'

/** Status bar seperti di form Odoo: langkah-langkah dengan langkah aktif disorot. */
export function StatusBar({ steps, current }: { steps: { key: string; label: string }[]; current: string }) {
  return (
    <ol className="flex overflow-hidden rounded-md border text-xs font-medium" aria-label="Status dokumen">
      {steps.map((step, index) => {
        const active = step.key === current
        return (
          <li
            key={step.key}
            aria-current={active ? 'step' : undefined}
            className={cn(
              'px-3 py-1.5 whitespace-nowrap',
              index > 0 && 'border-l',
              active ? 'bg-primary text-primary-foreground' : 'bg-card text-muted-foreground',
            )}
          >
            {step.label}
          </li>
        )
      })}
    </ol>
  )
}
