import { useState } from 'react'
import { ArrowLeft, CheckCircle2, FileText, Trash2, User } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { round } from '@/components/documents/amounts'
import { formatMoney } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { PaymentMethod } from '../types'
import { Numpad } from './Numpad'
import { applyKey, type NumpadKey } from './numpad'

export type PaymentLine = { key: string; method: PaymentMethod; amount: string }

type PaymentScreenProps = {
  total: number
  methods: PaymentMethod[]
  partnerName: string | null
  toInvoice: boolean
  isRefund: boolean
  submitting: boolean
  onToggleInvoice: () => void
  onChooseCustomer: () => void
  onBack: () => void
  onValidate: (payments: PaymentLine[]) => void
}

let counter = 0

/** Layar pembayaran: pilih metode (otomatis terisi sisa tagihan), split, kembalian dari tunai. */
export function PaymentScreen({
  total,
  methods,
  partnerName,
  toInvoice,
  isRefund,
  submitting,
  onToggleInvoice,
  onChooseCustomer,
  onBack,
  onValidate,
}: PaymentScreenProps) {
  const [lines, setLines] = useState<PaymentLine[]>([])
  const [selected, setSelected] = useState<string | null>(null)
  // Buffer numpad: ketikan pertama mengganti nilai, berikutnya menambah digit (seperti Odoo).
  const [buffer, setBuffer] = useState<string | null>(null)

  const paid = round(lines.reduce((sum, l) => sum + Number(l.amount || 0), 0))
  const remaining = round(total - paid)
  const hasCash = lines.some((l) => l.method.type === 'cash')
  const change = !isRefund && remaining < 0 && hasCash ? -remaining : 0
  const valid = isRefund ? Math.abs(remaining) < 0.005 : remaining <= 0.005 && (remaining > -0.005 || hasCash)

  function addMethod(method: PaymentMethod) {
    counter += 1
    const line = { key: `p${counter}`, method, amount: String(remaining > 0 || isRefund ? remaining : 0) }
    setLines([...lines, line])
    setSelected(line.key)
    setBuffer(null)
  }

  function onKey(key: NumpadKey) {
    if (!selected) return
    const next = applyKey(buffer ?? '', key)
    setBuffer(next)
    setLines(lines.map((l) => (l.key === selected ? { ...l, amount: (isRefund && next ? '-' : '') + (next || '0') } : l)))
  }

  return (
    <div className="grid h-full min-h-0 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
      <div className="flex min-h-0 flex-col gap-3">
        <Button variant="ghost" className="w-fit" onClick={onBack} disabled={submitting}>
          <ArrowLeft /> Kembali
        </Button>
        <h2 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">{isRefund ? 'Kembalikan dana lewat' : 'Metode pembayaran'}</h2>
        <div className="grid gap-2">
          {methods.map((method) => (
            <button
              key={method.id}
              type="button"
              onClick={() => addMethod(method)}
              className="bg-card hover:bg-muted flex h-12 items-center rounded-md border px-4 text-left font-medium"
            >
              {method.name}
            </button>
          ))}
        </div>
      </div>

      <div className="flex min-h-0 flex-col gap-3">
        <div className="bg-card rounded-lg border p-4 text-center">
          <div className="text-muted-foreground text-sm">{isRefund ? 'Total retur' : 'Total'}</div>
          <div className="text-3xl font-bold tabular-nums" data-testid="payment-total">
            {formatMoney(total)}
          </div>
        </div>

        <ul className="divide-y rounded-lg border">
          {lines.length === 0 && <li className="text-muted-foreground p-3 text-center text-sm">Pilih metode pembayaran.</li>}
          {lines.map((line) => (
            <li
              key={line.key}
              onClick={() => {
                setSelected(line.key)
                setBuffer(null)
              }}
              className={cn('flex cursor-pointer items-center gap-2 px-3 py-2', selected === line.key && 'bg-accent')}
            >
              <span className="flex-1 font-medium">{line.method.name}</span>
              <span className="text-lg tabular-nums">{formatMoney(line.amount)}</span>
              <Button
                variant="ghost"
                size="icon"
                className="size-8"
                aria-label={`Hapus ${line.method.name}`}
                onClick={(e) => {
                  e.stopPropagation()
                  setLines(lines.filter((l) => l.key !== line.key))
                  setSelected(null)
                }}
              >
                <Trash2 />
              </Button>
            </li>
          ))}
        </ul>

        <div className="grid grid-cols-2 gap-2 text-center">
          <div className="rounded-lg border p-2">
            <div className="text-muted-foreground text-xs">Sisa</div>
            <div className={cn('text-xl font-semibold tabular-nums', remaining > 0.005 && 'text-destructive')} data-testid="payment-remaining">
              {formatMoney(isRefund ? remaining : Math.max(remaining, 0))}
            </div>
          </div>
          <div className="rounded-lg border p-2">
            <div className="text-muted-foreground text-xs">Kembalian</div>
            <div className="text-xl font-semibold text-emerald-600 tabular-nums" data-testid="payment-change">
              {formatMoney(change)}
            </div>
          </div>
        </div>
        {!isRefund && remaining < -0.005 && !hasCash && <p className="text-destructive text-sm">Kembalian hanya bisa dari pembayaran tunai.</p>}

        <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
          <Numpad onKey={onKey} />
          <div className="grid content-start gap-2 sm:w-44">
            <Button variant="outline" className="h-12 justify-start" onClick={onChooseCustomer}>
              <User /> <span className="truncate">{partnerName ?? 'Pelanggan'}</span>
            </Button>
            {!isRefund && (
              <Button variant={toInvoice ? 'default' : 'outline'} className="h-12 justify-start" onClick={onToggleInvoice} aria-pressed={toInvoice}>
                <FileText /> Faktur
              </Button>
            )}
          </div>
        </div>

        <Button size="lg" className="h-14 text-lg" disabled={!valid || submitting} onClick={() => onValidate(lines)}>
          <CheckCircle2 /> {submitting ? 'Memproses…' : 'Validasi'}
        </Button>
      </div>
    </div>
  )
}
