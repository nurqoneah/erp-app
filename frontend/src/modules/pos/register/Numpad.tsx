import { Delete } from 'lucide-react'

import { cn } from '@/lib/utils'
import type { NumpadKey } from './numpad'

type NumpadProps = {
  onKey: (key: NumpadKey) => void
  modes?: { key: string; label: string }[]
  mode?: string
  onMode?: (mode: string) => void
  className?: string
}

const rows: NumpadKey[][] = [
  ['1', '2', '3'],
  ['4', '5', '6'],
  ['7', '8', '9'],
  ['.', '0', 'backspace'],
]

/** Numpad layar sentuh ala kasir Odoo, dengan tombol mode di kolom kanan. */
export function Numpad({ onKey, modes = [], mode, onMode, className }: NumpadProps) {
  return (
    <div className={cn('grid gap-1.5', modes.length ? 'grid-cols-4' : 'grid-cols-3', className)}>
      {rows.map((row, r) => (
        <div key={r} className="contents">
          {row.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => onKey(key)}
              className="bg-card hover:bg-muted active:bg-accent flex h-12 items-center justify-center rounded-md border text-lg font-medium tabular-nums select-none"
              aria-label={key === 'backspace' ? 'Hapus angka' : key}
            >
              {key === 'backspace' ? <Delete className="size-5" /> : key}
            </button>
          ))}
          {modes.length > 0 &&
            (modes[r] ? (
              <button
                type="button"
                onClick={() => onMode?.(modes[r].key)}
                aria-pressed={mode === modes[r].key}
                className={cn(
                  'h-12 rounded-md border text-sm font-medium select-none',
                  mode === modes[r].key ? 'bg-primary text-primary-foreground border-primary' : 'bg-card hover:bg-muted',
                )}
              >
                {modes[r].label}
              </button>
            ) : (
              <span />
            ))}
        </div>
      ))}
    </div>
  )
}
