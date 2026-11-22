import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeader } from '@/components/data/PageHeader'
import { LinesEditor } from '@/components/documents/LinesEditor'
import { newLine, toPayload, type EditableLine } from '@/components/documents/lines'
import { Field } from '@/components/form/Field'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { useFormState } from '@/hooks/useFormState'
import { useListParams } from '@/hooks/useListParams'
import { errorMessage } from '@/lib/api'
import { formatDate, formatNumber } from '@/lib/format'
import { useAdjust, useAdjustments, useLocations } from '@/modules/inventory/api'
import type { AdjustmentRow } from '@/modules/inventory/types'

const columns: Column<AdjustmentRow>[] = [
  { key: 'date', header: 'Tanggal', cell: (r) => formatDate(r.done_at) },
  { key: 'reference', header: 'Referensi', hideOnMobile: true, cell: (r) => r.reference },
  { key: 'product', header: 'Produk', cell: (r) => <span className="font-medium">{r.product.name}</span> },
  { key: 'location', header: 'Lokasi', hideOnMobile: true, cell: (r) => r.location },
  {
    key: 'change',
    header: 'Perubahan',
    className: 'text-right',
    cell: (r) => (
      <span className={Number(r.change) < 0 ? 'text-destructive tabular-nums' : 'text-emerald-600 tabular-nums'}>
        {Number(r.change) > 0 ? '+' : ''}
        {formatNumber(r.change)} {r.product.uom}
      </span>
    ),
  },
]

export function AdjustmentsPage() {
  const { data: locations = [] } = useLocations('internal')
  const adjust = useAdjust()
  const [params, setParams] = useListParams()
  const history = useAdjustments(params)
  const form = useFormState({ location_id: null as number | null, reason: '' })
  const { values, set, errors } = form
  const [lines, setLines] = useState<EditableLine[]>([newLine({ quantity: '0' })])
  const locationId = values.location_id ?? locations[0]?.id ?? null

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      const result = await adjust.mutateAsync({ location_id: locationId, reason: values.reason || null, lines: toPayload(lines, false) })
      toast.success(result.message)
      setLines([newLine({ quantity: '0' })])
      set('reason', '')
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
    }
  }

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <PageHeader title="Penyesuaian Stok" />

      <Card className="mb-6">
        <CardHeader>
          <CardTitle>Stock opname</CardTitle>
          <CardDescription>
            Isi <strong>jumlah hasil hitung</strong> per produk. Stok di lokasi akan disamakan dengan jumlah ini.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Lokasi" htmlFor="location" error={errors.location_id}>
                <NativeSelect id="location" value={locationId ?? ''} onChange={(e) => set('location_id', Number(e.target.value))}>
                  {locations.map((l) => (
                    <option key={l.id} value={l.id}>
                      {l.complete_name}
                    </option>
                  ))}
                </NativeSelect>
              </Field>
              <Field label="Alasan" htmlFor="reason" error={errors.reason}>
                <Input id="reason" value={values.reason} onChange={(e) => set('reason', e.target.value)} placeholder="mis. Opname akhir bulan" />
              </Field>
            </div>
            <LinesEditor
              lines={lines}
              onChange={setLines}
              withPrices={false}
              errors={errors}
              pickerKey="products-storable"
              productFilters={{ type: 'storable' }}
            />
            <Button type="submit" className="w-fit" disabled={adjust.isPending}>
              {adjust.isPending ? 'Menerapkan…' : 'Terapkan penyesuaian'}
            </Button>
          </form>
        </CardContent>
      </Card>

      <h2 className="mb-2 font-semibold">Riwayat penyesuaian</h2>
      <DataTable
        columns={columns}
        page={history.data}
        isLoading={history.isLoading}
        rowKey={(r) => r.id}
        onPageChange={(page) => setParams({ page })}
        empty="Belum ada penyesuaian stok."
      />
    </div>
  )
}
