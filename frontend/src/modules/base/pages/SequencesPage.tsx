import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { Field } from '@/components/form/Field'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { useSaveSequence, useSequences } from '@/modules/base/api'
import type { Sequence } from '@/modules/base/types'

const resetLabels: Record<Sequence['reset'], string> = { never: 'Tidak pernah', yearly: 'Tiap tahun', monthly: 'Tiap bulan' }

const columns: Column<Sequence>[] = [
  { key: 'name', header: 'Dokumen', cell: (s) => <span className="font-medium">{s.name}</span> },
  { key: 'module', header: 'Modul', hideOnMobile: true, cell: (s) => s.module_label && <Badge variant="outline">{s.module_label}</Badge> },
  { key: 'preview', header: 'Nomor berikutnya', cell: (s) => <code className="bg-muted rounded px-1.5 py-0.5 text-xs">{s.preview}</code> },
  { key: 'reset', header: 'Reset', hideOnMobile: true, cell: (s) => resetLabels[s.reset] },
]

/** Pratinjau di sisi klien (sama dengan logika backend). */
function preview(values: { prefix: string; suffix: string; padding: number; next_number: number }) {
  const now = new Date()
  const map: Record<string, string> = {
    '{YYYY}': String(now.getFullYear()),
    '{YY}': String(now.getFullYear()).slice(-2),
    '{MM}': String(now.getMonth() + 1).padStart(2, '0'),
    '{DD}': String(now.getDate()).padStart(2, '0'),
  }
  const fill = (s: string) => s.replace(/\{YYYY\}|\{YY\}|\{MM\}|\{DD\}/g, (m) => map[m])
  const number = String(Math.max(1, Number(values.next_number) || 1)).padStart(Number(values.padding) || 1, '0')
  return fill(values.prefix) + number + fill(values.suffix)
}

function SequenceDialog({ sequence, onClose }: { sequence: Sequence; onClose: () => void }) {
  const save = useSaveSequence()
  const form = useFormState({
    name: sequence.name,
    prefix: sequence.prefix,
    suffix: sequence.suffix,
    padding: sequence.padding,
    next_number: sequence.next_number,
    reset: sequence.reset,
  })
  const { values, set, errors } = form

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      await save.mutateAsync({ id: sequence.id, input: values })
      toast.success('Penomoran disimpan')
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
            <DialogTitle>{sequence.name}</DialogTitle>
            <DialogDescription>
              Gunakan {'{YYYY}'}, {'{YY}'}, {'{MM}'}, {'{DD}'} untuk tahun, bulan, dan tanggal.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Nama" htmlFor="seq-name" error={errors.name} className="sm:col-span-2">
              <Input id="seq-name" value={values.name} onChange={(e) => set('name', e.target.value)} />
            </Field>
            <Field label="Awalan" htmlFor="seq-prefix" error={errors.prefix}>
              <Input id="seq-prefix" value={values.prefix} onChange={(e) => set('prefix', e.target.value)} />
            </Field>
            <Field label="Akhiran" htmlFor="seq-suffix" error={errors.suffix}>
              <Input id="seq-suffix" value={values.suffix} onChange={(e) => set('suffix', e.target.value)} />
            </Field>
            <Field label="Jumlah digit" htmlFor="seq-padding" error={errors.padding}>
              <Input id="seq-padding" type="number" min={1} max={10} value={values.padding} onChange={(e) => set('padding', Number(e.target.value))} />
            </Field>
            <Field label="Nomor berikutnya" htmlFor="seq-next" error={errors.next_number}>
              <Input id="seq-next" type="number" min={1} value={values.next_number} onChange={(e) => set('next_number', Number(e.target.value))} />
            </Field>
            <Field label="Reset nomor" htmlFor="seq-reset" error={errors.reset} className="sm:col-span-2">
              <NativeSelect id="seq-reset" value={values.reset} onChange={(e) => set('reset', e.target.value as Sequence['reset'])}>
                {Object.entries(resetLabels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </NativeSelect>
            </Field>
          </div>
          <p className="text-muted-foreground text-sm">
            Pratinjau: <code className="bg-muted text-foreground rounded px-1.5 py-0.5">{preview(values)}</code>
          </p>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Batal
            </Button>
            <Button type="submit" disabled={save.isPending}>
              Simpan
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

export function SequencesPage() {
  const { data, isLoading } = useSequences()
  const [editing, setEditing] = useState<Sequence | null>(null)

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <PageHeader title="Penomoran Dokumen" />
      <DataTable
        columns={columns}
        rows={data}
        isLoading={isLoading}
        rowKey={(s) => s.id}
        onRowClick={setEditing}
        empty="Penomoran muncul setelah modul Penjualan, Pembelian, Inventori, atau POS diaktifkan."
      />
      {editing && <SequenceDialog key={editing.id} sequence={editing} onClose={() => setEditing(null)} />}
    </div>
  )
}
