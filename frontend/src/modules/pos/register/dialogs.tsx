import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Field } from '@/components/form/Field'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'
import { cn } from '@/lib/utils'
import { searchPartners } from '@/modules/contacts/api'
import { useCashMove, useCloseSession, useOpeningControl, useSession } from '../api'

/** Pilih pelanggan untuk pesanan (dicari dari Kontak). */
export function CustomerDialog({
  current,
  onSelect,
  onClose,
}: {
  current: { id: number; name: string } | null
  onSelect: (partner: { id: number; name: string } | null) => void
  onClose: () => void
}) {
  const [term, setTerm] = useState('')
  const debounced = useDebouncedValue(term, 250)
  const { data = [], isFetching } = useQuery({
    queryKey: ['picker', 'pos-customers', debounced],
    queryFn: () => searchPartners(debounced, { role: 'customer' }),
  })

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Pilih pelanggan</DialogTitle>
        </DialogHeader>
        <Input autoFocus value={term} onChange={(e) => setTerm(e.target.value)} placeholder="Cari nama, telepon, email…" />
        <ul className="max-h-72 divide-y overflow-y-auto rounded-md border">
          {data.length === 0 && <li className="text-muted-foreground p-3 text-center text-sm">{isFetching ? 'Memuat…' : 'Tidak ditemukan.'}</li>}
          {data.map((p) => (
            <li key={p.id}>
              <button
                type="button"
                onClick={() => {
                  onSelect({ id: p.id, name: p.label })
                  onClose()
                }}
                className={cn('hover:bg-muted flex w-full justify-between px-3 py-2 text-left text-sm', current?.id === p.id && 'bg-accent')}
              >
                <span className="font-medium">{p.label}</span>
                <span className="text-muted-foreground">{p.hint}</span>
              </button>
            </li>
          ))}
        </ul>
        <DialogFooter>
          {current && (
            <Button
              variant="outline"
              onClick={() => {
                onSelect(null)
                onClose()
              }}
            >
              Tanpa pelanggan
            </Button>
          )}
          <Button variant="outline" onClick={onClose}>
            Tutup
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function CashMoveDialog({ sessionId, onClose }: { sessionId: number; onClose: () => void }) {
  const cashMove = useCashMove()
  const form = useFormState({ type: 'in' as 'in' | 'out', amount: '', reason: '' })
  const { values, set, errors } = form

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      await cashMove.mutateAsync({ sessionId, input: values })
      toast.success(values.type === 'in' ? 'Kas masuk dicatat' : 'Kas keluar dicatat')
      onClose()
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>Kas masuk / keluar</DialogTitle>
          </DialogHeader>
          <div className="grid grid-cols-2 gap-2" role="radiogroup">
            {(['in', 'out'] as const).map((type) => (
              <button
                key={type}
                type="button"
                role="radio"
                aria-checked={values.type === type}
                onClick={() => set('type', type)}
                className={cn('h-10 rounded-md border font-medium', values.type === type ? 'border-primary bg-accent text-accent-foreground' : 'text-muted-foreground')}
              >
                {type === 'in' ? 'Kas masuk' : 'Kas keluar'}
              </button>
            ))}
          </div>
          <Field label="Jumlah" htmlFor="cash-amount" error={errors.amount} required>
            <Input id="cash-amount" type="number" min={0} step="any" autoFocus value={values.amount} onChange={(e) => set('amount', e.target.value)} />
          </Field>
          <Field label="Alasan" htmlFor="cash-reason" error={errors.reason} required>
            <Input id="cash-reason" value={values.reason} onChange={(e) => set('reason', e.target.value)} placeholder="mis. Beli es batu" />
          </Field>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Batal
            </Button>
            <Button type="submit" disabled={cashMove.isPending}>
              Simpan
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

/** Kontrol pembukaan: hitung kas awal di laci (saran: kas akhir sesi sebelumnya). */
export function OpeningDialog({
  sessionId,
  cashControl,
  onOpened,
  onClose,
}: {
  sessionId: number
  cashControl: boolean
  onOpened: () => void
  onClose: () => void
}) {
  const { data, isLoading } = useSession(sessionId)
  const opening = useOpeningControl()

  if (isLoading || !data) {
    return (
      <Dialog open onOpenChange={(open) => !open && onClose()}>
        <DialogContent>
          <DialogTitle>Buka kasir</DialogTitle>
          <Skeleton className="h-24" />
        </DialogContent>
      </Dialog>
    )
  }

  return (
    <OpeningForm
      sessionId={sessionId}
      cashControl={cashControl}
      suggested={data.suggested_opening_cash ?? 0}
      submitting={opening.isPending}
      onSubmit={async (cash, notes) => {
        try {
          await opening.mutateAsync({ id: sessionId, cash, notes })
          onOpened()
        } catch (error) {
          toast.error(errorMessage(error))
        }
      }}
      onClose={onClose}
    />
  )
}

function OpeningForm({
  cashControl,
  suggested,
  submitting,
  onSubmit,
  onClose,
}: {
  sessionId: number
  cashControl: boolean
  suggested: number
  submitting: boolean
  onSubmit: (cash: string, notes: string | null) => void
  onClose: () => void
}) {
  const [cash, setCash] = useState(String(suggested))
  const [notes, setNotes] = useState('')

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <form
          onSubmit={(e) => {
            e.preventDefault()
            onSubmit(cashControl ? cash || '0' : '0', notes || null)
          }}
          className="grid gap-4"
        >
          <DialogHeader>
            <DialogTitle>Kontrol pembukaan</DialogTitle>
            <DialogDescription>{cashControl ? 'Hitung uang tunai di laci kasir sebelum mulai berjualan.' : 'Mulai sesi kasir.'}</DialogDescription>
          </DialogHeader>
          {cashControl && (
            <Field label="Kas awal" htmlFor="opening-cash" hint={`Kas akhir sesi sebelumnya: ${formatMoney(suggested)}`}>
              <Input id="opening-cash" type="number" min={0} step="any" autoFocus value={cash} onChange={(e) => setCash(e.target.value)} />
            </Field>
          )}
          <Field label="Catatan pembukaan" htmlFor="opening-notes">
            <Textarea id="opening-notes" rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />
          </Field>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Batal
            </Button>
            <Button type="submit" disabled={submitting}>
              Buka kasir
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

/** Tutup kasir: bandingkan kas yang diharapkan dengan hasil hitung. */
export function CloseSessionDialog({ sessionId, onClosed, onClose }: { sessionId: number; onClosed: () => void; onClose: () => void }) {
  const { data, isLoading } = useSession(sessionId)
  const close = useCloseSession()
  const [counted, setCounted] = useState<string | null>(null)
  const [notes, setNotes] = useState('')

  const summary = data?.summary
  const expected = summary?.cash?.expected ?? 0
  const countedValue = counted ?? String(expected)
  const difference = Math.round((Number(countedValue || 0) - expected) * 100) / 100
  const overLimit = summary?.max_difference != null && Math.abs(difference) > summary.max_difference

  async function submit() {
    try {
      await close.mutateAsync({ sessionId, counted_cash: summary?.cash ? countedValue || '0' : null, notes: notes || null })
      toast.success('Sesi kasir ditutup')
      onClosed()
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>Tutup kasir</DialogTitle>
          <DialogDescription>
            {data?.data.name} · dibuka {formatDate(data?.data.opened_at)}
          </DialogDescription>
        </DialogHeader>
        {isLoading || !summary ? (
          <Skeleton className="h-40" />
        ) : (
          <div className="grid gap-4 text-sm">
            <div className="bg-muted/50 flex justify-between rounded-md px-3 py-2">
              <span>{summary.orders.count} pesanan</span>
              <span className="font-semibold tabular-nums">{formatMoney(summary.orders.amount)}</span>
            </div>
            {summary.cash && (
              <div className="grid gap-1 rounded-md border p-3">
                <div className="mb-1 font-semibold">{summary.cash.name}</div>
                <Row label="Kas awal" value={summary.cash.opening} />
                <Row label="Pembayaran tunai (bersih kembalian)" value={summary.cash.payments} />
                {summary.cash.moves.map((m) => (
                  <Row key={m.id} label={`${m.amount > 0 ? 'Kas masuk' : 'Kas keluar'}: ${m.reason}`} value={m.amount} />
                ))}
                <Row label="Seharusnya" value={expected} strong />
                <div className="mt-2 grid grid-cols-2 items-end gap-3">
                  <Field label="Kas terhitung" htmlFor="counted-cash">
                    <Input id="counted-cash" type="number" min={0} step="any" value={countedValue} onChange={(e) => setCounted(e.target.value)} />
                  </Field>
                  <div className={cn('pb-2 text-right font-semibold tabular-nums', difference !== 0 && 'text-destructive')} data-testid="cash-difference">
                    Selisih {formatMoney(difference)}
                  </div>
                </div>
                {overLimit && (
                  <p className="text-destructive text-xs">Selisih melebihi batas {formatMoney(summary.max_difference)}. Hanya manajer POS yang bisa menutup.</p>
                )}
              </div>
            )}
            {summary.non_cash.length > 0 && (
              <div className="grid gap-1 rounded-md border p-3">
                {summary.non_cash.map((m) => (
                  <Row key={m.payment_method_id} label={`${m.name} (${m.count} transaksi)`} value={m.amount} />
                ))}
              </div>
            )}
            <Field label="Catatan penutupan" htmlFor="closing-notes">
              <Textarea id="closing-notes" rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />
            </Field>
          </div>
        )}
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Batal
          </Button>
          <Button onClick={submit} disabled={!summary || close.isPending}>
            Tutup kasir
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function Row({ label, value, strong }: { label: string; value: number; strong?: boolean }) {
  return (
    <div className={cn('flex justify-between gap-4', strong && 'border-t pt-1 font-semibold')}>
      <span className={strong ? '' : 'text-muted-foreground'}>{label}</span>
      <span className="tabular-nums">{formatMoney(value)}</span>
    </div>
  )
}
