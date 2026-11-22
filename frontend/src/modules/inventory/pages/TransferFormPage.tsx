import { useState, type FormEvent } from 'react'
import { Ban, CheckCircle2 } from 'lucide-react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { ConfirmDialog } from '@/components/data/ConfirmDialog'
import { NotFound } from '@/components/data/NotFound'
import { PageHeader } from '@/components/data/PageHeader'
import { LinesEditor } from '@/components/documents/LinesEditor'
import { StatusBar } from '@/components/documents/StatusBar'
import { newLine, toEditable, toPayload, type EditableLine } from '@/components/documents/lines'
import { Field } from '@/components/form/Field'
import { RecordPicker, type PickerOption } from '@/components/form/RecordPicker'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { formatDate, todayISO } from '@/lib/format'
import { originLink } from '@/lib/origins'
import { searchPartners } from '@/modules/contacts/api'
import { useLocations, usePicking, usePickingAction, useSavePicking, type PickingInput } from '@/modules/inventory/api'
import { pickingTypeLabels, type Picking, type PickingType } from '@/modules/inventory/types'

export function TransferFormPage() {
  const { id } = useParams()
  const pickingId = id ? Number(id) : undefined
  const [searchParams] = useSearchParams()
  const { data: picking, isLoading, isError } = usePicking(pickingId)

  if (pickingId && isLoading) return <Skeleton className="m-6 h-96" />
  if (pickingId && (isError || !picking)) return <NotFound backTo="/inventory/transfers" />

  const type = (picking?.type ?? searchParams.get('type') ?? 'receipt') as PickingType
  return (
    <TransferForm
      key={picking ? `${picking.id}-${picking.state}` : 'new'}
      picking={picking}
      initialType={type in pickingTypeLabels ? type : 'receipt'}
    />
  )
}

