import { useState, type FormEvent } from 'react'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'

import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { CheckField, Field } from '@/components/form/Field'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { usePaymentMethods, useSavePaymentMethod } from '../api'
import type { PaymentMethod } from '../types'

const columns: Column<PaymentMethod>[] = [
  { key: 'name', header: 'Nama', cell: (m) => <span className="font-medium">{m.name}</span> },
  { key: 'type', header: 'Tipe', cell: (m) => (m.type === 'cash' ? 'Tunai (kontrol kas)' : 'Bank / non-tunai') },
  { key: 'active', header: 'Status', cell: (m) => (m.is_active ? <Badge variant="success">Aktif</Badge> : <Badge variant="outline">Nonaktif</Badge>) },
]

function MethodDialog({ method, onClose }: { method: PaymentMethod | null; onClose: () => void }) {
  const save = useSavePaymentMethod()
  const form = useFormState({ name: method?.name ?? '', type: method?.type ?? ('bank' as PaymentMethod['type']), sequence: method?.sequence ?? 10, is_active: method?.is_active ?? true })
  const { values, set, errors } = form

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      await save.mutateAsync({ id: method?.id, input: values })
      toast.success('Metode pembayaran disimpan')
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
            <DialogTitle>{method ? method.name : 'Metode pembayaran baru'}</DialogTitle>
          </DialogHeader>
          <Field label="Nama" htmlFor="pm-name" error={errors.name} required>
            <Input id="pm-name" autoFocus value={values.name} onChange={(e) => set('name', e.target.value)} placeholder="mis. GoPay" />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Tipe" htmlFor="pm-type" error={errors.type}>
              <NativeSelect id="pm-type" value={values.type} onChange={(e) => set('type', e.target.value as PaymentMethod['type'])}>
                <option value="cash">Tunai (kontrol kas)</option>
                <option value="bank">Bank / non-tunai</option>
              </NativeSelect>
            </Field>
            <Field label="Urutan" htmlFor="pm-seq" error={errors.sequence}>
              <Input id="pm-seq" type="number" min={0} value={values.sequence} onChange={(e) => set('sequence', Number(e.target.value))} />
            </Field>
          </div>
          <CheckField id="pm-active" label="Aktif">
            <Checkbox id="pm-active" checked={values.is_active} onCheckedChange={(v) => set('is_active', v === true)} />
          </CheckField>
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

export function PaymentMethodsPage() {
  const { data, isLoading } = usePaymentMethods()
  const [editing, setEditing] = useState<PaymentMethod | 'new' | null>(null)

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <PageHeader
        title="Metode Pembayaran"
        actions={
          <Button onClick={() => setEditing('new')}>
            <Plus /> Baru
          </Button>
        }
      />
      <DataTable columns={columns} rows={data} isLoading={isLoading} rowKey={(m) => m.id} onRowClick={setEditing} />
      {editing && <MethodDialog key={editing === 'new' ? 'new' : editing.id} method={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </div>
  )
}
