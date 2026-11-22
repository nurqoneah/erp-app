import { useState, type FormEvent } from 'react'
import { Ban, CheckCircle2, FileText, RotateCcw, Trash2, Truck } from 'lucide-react'
import { useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { ConfirmDialog } from '@/components/data/ConfirmDialog'
import { NotFound } from '@/components/data/NotFound'
import { PageHeader } from '@/components/data/PageHeader'
import { AmountSummary } from '@/components/documents/AmountSummary'
import { LinesEditor } from '@/components/documents/LinesEditor'
import { SmartButtons } from '@/components/documents/SmartButtons'
import { StatusBar } from '@/components/documents/StatusBar'
import { documentTotals } from '@/components/documents/amounts'
import { newLine, toEditable, toPayload, type EditableLine } from '@/components/documents/lines'
import { Field } from '@/components/form/Field'
import { RecordPicker, type PickerOption } from '@/components/form/RecordPicker'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { formatNumber, todayISO } from '@/lib/format'
import { searchPartners } from '@/modules/contacts/api'
import { useCreateOrderInvoice, useDeleteOrder, useOrder, useOrderAction, useSaveOrder } from './api'
import type { Order, OrderKind, RelatedInvoice } from './types'

export function OrderFormPage({ kind }: { kind: OrderKind }) {
  const { id } = useParams()
  const orderId = id ? Number(id) : undefined
  const { data: order, isLoading, isError } = useOrder(kind, orderId)

  if (orderId && isLoading) return <Skeleton className="m-6 h-96" />
  if (orderId && (isError || !order)) return <NotFound backTo={kind.draftListPath} />

  // Key per status: form diisi ulang dari data server setiap kali status berubah.
  return <OrderForm key={order ? `${order.id}-${order.state}` : 'new'} kind={kind} order={order} />
}

function OrderForm({ kind, order }: { kind: OrderKind; order?: Order }) {
  const navigate = useNavigate()
  const { can } = useAuth()
  const save = useSaveOrder(kind)
  const action = useOrderAction(kind)
  const createInvoice = useCreateOrderInvoice(kind)
  const remove = useDeleteOrder(kind)
  const state = order?.state ?? 'draft'
  const editable = can(kind.permission.manage) && state === 'draft'
  const canConfirm = can(kind.permission.confirm)

  const form = useFormState({
    order_date: order?.order_date ?? todayISO(),
    extra_date: (order?.[kind.extraDateField] as string | null | undefined) ?? '',
    notes: order?.notes ?? '',
  })
  const { values, set, errors } = form
  const [partner, setPartner] = useState<PickerOption | null>(order?.partner ? { id: order.partner.id, label: order.partner.name } : null)
  const [lines, setLines] = useState<EditableLine[]>(order?.lines?.map(toEditable) ?? [newLine()])
  const totals = documentTotals(lines)

  async function persist(): Promise<Order | null> {
    try {
      return await save.mutateAsync({
        id: order?.id,
        input: {
          partner_id: partner?.id ?? null,
          order_date: values.order_date,
          [kind.extraDateField]: values.extra_date || null,
          notes: values.notes || null,
          lines: toPayload(lines).map(({ id: _id, ...line }) => line),
        },
      })
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
      return null
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const saved = await persist()
    if (saved) {
      toast.success(`${kind.labels.draft} disimpan`)
      if (!order) navigate(`${kind.basePath}/${saved.id}`, { replace: true })
    }
  }

  async function run(name: 'confirm' | 'cancel' | 'set-to-draft', message: string, target: Order | null | undefined = order) {
    if (!target) return
    // Dokumen baru sudah tersimpan: pindah ke halamannya dulu agar kegagalan tidak membuat duplikat.
    if (!order) navigate(`${kind.basePath}/${target.id}`, { replace: true })
    try {
      await action.mutateAsync({ id: target.id, action: name })
      toast.success(message)
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  async function makeInvoice() {
    if (!order) return
    try {
      const invoiceId = await createInvoice.mutateAsync(order.id)
      toast.success(`${kind.labels.invoice} draf dibuat`)
      navigate(`${kind.invoiceBasePath}/${invoiceId}`)
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  const invoices = (order?.[kind.invoicesField] as RelatedInvoice[] | undefined) ?? []
  const pickings = order?.pickings ?? []
  const busy = save.isPending || action.isPending || createInvoice.isPending
  const lineById = new Map(order?.lines?.map((l) => [l.id, l]))

  return (
    <form onSubmit={handleSubmit} className="mx-auto max-w-6xl px-4 py-6">
      <PageHeader
        crumbs={[{ label: state === 'confirmed' ? kind.labels.order : kind.labels.draft, to: state === 'confirmed' ? kind.basePath : kind.draftListPath }]}
        title={order?.number ?? `${kind.labels.draft} baru`}
        actions={
          <>
            {editable && order && (
              <ConfirmDialog
                title={`Hapus ${order.number}?`}
                description={`${kind.labels.draft} ini akan dihapus permanen.`}
                confirmLabel="Hapus"
                destructive
                onConfirm={async () => {
                  try {
                    await remove.mutateAsync(order.id)
                    toast.success(`${kind.labels.draft} dihapus`)
                    navigate(kind.draftListPath)
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
            )}
            {editable && (
              <Button type="submit" variant="outline" disabled={busy}>
                Simpan
              </Button>
            )}
            {editable && canConfirm && (
              <Button
                type="button"
                disabled={busy}
                onClick={async () => {
                  const saved = await persist()
                  if (saved) await run('confirm', `${saved.number} dikonfirmasi`, saved)
                }}
              >
                <CheckCircle2 /> Konfirmasi
              </Button>
            )}
            {order && state === 'confirmed' && canConfirm && order[kind.invoiceStatusField] === `to_${kind.invoiceAction}` && (
              <Button type="button" onClick={makeInvoice} disabled={busy}>
                <FileText /> {kind.labels.createInvoice}
              </Button>
            )}
            {order && state === 'confirmed' && canConfirm && (
              <ConfirmDialog
                title={`Batalkan ${order.number}?`}
                description={`${kind.labels.transfer} yang belum diproses ikut dibatalkan.`}
                confirmLabel="Batalkan pesanan"
                destructive
                onConfirm={() => run('cancel', 'Pesanan dibatalkan')}
                trigger={(open) => (
                  <Button type="button" variant="outline" onClick={open} disabled={busy}>
                    <Ban /> Batalkan
                  </Button>
                )}
              />
            )}
            {order && state === 'cancelled' && canConfirm && (
              <Button type="button" variant="outline" onClick={() => run('set-to-draft', `Dijadikan ${kind.labels.draft.toLowerCase()} lagi`)} disabled={busy}>
                <RotateCcw /> Jadikan {kind.labels.draft.toLowerCase()}
              </Button>
            )}
          </>
        }
      />

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <StatusBar
          steps={
            state === 'cancelled'
              ? [{ key: 'cancelled', label: 'Dibatalkan' }]
              : [
                  { key: 'draft', label: kind.labels.draft },
                  { key: 'confirmed', label: kind.labels.order },
                ]
          }
          current={state}
        />
        <SmartButtons
          items={[
            {
              label: kind.labels.transfer,
              count: pickings.length,
              to: pickings.length === 1 ? `/inventory/transfers/${pickings[0].id}` : `/inventory/transfers?search=${encodeURIComponent(order?.number ?? '')}&state=all`,
              icon: <Truck />,
            },
            {
              label: kind.labels.invoice,
              count: invoices.length,
              to: invoices.length === 1 ? `${kind.invoiceBasePath}/${invoices[0].id}` : `${kind.invoiceBasePath}?search=${encodeURIComponent(order?.number ?? '')}`,
              icon: <FileText />,
            },
          ]}
        />
      </div>

      <Card>
        <CardContent className="grid gap-6">
          <fieldset disabled={!editable} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Field label={kind.labels.partner} htmlFor="partner" error={errors.partner_id} required className="sm:col-span-2">
              <RecordPicker
                id="partner"
                value={partner}
                onChange={setPartner}
                queryKey={`partners-${kind.partnerRole}`}
                search={(term) => searchPartners(term, { role: kind.partnerRole })}
                placeholder={`Pilih ${kind.labels.partner.toLowerCase()}…`}
                invalid={!!errors.partner_id}
                disabled={!editable}
              />
            </Field>
            <Field label="Tanggal" htmlFor="order_date" error={errors.order_date}>
              <Input id="order_date" type="date" value={values.order_date} onChange={(e) => set('order_date', e.target.value)} />
            </Field>
            <Field label={kind.labels.extraDate} htmlFor="extra_date" error={errors[kind.extraDateField]}>
              <Input id="extra_date" type="date" value={values.extra_date} onChange={(e) => set('extra_date', e.target.value)} />
            </Field>
          </fieldset>

          <LinesEditor
            lines={lines}
            onChange={setLines}
            readOnly={!editable}
            priceField={kind.priceField}
            productFilters={kind.productFilters}
            errors={errors}
            pickerKey={`products-${kind.partnerRole}`}
            extraColumns={
              state === 'confirmed'
                ? [
                    {
                      header: kind.labels.qtyDone,
                      cell: (line) => {
                        const source = line.id ? lineById.get(line.id) : undefined
                        return source && source.product.type !== 'service' ? formatNumber(source[kind.qtyDoneField] as string) : '—'
                      },
                    },
                    {
                      header: kind.labels.qtyInvoiced,
                      cell: (line) => formatNumber((line.id ? lineById.get(line.id)?.[kind.qtyInvoicedField] : 0) as string),
                    },
                  ]
                : []
            }
          />

          <div className="flex flex-wrap items-start gap-6">
            <Field label="Catatan" htmlFor="notes" className="min-w-64 flex-1">
              <Textarea id="notes" rows={3} value={values.notes} onChange={(e) => set('notes', e.target.value)} disabled={!editable} />
            </Field>
            <AmountSummary
              untaxed={editable ? totals.untaxed : (order?.amount_untaxed ?? 0)}
              tax={editable ? totals.tax : (order?.amount_tax ?? 0)}
              total={editable ? totals.total : (order?.amount_total ?? 0)}
            />
          </div>
        </CardContent>
      </Card>
    </form>
  )
}