function TransferForm({ picking, initialType }: { picking?: Picking; initialType: PickingType }) {
  const navigate = useNavigate()
  const { can } = useAuth()
  const save = useSavePicking()
  const action = usePickingAction()
  const { data: locations = [] } = useLocations()
  const canManage = can('inventory.transfer.manage')
  const editable = canManage && (!picking || picking.is_editable)

  const form = useFormState({
    type: initialType,
    scheduled_date: picking?.scheduled_date ?? todayISO(),
    source_location_id: picking?.source_location_id ?? (null as number | null),
    dest_location_id: picking?.dest_location_id ?? (null as number | null),
    notes: picking?.notes ?? '',
  })
  const { values, set, errors } = form
  const [partner, setPartner] = useState<PickerOption | null>(picking?.partner ? { id: picking.partner.id, label: picking.partner.name } : null)
  const [lines, setLines] = useState<EditableLine[]>(
    picking?.moves?.map((m) => toEditable({ ...m, product: { ...m.product } })) ?? [newLine()],
  )

  function payload(): PickingInput {
    return {
      ...(picking ? {} : { type: values.type }),
      partner_id: partner?.id ?? null,
      scheduled_date: values.scheduled_date,
      // Kosong = lokasi default sesuai tipe transfer (diisi server).
      source_location_id: values.source_location_id,
      dest_location_id: values.dest_location_id,
      notes: values.notes || null,
      lines: toPayload(lines, false),
    }
  }

  async function persist(): Promise<Picking | null> {
    try {
      const saved = await save.mutateAsync({ id: picking?.id, input: payload() })
      return saved
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
      return null
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const saved = await persist()
    if (saved) {
      toast.success('Transfer disimpan')
      if (!picking) navigate(`/inventory/transfers/${saved.id}`, { replace: true })
    }
  }

  async function validate() {
    // Simpan perubahan dulu bila transfer masih bisa diedit.
    const target = editable ? await persist() : picking
    if (!target) return
    // Transfer baru sudah tersimpan: pindah ke halamannya dulu, supaya bila validasi gagal
    // tombol berikutnya tidak membuat transfer kedua.
    if (!picking) navigate(`/inventory/transfers/${target.id}`, { replace: true })
    try {
      await action.mutateAsync({ id: target.id, action: 'validate' })
      toast.success(`${target.reference} divalidasi, stok diperbarui`)
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  const state = picking?.state ?? 'draft'
  const origin = originLink(picking?.origin_type, picking?.origin_id)
  const busy = save.isPending || action.isPending

  return (
    <form onSubmit={handleSubmit} className="mx-auto max-w-5xl px-4 py-6">
      <PageHeader
        crumbs={[{ label: 'Transfer', to: '/inventory/transfers' }]}
        title={picking?.reference ?? `${pickingTypeLabels[values.type]} baru`}
        actions={
          canManage &&
          state === 'draft' && (
            <>
              {picking && (
                <ConfirmDialog
                  title="Batalkan transfer?"
                  description={`${picking.reference} akan dibatalkan dan stok tidak berubah.`}
                  confirmLabel="Batalkan transfer"
                  destructive
                  onConfirm={async () => {
                    try {
                      await action.mutateAsync({ id: picking.id, action: 'cancel' })
                      toast.success('Transfer dibatalkan')
                    } catch (error) {
                      toast.error(errorMessage(error))
                    }
                  }}
                  trigger={(open) => (
                    <Button type="button" variant="outline" onClick={open} disabled={busy}>
                      <Ban /> Batalkan
                    </Button>
                  )}
                />
              )}
              {editable && (
                <Button type="submit" variant="outline" disabled={busy}>
                  Simpan
                </Button>
              )}
              <Button type="button" onClick={validate} disabled={busy}>
                <CheckCircle2 /> Validasi
              </Button>
            </>
          )
        }
      />

      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <StatusBar
          steps={state === 'cancelled' ? [{ key: 'cancelled', label: 'Dibatalkan' }] : [{ key: 'draft', label: 'Siap' }, { key: 'done', label: 'Selesai' }]}
          current={state}
        />
        {picking?.origin && (
          <span className="text-muted-foreground text-sm">
            Dokumen asal:{' '}
            {origin ? (
              <Link to={origin} className="text-primary font-medium hover:underline">
                {picking.origin}
              </Link>
            ) : (
              picking.origin
            )}
          </span>
        )}
      </div>

      <Card>
        <CardContent className="grid gap-6">
          <fieldset disabled={!editable} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {!picking && (
              <Field label="Tipe" htmlFor="type">
                <NativeSelect id="type" value={values.type} onChange={(e) => set('type', e.target.value as PickingType)}>
                  {(Object.keys(pickingTypeLabels) as PickingType[]).map((t) => (
                    <option key={t} value={t}>
                      {pickingTypeLabels[t]}
                    </option>
                  ))}
                </NativeSelect>
              </Field>
            )}
            <Field label={values.type === 'receipt' ? 'Pemasok' : values.type === 'delivery' ? 'Pelanggan' : 'Mitra'} htmlFor="partner" error={errors.partner_id}>
              <RecordPicker
                id="partner"
                value={partner}
                onChange={setPartner}
                queryKey="partners-all"
                search={(term) => searchPartners(term)}
                placeholder="Opsional"
                disabled={!editable}
              />
            </Field>
            <Field label="Tanggal rencana" htmlFor="scheduled_date" error={errors.scheduled_date}>
              <Input id="scheduled_date" type="date" value={values.scheduled_date} onChange={(e) => set('scheduled_date', e.target.value)} />
            </Field>
            <Field label="Dari lokasi" htmlFor="source" error={errors.source_location_id}>
              <NativeSelect
                id="source"
                value={values.source_location_id ?? ''}
                onChange={(e) => set('source_location_id', e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">Default ({values.type === 'receipt' ? 'Mitra/Pemasok' : 'stok gudang'})</option>
                {locations.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.complete_name}
                  </option>
                ))}
              </NativeSelect>
            </Field>
            <Field label="Ke lokasi" htmlFor="dest" error={errors.dest_location_id}>
              <NativeSelect
                id="dest"
                value={values.dest_location_id ?? ''}
                onChange={(e) => set('dest_location_id', e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">Default ({values.type === 'delivery' ? 'Mitra/Pelanggan' : 'stok gudang'})</option>
                {locations.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.complete_name}
                  </option>
                ))}
              </NativeSelect>
            </Field>
          </fieldset>

          <LinesEditor
            lines={lines}
            onChange={setLines}
            readOnly={!editable}
            withPrices={false}
            errors={errors}
            pickerKey="products-stock"
          />

          {picking?.done_at && <p className="text-muted-foreground text-sm">Divalidasi pada {formatDate(picking.done_at)}.</p>}

          <Field label="Catatan" htmlFor="notes">
            <Textarea id="notes" rows={2} value={values.notes} onChange={(e) => set('notes', e.target.value)} disabled={!editable} />
          </Field>
        </CardContent>
      </Card>
    </form>
  )
}
