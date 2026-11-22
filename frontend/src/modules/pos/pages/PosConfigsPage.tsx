import { useState, type FormEvent } from 'react'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'

import { PageHeader } from '@/components/data/PageHeader'
import { CheckField, Field } from '@/components/form/Field'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useWarehouses } from '@/modules/inventory/api'
import { usePaymentMethods, usePosConfigs, useSavePosConfig } from '../api'
import type { PosConfig } from '../types'

function ConfigDialog({ config, onClose }: { config: PosConfig | null; onClose: () => void }) {
  const save = useSavePosConfig()
  const { data: warehouses = [] } = useWarehouses()
  const { data: methods = [] } = usePaymentMethods()
  const form = useFormState({
    name: config?.name ?? '',
    warehouse_id: config?.warehouse.id ?? (null as number | null),
    payment_method_ids: config?.payment_methods.map((m) => m.id) ?? ([] as number[]),
    receipt_header: config?.receipt_header ?? '',
    receipt_footer: config?.receipt_footer ?? '',
    set_maximum_difference: config?.set_maximum_difference ?? false,
    amount_authorized_diff: config?.amount_authorized_diff ?? '0',
    is_active: config?.is_active ?? true,
  })
  const { values, set, errors } = form
  const warehouseId = values.warehouse_id ?? warehouses[0]?.id ?? null

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      await save.mutateAsync({ id: config?.id, input: { ...values, warehouse_id: warehouseId } })
      toast.success('Point of Sale disimpan')
      onClose()
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
    }
  }

  function toggleMethod(id: number, checked: boolean) {
    set('payment_method_ids', checked ? [...values.payment_method_ids, id] : values.payment_method_ids.filter((m) => m !== id))
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-xl">
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>{config ? config.name : 'Point of Sale baru'}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Nama" htmlFor="pos-name" error={errors.name} required>
              <Input id="pos-name" autoFocus value={values.name} onChange={(e) => set('name', e.target.value)} placeholder="mis. Toko Pusat" />
            </Field>
            <Field label="Gudang (sumber stok)" htmlFor="pos-wh" error={errors.warehouse_id} required>
              <NativeSelect id="pos-wh" value={warehouseId ?? ''} onChange={(e) => set('warehouse_id', Number(e.target.value))}>
                {warehouses.map((w) => (
                  <option key={w.id} value={w.id}>
                    {w.name}
                  </option>
                ))}
              </NativeSelect>
            </Field>
          </div>
          <Field label="Metode pembayaran" error={errors.payment_method_ids} required>
            <div className="grid gap-2 sm:grid-cols-2">
              {methods.map((m) => (
                <CheckField key={m.id} id={`pm-${m.id}`} label={`${m.name}${m.type === 'cash' ? ' (tunai)' : ''}`}>
                  <Checkbox id={`pm-${m.id}`} checked={values.payment_method_ids.includes(m.id)} onCheckedChange={(v) => toggleMethod(m.id, v === true)} />
                </CheckField>
              ))}
            </div>
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Kepala struk" htmlFor="pos-header" error={errors.receipt_header}>
              <Textarea id="pos-header" rows={2} value={values.receipt_header} onChange={(e) => set('receipt_header', e.target.value)} placeholder="Alamat / telepon toko" />
            </Field>
            <Field label="Kaki struk" htmlFor="pos-footer" error={errors.receipt_footer}>
              <Textarea id="pos-footer" rows={2} value={values.receipt_footer} onChange={(e) => set('receipt_footer', e.target.value)} />
            </Field>
          </div>
          <div className="grid items-end gap-4 sm:grid-cols-2">
            <CheckField id="pos-maxdiff" label="Batasi selisih kas saat tutup kasir">
              <Checkbox id="pos-maxdiff" checked={values.set_maximum_difference} onCheckedChange={(v) => set('set_maximum_difference', v === true)} />
            </CheckField>
            {values.set_maximum_difference && (
              <Field label="Selisih maksimal" htmlFor="pos-diff" error={errors.amount_authorized_diff}>
                <Input id="pos-diff" type="number" min={0} step="any" value={values.amount_authorized_diff} onChange={(e) => set('amount_authorized_diff', e.target.value)} />
              </Field>
            )}
          </div>
          <CheckField id="pos-active" label="Aktif">
            <Checkbox id="pos-active" checked={values.is_active} onCheckedChange={(v) => set('is_active', v === true)} />
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

export function PosConfigsPage() {
  const { data: configs, isLoading } = usePosConfigs(true)
  const [editing, setEditing] = useState<PosConfig | 'new' | null>(null)

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <PageHeader
        title="Point of Sale"
        actions={
          <Button onClick={() => setEditing('new')}>
            <Plus /> Baru
          </Button>
        }
      />
      <div className="grid gap-4 md:grid-cols-2">
        {isLoading && <Skeleton className="h-40 rounded-xl" />}
        {configs?.map((c) => (
          <Card key={c.id} className="cursor-pointer gap-3 hover:shadow-md" onClick={() => setEditing(c)}>
            <CardHeader className="flex flex-row items-center gap-2">
              <CardTitle className="flex-1">{c.name}</CardTitle>
              {!c.is_active && <Badge variant="outline">Nonaktif</Badge>}
            </CardHeader>
            <CardContent className="grid gap-2 text-sm">
              <div className="text-muted-foreground">Gudang: {c.warehouse.name}</div>
              <div className="flex flex-wrap gap-1">
                {c.payment_methods.map((m) => (
                  <Badge key={m.id} variant="secondary">
                    {m.name}
                  </Badge>
                ))}
              </div>
              {c.set_maximum_difference && <div className="text-muted-foreground">Selisih kas maks. {formatMoney(c.amount_authorized_diff)}</div>}
            </CardContent>
          </Card>
        ))}
      </div>
      {editing && <ConfigDialog key={editing === 'new' ? 'new' : editing.id} config={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </div>
  )
}
