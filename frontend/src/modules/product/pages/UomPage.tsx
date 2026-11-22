import { useState, type FormEvent } from 'react'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { PageHeader } from '@/components/data/PageHeader'
import { CheckField, Field } from '@/components/form/Field'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { Skeleton } from '@/components/ui/skeleton'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { formatNumber } from '@/lib/format'
import { useCreateUomCategory, useSaveUom, useUoms } from '@/modules/product/api'
import type { Uom, UomCategory, UomType } from '@/modules/product/types'

const typeLabels: Record<UomType, string> = {
  reference: 'Satuan acuan',
  bigger: 'Lebih besar dari acuan',
  smaller: 'Lebih kecil dari acuan',
}

function describe(uom: Uom, reference?: Uom): string {
  if (uom.type === 'reference' || !reference) return 'Satuan acuan'
  return uom.type === 'bigger'
    ? `1 ${uom.name} = ${formatNumber(uom.ratio)} ${reference.name}`
    : `1 ${reference.name} = ${formatNumber(uom.ratio)} ${uom.name}`
}

function UomDialog({ category, uom, onClose }: { category: UomCategory; uom: Uom | null; onClose: () => void }) {
  const save = useSaveUom()
  const form = useFormState({
    category_id: category.id,
    name: uom?.name ?? '',
    type: uom?.type ?? ('bigger' as UomType),
    ratio: uom?.ratio ?? '1',
    is_active: uom?.is_active ?? true,
  })
  const { values, set, errors } = form
  const reference = category.uoms.find((u) => u.type === 'reference')

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      await save.mutateAsync({ id: uom?.id, input: values })
      toast.success('Satuan disimpan')
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
            <DialogTitle>{uom ? `Ubah ${uom.name}` : 'Satuan baru'}</DialogTitle>
            <DialogDescription>Kategori {category.name}</DialogDescription>
          </DialogHeader>
          <Field label="Nama" htmlFor="uom-name" error={errors.name} required>
            <Input id="uom-name" autoFocus value={values.name} onChange={(e) => set('name', e.target.value)} aria-invalid={!!errors.name} />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Tipe" htmlFor="uom-type" error={errors.type}>
              <NativeSelect id="uom-type" value={values.type} onChange={(e) => set('type', e.target.value as UomType)} disabled={uom?.type === 'reference'}>
                {(Object.keys(typeLabels) as UomType[]).map((t) => (
                  <option key={t} value={t}>
                    {typeLabels[t]}
                  </option>
                ))}
              </NativeSelect>
            </Field>
            {values.type !== 'reference' && (
              <Field label="Rasio" htmlFor="uom-ratio" error={errors.ratio} required>
                <Input id="uom-ratio" type="number" min={0} step="any" value={values.ratio} onChange={(e) => set('ratio', e.target.value)} />
              </Field>
            )}
          </div>
          {values.type !== 'reference' && reference && values.name && (
            <p className="text-muted-foreground text-sm">{describe({ ...values, id: 0, rounding: '0.01' } as Uom, reference)}</p>
          )}
          <CheckField id="uom-active" label="Aktif">
            <Checkbox id="uom-active" checked={values.is_active} onCheckedChange={(v) => set('is_active', v === true)} />
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

function CategoryDialog({ onClose }: { onClose: () => void }) {
  const create = useCreateUomCategory()
  const form = useFormState({ name: '', reference_name: '' })
  const { values, set, errors } = form

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      await create.mutateAsync(values)
      toast.success('Kategori satuan dibuat')
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
            <DialogTitle>Kategori satuan baru</DialogTitle>
            <DialogDescription>Satuan hanya bisa dikonversi dalam kategori yang sama.</DialogDescription>
          </DialogHeader>
          <Field label="Nama kategori" htmlFor="uc-name" error={errors.name} required>
            <Input id="uc-name" autoFocus value={values.name} onChange={(e) => set('name', e.target.value)} placeholder="mis. Kemasan" />
          </Field>
          <Field label="Satuan acuan" htmlFor="uc-ref" error={errors.reference_name} required>
            <Input id="uc-ref" value={values.reference_name} onChange={(e) => set('reference_name', e.target.value)} placeholder="mis. Pak" />
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

export function UomPage() {
  const { can } = useAuth()
  const canManage = can('product.product.manage')
  const { data: categories, isLoading } = useUoms(true)
  const [editing, setEditing] = useState<{ category: UomCategory; uom: Uom | null } | null>(null)
  const [creatingCategory, setCreatingCategory] = useState(false)

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <PageHeader
        title="Satuan"
        actions={
          canManage && (
            <Button onClick={() => setCreatingCategory(true)}>
              <Plus /> Kategori satuan
            </Button>
          )
        }
      />
      <div className="grid gap-4 md:grid-cols-2">
        {isLoading && Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-44 rounded-xl" />)}
        {categories?.map((category) => {
          const reference = category.uoms.find((u) => u.type === 'reference')
          return (
            <Card key={category.id} className="gap-3">
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>{category.name}</CardTitle>
                {canManage && (
                  <Button variant="ghost" size="sm" onClick={() => setEditing({ category, uom: null })}>
                    <Plus /> Satuan
                  </Button>
                )}
              </CardHeader>
              <CardContent>
                <ul className="divide-y text-sm">
                  {category.uoms.map((uom) => (
                    <li key={uom.id}>
                      <button
                        type="button"
                        disabled={!canManage}
                        onClick={() => setEditing({ category, uom })}
                        className="hover:bg-muted/50 flex w-full items-center gap-2 px-1 py-2 text-left disabled:hover:bg-transparent"
                      >
                        <span className="font-medium">{uom.name}</span>
                        {uom.type === 'reference' && <Badge variant="secondary">Acuan</Badge>}
                        {!uom.is_active && <Badge variant="outline">Nonaktif</Badge>}
                        <span className="text-muted-foreground ml-auto text-xs">{describe(uom, reference)}</span>
                      </button>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )
        })}
      </div>
      {editing && (
        <UomDialog
          key={editing.uom?.id ?? `new-${editing.category.id}`}
          category={editing.category}
          uom={editing.uom}
          onClose={() => setEditing(null)}
        />
      )}
      {creatingCategory && <CategoryDialog onClose={() => setCreatingCategory(false)} />}
    </div>
  )
}
