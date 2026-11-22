import { useMemo, useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Archive, ArchiveRestore, Trash2 } from 'lucide-react'
import { useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { ExtensionSlot } from '@/components/ExtensionSlot'
import { ConfirmDialog } from '@/components/data/ConfirmDialog'
import { NotFound } from '@/components/data/NotFound'
import { PageHeader } from '@/components/data/PageHeader'
import { CheckField, Field, FormSection } from '@/components/form/Field'
import { RecordPicker, type PickerOption } from '@/components/form/RecordPicker'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { searchCategories, useDeleteProduct, useProduct, useSaveProduct, useUoms } from '@/modules/product/api'
import { productTypeLabels, type Product, type ProductInput, type ProductType, type UomCategory } from '@/modules/product/types'

const empty: ProductInput = {
  name: '',
  sku: null,
  barcode: null,
  type: 'storable',
  category_id: null,
  uom_id: null,
  purchase_uom_id: null,
  sale_price: '0',
  cost: '0',
  can_be_sold: true,
  can_be_purchased: true,
  available_in_pos: true,
  description: null,
  is_active: true,
}

function toInput(p: Product): ProductInput {
  return {
    name: p.name,
    sku: p.sku,
    barcode: p.barcode,
    type: p.type,
    category_id: p.category_id,
    uom_id: p.uom_id,
    purchase_uom_id: p.purchase_uom_id,
    sale_price: p.sale_price,
    cost: p.cost,
    can_be_sold: p.can_be_sold,
    can_be_purchased: p.can_be_purchased,
    available_in_pos: p.available_in_pos,
    description: p.description,
    is_active: p.is_active,
  }
}

export function ProductFormPage() {
  const { id } = useParams()
  const productId = id ? Number(id) : undefined
  const { data: product, isLoading, isError } = useProduct(productId)
  const { data: uomCategories, isLoading: loadingUoms } = useUoms()
  // Default produk baru: kategori pertama ("Semua") dan satuan "Unit".
  const { data: defaultCategory, isLoading: loadingCategory } = useQuery({
    queryKey: ['picker', 'product-categories', 'default'],
    queryFn: async () => (await searchCategories(''))[0] ?? null,
    enabled: !productId,
  })

  if ((productId && isLoading) || loadingUoms || (!productId && loadingCategory)) return <Skeleton className="m-6 h-96" />
  if (productId && (isError || !product)) return <NotFound backTo="/products" />

  if (product) {
    return (
      <ProductForm
        product={product}
        uomCategories={uomCategories ?? []}
        initial={toInput(product)}
        initialCategory={product.category ? { id: product.category.id, label: product.category.complete_name } : null}
      />
    )
  }

  const unit = uomCategories?.flatMap((c) => c.uoms).find((u) => u.type === 'reference' && u.name === 'Unit')
  return (
    <ProductForm
      uomCategories={uomCategories ?? []}
      initial={{ ...empty, uom_id: unit?.id ?? null, purchase_uom_id: unit?.id ?? null, category_id: defaultCategory?.id ?? null }}
      initialCategory={defaultCategory ?? null}
    />
  )
}

type ProductFormProps = {
  product?: Product
  uomCategories: UomCategory[]
  initial: ProductInput
  initialCategory: PickerOption | null
}

function ProductForm({ product, uomCategories, initial, initialCategory }: ProductFormProps) {
  const productId = product?.id
  const navigate = useNavigate()
  const { can } = useAuth()
  const readOnly = !can('product.product.manage')
  const save = useSaveProduct()
  const remove = useDeleteProduct()
  const form = useFormState<ProductInput>(initial)
  const { values, set, errors } = form
  const [category, setCategory] = useState<PickerOption | null>(initialCategory)

  const uomCategoryOf = useMemo(() => {
    const map = new Map<number, number>()
    uomCategories.forEach((c) => c.uoms.forEach((u) => map.set(u.id, c.id)))
    return map
  }, [uomCategories])
  const purchaseUomChoices = uomCategories.find((c) => c.id === uomCategoryOf.get(values.uom_id ?? 0))?.uoms ?? []

  function changeUom(uomId: number) {
    set('uom_id', uomId)
    if (uomCategoryOf.get(uomId) !== uomCategoryOf.get(values.purchase_uom_id ?? 0)) set('purchase_uom_id', uomId)
  }

  async function persist(input: ProductInput, message: string) {
    try {
      const saved = await save.mutateAsync({ id: productId, input })
      form.setValues(input)
      toast.success(message)
      if (!productId) navigate(`/products/${saved.id}`, { replace: true })
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
    }
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    void persist(values, 'Produk disimpan')
  }

  const margin = Number(values.sale_price) - Number(values.cost)
  const marginPct = Number(values.sale_price) > 0 ? (margin / Number(values.sale_price)) * 100 : 0
  const text = (key: 'name' | 'sku' | 'barcode' | 'description') => ({
    id: key,
    value: values[key] ?? '',
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => set(key, e.target.value || (key === 'name' ? '' : null)),
    'aria-invalid': !!errors[key],
  })

  return (
    <form onSubmit={handleSubmit} className="mx-auto max-w-4xl px-4 py-6">
      <PageHeader
        crumbs={[{ label: 'Produk', to: '/products' }]}
        title={productId ? product?.name ?? '' : 'Baru'}
        actions={
          !readOnly && (
            <>
              {productId && (
                <>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => persist({ ...values, is_active: !values.is_active }, values.is_active ? 'Produk diarsipkan' : 'Produk dipulihkan')}
                  >
                    {values.is_active ? <Archive /> : <ArchiveRestore />}
                    {values.is_active ? 'Arsipkan' : 'Pulihkan'}
                  </Button>
                  <ConfirmDialog
                    title="Hapus produk?"
                    description="Produk akan dihapus. Gunakan Arsipkan bila hanya ingin menyembunyikannya."
                    confirmLabel="Hapus"
                    destructive
                    onConfirm={async () => {
                      try {
                        await remove.mutateAsync(productId)
                        toast.success('Produk dihapus')
                        navigate('/products')
                      } catch (error) {
                        toast.error(errorMessage(error))
                      }
                    }}
                    trigger={(open) => (
                      <Button type="button" variant="outline" size="icon" onClick={open} aria-label="Hapus">
                        <Trash2 />
                      </Button>
                    )}
                  />
                </>
              )}
              <Button type="submit" disabled={save.isPending}>
                {save.isPending ? 'Menyimpan…' : 'Simpan'}
              </Button>
            </>
          )
        }
      />

      {productId && !values.is_active && (
        <p className="bg-muted text-muted-foreground mb-4 rounded-md px-3 py-2 text-sm">Produk ini diarsipkan.</p>
      )}

      <Card>
        <CardContent>
          <fieldset disabled={readOnly} className="grid gap-6">
            <Field label="Nama produk" htmlFor="name" error={errors.name} required>
              <Input {...text('name')} className="text-base font-medium" placeholder="mis. Kopi Susu Gula Aren" />
            </Field>

            <div className="flex flex-wrap gap-6">
              <CheckField id="can_be_sold" label="Bisa dijual">
                <Checkbox id="can_be_sold" checked={values.can_be_sold} onCheckedChange={(v) => set('can_be_sold', v === true)} />
              </CheckField>
              <CheckField id="can_be_purchased" label="Bisa dibeli">
                <Checkbox id="can_be_purchased" checked={values.can_be_purchased} onCheckedChange={(v) => set('can_be_purchased', v === true)} />
              </CheckField>
              <CheckField id="available_in_pos" label="Tersedia di POS">
                <Checkbox id="available_in_pos" checked={values.available_in_pos} onCheckedChange={(v) => set('available_in_pos', v === true)} />
              </CheckField>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
              <FormSection title="Informasi umum">
                <Field label="Tipe produk" htmlFor="type" error={errors.type}>
                  <NativeSelect id="type" value={values.type} onChange={(e) => set('type', e.target.value as ProductType)}>
                    {(Object.keys(productTypeLabels) as ProductType[]).map((t) => (
                      <option key={t} value={t}>
                        {productTypeLabels[t]}
                      </option>
                    ))}
                  </NativeSelect>
                </Field>
                <Field label="Kategori" htmlFor="category_id" error={errors.category_id} required>
                  <RecordPicker
                    id="category_id"
                    value={category}
                    onChange={(option) => {
                      setCategory(option)
                      set('category_id', option?.id ?? null)
                    }}
                    queryKey="product-categories"
                    search={searchCategories}
                    clearable={false}
                    invalid={!!errors.category_id}
                  />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="Kode internal (SKU)" htmlFor="sku" error={errors.sku}>
                    <Input {...text('sku')} />
                  </Field>
                  <Field label="Barcode" htmlFor="barcode" error={errors.barcode}>
                    <Input {...text('barcode')} inputMode="numeric" />
                  </Field>
                </div>
              </FormSection>

              <FormSection title="Harga & satuan">
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="Harga jual" htmlFor="sale_price" error={errors.sale_price} required>
                    <Input
                      id="sale_price"
                      type="number"
                      min={0}
                      step="any"
                      value={values.sale_price}
                      onChange={(e) => set('sale_price', e.target.value)}
                      aria-invalid={!!errors.sale_price}
                    />
                  </Field>
                  <Field label="Harga pokok" htmlFor="cost" error={errors.cost} required>
                    <Input
                      id="cost"
                      type="number"
                      min={0}
                      step="any"
                      value={values.cost}
                      onChange={(e) => set('cost', e.target.value)}
                      aria-invalid={!!errors.cost}
                    />
                  </Field>
                </div>
                <p className="text-muted-foreground -mt-2 text-xs">
                  Margin: {formatMoney(margin)} ({marginPct.toFixed(1)}%)
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="Satuan" htmlFor="uom_id" error={errors.uom_id} required>
                    <NativeSelect id="uom_id" value={values.uom_id ?? ''} onChange={(e) => changeUom(Number(e.target.value))} aria-invalid={!!errors.uom_id}>
                      <option value="" disabled>
                        Pilih…
                      </option>
                      {uomCategories.map((c) => (
                        <optgroup key={c.id} label={c.name}>
                          {c.uoms.map((u) => (
                            <option key={u.id} value={u.id}>
                              {u.name}
                            </option>
                          ))}
                        </optgroup>
                      ))}
                    </NativeSelect>
                  </Field>
                  <Field label="Satuan beli" htmlFor="purchase_uom_id" error={errors.purchase_uom_id}>
                    <NativeSelect
                      id="purchase_uom_id"
                      value={values.purchase_uom_id ?? ''}
                      onChange={(e) => set('purchase_uom_id', Number(e.target.value))}
                      aria-invalid={!!errors.purchase_uom_id}
                    >
                      {purchaseUomChoices.map((u) => (
                        <option key={u.id} value={u.id}>
                          {u.name}
                        </option>
                      ))}
                    </NativeSelect>
                  </Field>
                </div>
              </FormSection>
            </div>

            <ExtensionSlot
              point="product.product.form"
              recordId={productId}
              values={values as Record<string, unknown>}
              set={(field, value) => set(field as keyof ProductInput, value as never)}
              errors={errors}
              readOnly={readOnly}
            />

            <FormSection title="Deskripsi">
              <Textarea {...text('description')} rows={3} aria-label="Deskripsi" />
            </FormSection>
          </fieldset>
        </CardContent>
      </Card>
    </form>
  )
}
