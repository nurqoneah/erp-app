import { useState, type FormEvent } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { ConfirmDialog } from '@/components/data/ConfirmDialog'
import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { SearchInput } from '@/components/data/SearchInput'
import { Field } from '@/components/form/Field'
import { RecordPicker, type PickerOption } from '@/components/form/RecordPicker'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { useFormState } from '@/hooks/useFormState'
import { useListParams } from '@/hooks/useListParams'
import { errorMessage } from '@/lib/api'
import { searchCategories, useCategories, useDeleteCategory, useSaveCategory } from '@/modules/product/api'
import type { ProductCategory } from '@/modules/product/types'

const columns: Column<ProductCategory>[] = [
  { key: 'complete_name', header: 'Kategori', sortKey: 'complete_name', cell: (c) => <span className="font-medium">{c.complete_name}</span> },
  { key: 'products', header: 'Produk', className: 'text-right', cell: (c) => c.products_count },
]

function parentLabel(category: ProductCategory): string {
  return category.complete_name.split(' / ').slice(0, -1).join(' / ')
}

function CategoryDialog({ category, onClose }: { category: ProductCategory | null; onClose: () => void }) {
  const save = useSaveCategory()
  const remove = useDeleteCategory()
  const form = useFormState({ name: category?.name ?? '', parent_id: category?.parent_id ?? null as number | null })
  const { values, set, errors } = form
  const [parent, setParent] = useState<PickerOption | null>(
    category?.parent_id ? { id: category.parent_id, label: parentLabel(category) } : null,
  )

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      await save.mutateAsync({ id: category?.id, input: values })
      toast.success('Kategori disimpan')
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
            <DialogTitle>{category ? 'Ubah kategori' : 'Kategori baru'}</DialogTitle>
          </DialogHeader>
          <Field label="Nama" htmlFor="cat-name" error={errors.name} required>
            <Input id="cat-name" autoFocus value={values.name} onChange={(e) => set('name', e.target.value)} aria-invalid={!!errors.name} />
          </Field>
          <Field label="Induk" htmlFor="cat-parent" error={errors.parent_id}>
            <RecordPicker
              id="cat-parent"
              value={parent}
              onChange={(option) => {
                setParent(option)
                set('parent_id', option?.id ?? null)
              }}
              queryKey="product-categories"
              search={searchCategories}
              placeholder="Tanpa induk"
              invalid={!!errors.parent_id}
            />
          </Field>
          <DialogFooter className="sm:justify-between">
            {category ? (
              <ConfirmDialog
                title="Hapus kategori?"
                description={`Kategori "${category.complete_name}" akan dihapus.`}
                confirmLabel="Hapus"
                destructive
                onConfirm={async () => {
                  try {
                    await remove.mutateAsync(category.id)
                    toast.success('Kategori dihapus')
                    onClose()
                  } catch (error) {
                    toast.error(errorMessage(error))
                  }
                }}
                trigger={(open) => (
                  <Button type="button" variant="ghost" className="text-destructive" onClick={open}>
                    <Trash2 /> Hapus
                  </Button>
                )}
              />
            ) : (
              <span />
            )}
            <div className="flex gap-2">
              <Button type="button" variant="outline" onClick={onClose}>
                Batal
              </Button>
              <Button type="submit" disabled={save.isPending}>
                Simpan
              </Button>
            </div>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

export function CategoriesPage() {
  const { can } = useAuth()
  const canManage = can('product.product.manage')
  const [params, setParams] = useListParams({ sort: 'complete_name' })
  const { data, isLoading } = useCategories(params)
  const [editing, setEditing] = useState<ProductCategory | 'new' | null>(null)

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <PageHeader
        title="Kategori Produk"
        actions={
          canManage && (
            <Button onClick={() => setEditing('new')}>
              <Plus /> Baru
            </Button>
          )
        }
      />
      <div className="mb-3">
        <SearchInput value={String(params.search ?? '')} onChange={(search) => setParams({ search })} placeholder="Cari kategori…" />
      </div>
      <DataTable
        columns={columns}
        page={data}
        isLoading={isLoading}
        rowKey={(c) => c.id}
        onRowClick={canManage ? setEditing : undefined}
        sort={params.sort}
        direction={params.direction}
        onSortChange={(sort, direction) => setParams({ sort, direction })}
        onPageChange={(page) => setParams({ page })}
      />
      {editing && (
        <CategoryDialog
          key={editing === 'new' ? 'new' : editing.id}
          category={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
        />
      )}
    </div>
  )
}
