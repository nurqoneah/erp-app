import type { ReactNode } from 'react'

import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'

type FieldProps = {
  label: string
  htmlFor?: string
  error?: string
  hint?: string
  required?: boolean
  className?: string
  children: ReactNode
}

export function Field({ label, htmlFor, error, hint, required, className, children }: FieldProps) {
  return (
    <div className={cn('grid content-start gap-1.5', className)}>
      <Label htmlFor={htmlFor}>
        {label}
        {required && <span className="text-destructive">*</span>}
      </Label>
      {children}
      {error ? (
        <p className="text-destructive text-xs" role="alert">
          {error}
        </p>
      ) : (
        hint && <p className="text-muted-foreground text-xs">{hint}</p>
      )}
    </div>
  )
}

export function FormSection({ title, children, className }: { title?: string; children: ReactNode; className?: string }) {
  return (
    <section className={cn('grid gap-4', className)}>
      {title && <h2 className="text-muted-foreground border-b pb-1 text-xs font-semibold tracking-wide uppercase">{title}</h2>}
      {children}
    </section>
  )
}

/** Baris centang dengan label di kanan. */
export function CheckField({ id, label, children }: { id: string; label: string; children: ReactNode }) {
  return (
    <div className="flex items-center gap-2">
      {children}
      <Label htmlFor={id} className="font-normal">
        {label}
      </Label>
    </div>
  )
}
