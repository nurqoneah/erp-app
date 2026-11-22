import { useState, type FormEvent } from 'react'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'

import { PageHeader } from '@/components/data/PageHeader'
import { Field } from '@/components/form/Field'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { useCreateLocation, useSaveWarehouse, useWarehouses } from '@/modules/inventory/api'
import type { Warehouse } from '@/modules/inventory/types'

function WarehouseDialog({ onClose }: { onClose: () => void }) {
  const save = useSaveWarehouse()
  const form = useFormState({ name: '', code: '' })
  const { values, set, errors } = form

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      await save.mutateAsync({ input: values })
      toast.success('Gudang dibuat')
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
            <DialogTitle>Gudang baru</DialogTitle>
          </DialogHeader>
          <Field label="Nama" htmlFor="wh-name" error={errors.name} required>
            <Input id="wh-name" autoFocus value={values.name} onChange={(e) => set('name', e.target.value)} placeholder="mis. Gudang Padang" />
          </Field>
          <Field label="Kode" htmlFor="wh-code" error={errors.code} required hint="Maks. 10 huruf/angka, dipakai sebagai nama lokasi, mis. PDG">
            <Input id="wh-code" value={values.code} onChange={(e) => set('code', e.target.value.toUpperCase())} maxLength={10} />
          </Field>
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

function LocationDialog({ warehouse, onClose }: { warehouse: Warehouse; onClose: () => void }) {
  const create = useCreateLocation()
  const form = useFormState({ name: '', parent_id: warehouse.stock_location_id })
  const { values, set, errors } = form

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      await create.mutateAsync(values)
      toast.success('Lokasi dibuat')
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
            <DialogTitle>Lokasi baru di {warehouse.name}</DialogTitle>
          </DialogHeader>
          <Field label="Di dalam">
            <div className="flex flex-wrap gap-1">
              {warehouse.locations.map((l) => (
                <button
                  key={l.id}
                  type="button"
                  onClick={() => set('parent_id', l.id)}
                  className={
                    'rounded-md border px-2 py-1 text-xs ' + (values.parent_id === l.id ? 'border-primary bg-accent text-accent-foreground' : 'text-muted-foreground')
                  }
                >
                  {l.complete_name}
                </button>
              ))}
            </div>
          </Field>
          <Field label="Nama lokasi" htmlFor="loc-name" error={errors.name} required>
            <Input id="loc-name" autoFocus value={values.name} onChange={(e) => set('name', e.target.value)} placeholder="mis. Rak A" />
          </Field>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Batal
            </Button>
            <Button type="submit" disabled={create.isPending}>
              Simpan
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

export function WarehousesPage() {
  const { data: warehouses, isLoading } = useWarehouses()
  const save = useSaveWarehouse()
  const [creating, setCreating] = useState(false)
  const [addingTo, setAddingTo] = useState<Warehouse | null>(null)

  async function toggle(warehouse: Warehouse, active: boolean) {
    try {
      await save.mutateAsync({ id: warehouse.id, input: { name: warehouse.name, is_active: active } })
      toast.success(active ? 'Gudang diaktifkan' : 'Gudang dinonaktifkan')
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <PageHeader
        title="Gudang"
        actions={
          <Button onClick={() => setCreating(true)}>
            <Plus /> Gudang
          </Button>
        }
      />
      <div className="grid gap-4 md:grid-cols-2">
        {isLoading && <Skeleton className="h-40 rounded-xl" />}
        {warehouses?.map((w) => (
          <Card key={w.id} className="gap-3">
            <CardHeader className="flex flex-row items-center gap-3">
              <CardTitle className="flex-1">
                {w.name} <Badge variant="outline">{w.code}</Badge>
              </CardTitle>
              <Switch checked={w.is_active} onCheckedChange={(v) => toggle(w, v)} aria-label={`Aktifkan ${w.name}`} />
            </CardHeader>
            <CardContent>
              <ul className="divide-y text-sm">
                {w.locations.map((l) => (
                  <li key={l.id} className="py-1.5">
                    {l.complete_name}
                  </li>
                ))}
              </ul>
              <Button variant="ghost" size="sm" className="mt-2" onClick={() => setAddingTo(w)}>
                <Plus /> Lokasi
              </Button>
            </CardContent>
          </Card>
        ))}
      </div>
      {creating && <WarehouseDialog onClose={() => setCreating(false)} />}
      {addingTo && <LocationDialog warehouse={addingTo} onClose={() => setAddingTo(null)} />}
    </div>
  )
}
