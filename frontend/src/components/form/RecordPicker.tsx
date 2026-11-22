import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Check, ChevronsUpDown, X } from 'lucide-react'
import { Popover } from 'radix-ui'

import { Input } from '@/components/ui/input'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { cn } from '@/lib/utils'

export type PickerOption = { id: number; label: string; hint?: string }

type RecordPickerProps = {
  id?: string
  value: PickerOption | null
  onChange: (value: PickerOption | null) => void
  /** Kunci cache unik untuk sumber data ini. */
  queryKey: string
  search: (term: string) => Promise<PickerOption[]>
  placeholder?: string
  disabled?: boolean
  invalid?: boolean
  clearable?: boolean
}

/**
 * Field relasi (many2one) dengan pencarian ke server, mirip field relasi di Odoo.
 */
export function RecordPicker({
  id,
  value,
  onChange,
  queryKey,
  search,
  placeholder = 'Pilih…',
  disabled,
  invalid,
  clearable = true,
}: RecordPickerProps) {
  const [open, setOpen] = useState(false)
  const [term, setTerm] = useState('')
  const debounced = useDebouncedValue(term, 250)

  const { data: options = [], isFetching } = useQuery({
    queryKey: ['picker', queryKey, debounced],
    queryFn: () => search(debounced),
    enabled: open,
    staleTime: 30_000,
  })

  return (
    <Popover.Root open={open} onOpenChange={setOpen}>
      <div className="relative">
        <Popover.Trigger asChild disabled={disabled}>
          <button
            id={id}
            type="button"
            aria-invalid={invalid}
            className={cn(
              'border-input focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive flex h-9 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 text-left text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
              !value && 'text-muted-foreground',
            )}
          >
            <span className="truncate">{value?.label ?? placeholder}</span>
            <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
          </button>
        </Popover.Trigger>
        {clearable && value && !disabled && (
          <button
            type="button"
            onClick={() => onChange(null)}
            className="text-muted-foreground hover:text-foreground absolute top-1/2 right-8 -translate-y-1/2"
            aria-label="Kosongkan"
          >
            <X className="size-3.5" />
          </button>
        )}
      </div>
      <Popover.Portal>
        <Popover.Content
          align="start"
          sideOffset={4}
          className="bg-popover text-popover-foreground z-50 w-(--radix-popover-trigger-width) min-w-56 rounded-md border p-1 shadow-md"
        >
          <Input
            autoFocus
            value={term}
            onChange={(e) => setTerm(e.target.value)}
            placeholder="Ketik untuk mencari…"
            className="mb-1 h-8"
          />
          <div role="listbox" className="max-h-64 overflow-y-auto">
            {options.length === 0 && (
              <p className="text-muted-foreground px-2 py-3 text-center text-sm">{isFetching ? 'Memuat…' : 'Tidak ditemukan.'}</p>
            )}
            {options.map((option) => (
              <button
                key={option.id}
                type="button"
                role="option"
                aria-selected={value?.id === option.id}
                onClick={() => {
                  onChange(option)
                  setOpen(false)
                  setTerm('')
                }}
                className="hover:bg-accent hover:text-accent-foreground flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
              >
                <Check className={cn('size-4 shrink-0', value?.id === option.id ? 'opacity-100' : 'opacity-0')} />
                <span className="min-w-0 flex-1 truncate">{option.label}</span>
                {option.hint && <span className="text-muted-foreground text-xs">{option.hint}</span>}
              </button>
            ))}
          </div>
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  )
}
