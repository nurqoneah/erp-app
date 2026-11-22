import { useState, type FormEvent } from 'react'
import { Ban, CheckCircle2, RotateCcw, Trash2, Wallet } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { ConfirmDialog } from '@/components/data/ConfirmDialog'
import { NotFound } from '@/components/data/NotFound'
import { PageHeader } from '@/components/data/PageHeader'
import { AmountSummary } from '@/components/documents/AmountSummary'
import { LinesEditor } from '@/components/documents/LinesEditor'
import { StateBadge } from '@/components/documents/StateBadge'
import { StatusBar } from '@/components/documents/StatusBar'
import { documentTotals } from '@/components/documents/amounts'
import { newLine, toEditable, toPayload, type EditableLine } from '@/components/documents/lines'
import { stateOf } from '@/components/documents/states'
import { Field } from '@/components/form/Field'
import { RecordPicker, type PickerOption } from '@/components/form/RecordPicker'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { formatDate, formatMoney, todayISO } from '@/lib/format'
import { originLink } from '@/lib/origins'
import { searchPartners } from '@/modules/contacts/api'
import { useDeleteInvoice, useInvoice, useInvoiceAction, useRegisterPayment, useSaveInvoice, type InvoiceInput } from '@/modules/invoicing/api'
import { paymentMethodLabels, type Invoice, type InvoiceKind } from '@/modules/invoicing/types'

export function InvoiceFormPage({ kind }: { kind: InvoiceKind }) {
  const { id } = useParams()
  const invoiceId = id ? Number(id) : undefined
  const { data: invoice, isLoading, isError } = useInvoice(invoiceId)

  if (invoiceId && isLoading) return <Skeleton className="m-6 h-96" />
  if (invoiceId && (isError || !invoice || invoice.type !== kind.type)) return <NotFound backTo={kind.basePath} />

  // Key per status: form diisi ulang dari data server setiap kali status berubah (mis. setelah posting).
  return <InvoiceForm key={invoice ? `${invoice.id}-${invoice.state}` : 'new'} kind={kind} invoice={invoice} />
}

function PaymentDialog({ invoice, onClose }: { invoice: Invoice; onClose: () => void }) {
  const register = useRegisterPayment()
  const form = useFormState({ amount: invoice.amount_due, date: todayISO(), method: 'bank', reference: '' })
  const { values, set, errors } = form

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    // Jangan sampai submit ikut merambat ke form faktur (event React menembus portal).
    event.stopPropagation()
    try {
      const updated = await register.mutateAsync({ id: invoice.id, input: { ...values, reference: values.reference || null } })
      toast.success(updated.payment_state === 'paid' ? `${invoice.display_name} lunas` : 'Pembayaran dicatat')
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
            <DialogTitle>Catat pembayaran</DialogTitle>
            <DialogDescription>
              {invoice.display_name} · sisa {formatMoney(invoice.amount_due)}
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Jumlah" htmlFor="pay-amount" error={errors.amount} required>
              <Input id="pay-amount" type="number" min={0} step="any" value={values.amount} onChange={(e) => set('amount', e.target.value)} />
            </Field>
            <Field label="Tanggal" htmlFor="pay-date" error={errors.date} required>
              <Input id="pay-date" type="date" value={values.date} onChange={(e) => set('date', e.target.value)} />
            </Field>
            <Field label="Metode" htmlFor="pay-method" error={errors.method}>
              <NativeSelect id="pay-method" value={values.method} onChange={(e) => set('method', e.target.value)}>
                {Object.entries(paymentMethodLabels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </NativeSelect>
            </Field>
            <Field label="Referensi" htmlFor="pay-ref" error={errors.reference}>
              <Input id="pay-ref" value={values.reference} onChange={(e) => set('reference', e.target.value)} placeholder="mis. no. transfer" />
            </Field>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Batal
            </Button>
            <Button type="submit" disabled={register.isPending}>
              Simpan pembayaran
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

function InvoiceForm({ kind, invoice }: { kind: InvoiceKind; invoice?: Invoice }) {
  const navigate = useNavigate()
  const { can } = useAuth()
  const save = useSaveInvoice()
  const action = useInvoiceAction()
  const remove = useDeleteInvoice()
  const [paying, setPaying] = useState(false)
  const canManage = can('invoicing.invoice.manage')
  const state = invoice?.state ?? 'draft'
  const editable = canManage && state === 'draft'

  const form = useFormState({
    invoice_date: invoice?.invoice_date ?? '',
    due_date: invoice?.due_date ?? '',
    vendor_reference: invoice?.vendor_reference ?? '',
    notes: invoice?.notes ?? '',
  })
  const { values, set, errors } = form
  const [partner, setPartner] = useState<PickerOption | null>(invoice?.partner ? { id: invoice.partner.id, label: invoice.partner.name } : null)
  const [lines, setLines] = useState<EditableLine[]>(invoice?.lines?.map(toEditable) ?? [newLine()])
  const totals = documentTotals(lines)

  function payload(): InvoiceInput {
    return {
      ...(invoice ? {} : { type: kind.type }),
      partner_id: partner?.id ?? null,
      invoice_date: values.invoice_date || null,
      due_date: values.due_date || null,
      vendor_reference: values.vendor_reference || null,
      notes: values.notes || null,
      lines: toPayload(lines),
    }
  }

  async function persist(): Promise<Invoice | null> {
    try {
      return await save.mutateAsync({ id: invoice?.id, input: payload() })
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
      return null
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const saved = await persist()
    if (saved) {
      toast.success(`${kind.singular} disimpan`)
      if (!invoice) navigate(`${kind.basePath}/${saved.id}`, { replace: true })
    }
  }

  async function run(name: 'post' | 'cancel' | 'reset-to-draft', message: string, target: Invoice | undefined | null = invoice) {
    if (!target) return
    // Draf baru sudah tersimpan: pindah ke halamannya dulu agar kegagalan tidak membuat duplikat.
    if (!invoice) navigate(`${kind.basePath}/${target.id}`, { replace: true })
    try {
      const updated = await action.mutateAsync({ id: target.id, action: name })
      toast.success(name === 'post' ? `${updated.display_name} diposting` : message)
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  const origin = originLink(invoice?.origin_type, invoice?.origin_id)
  const hasPayments = (invoice?.payments?.length ?? 0) > 0
  const busy = save.isPending || action.isPending

  return (
    <>
    <form onSubmit={handleSubmit} className="mx-auto max-w-6xl px-4 py-6">
      <PageHeader
        crumbs={[{ label: kind.title, to: kind.basePath }]}
        title={invoice?.display_name ?? `${kind.singular} baru`}
        actions={
          <>
            {editable && invoice && !invoice.number && (
              <ConfirmDialog
                title={`Hapus ${kind.singular.toLowerCase()} draf?`}
                description="Draf ini akan dihapus permanen."
                confirmLabel="Hapus"
                destructive
                onConfirm={async () => {
                  try {
                    await remove.mutateAsync(invoice.id)
                    toast.success('Draf dihapus')
                    navigate(kind.basePath)
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
              <>
                <Button type="submit" variant="outline" disabled={busy}>
                  Simpan
                </Button>
                <Button
                  type="button"
                  disabled={busy}
                  onClick={async () => {
                    const saved = await persist()
                    if (saved) await run('post', '', saved)
                  }}
                >
                  <CheckCircle2 /> Posting
                </Button>
              </>
            )}
            {invoice && state === 'posted' && invoice.payment_state !== 'paid' && can('invoicing.payment.manage') && (
              <Button type="button" onClick={() => setPaying(true)}>
                <Wallet /> Catat pembayaran
              </Button>
            )}
            {invoice && canManage && state !== 'draft' && !hasPayments && (
              <Button type="button" variant="outline" disabled={busy} onClick={() => run('reset-to-draft', 'Dikembalikan ke draf')}>
                <RotateCcw /> Ke draf
              </Button>
            )}
            {invoice && canManage && state === 'posted' && !hasPayments && (
              <ConfirmDialog
                title={`Batalkan ${invoice.display_name}?`}
                description="Dokumen yang dibatalkan tidak dihitung lagi di pesanan asalnya."
                confirmLabel="Batalkan"
                destructive
                onConfirm={() => run('cancel', `${kind.singular} dibatalkan`)}
                trigger={(open) => (
                  <Button type="button" variant="outline" onClick={open} disabled={busy}>
                    <Ban /> Batalkan
                  </Button>
                )}
              />
            )}
          </>
        }
      />

      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <StatusBar
            steps={state === 'cancelled' ? [{ key: 'cancelled', label: 'Dibatalkan' }] : [{ key: 'draft', label: 'Draf' }, { key: 'posted', label: 'Diposting' }]}
            current={state}
          />
          {invoice && state === 'posted' && <StateBadge {...stateOf('payment', invoice.payment_state)} />}
        </div>
        {invoice?.origin && (
          <span className="text-muted-foreground text-sm">
            Asal:{' '}
            {origin ? (
              <Link to={origin} className="text-primary font-medium hover:underline">
                {invoice.origin}
              </Link>
            ) : (
              invoice.origin
            )}
          </span>
        )}
      </div>

      <Card>
        <CardContent className="grid gap-6">
          <fieldset disabled={!editable} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Field label={kind.partnerLabel} htmlFor="partner" error={errors.partner_id} required className="sm:col-span-2">
              <RecordPicker
                id="partner"
                value={partner}
                onChange={setPartner}
                queryKey={`partners-${kind.partnerRole}`}
                search={(term) => searchPartners(term, { role: kind.partnerRole })}
                placeholder={`Pilih ${kind.partnerLabel.toLowerCase()}…`}
                invalid={!!errors.partner_id}
                disabled={!editable}
              />
            </Field>
            <Field label="Tanggal" htmlFor="invoice_date" error={errors.invoice_date} hint={editable ? 'Kosong = tanggal posting' : undefined}>
              <Input id="invoice_date" type="date" value={values.invoice_date} onChange={(e) => set('invoice_date', e.target.value)} />
            </Field>
            <Field label="Jatuh tempo" htmlFor="due_date" error={errors.due_date}>
              <Input id="due_date" type="date" value={values.due_date} onChange={(e) => set('due_date', e.target.value)} />
            </Field>
            {kind.type === 'in_invoice' && (
              <Field label="No. faktur pemasok" htmlFor="vendor_reference" error={errors.vendor_reference} className="sm:col-span-2">
                <Input id="vendor_reference" value={values.vendor_reference} onChange={(e) => set('vendor_reference', e.target.value)} />
              </Field>
            )}
          </fieldset>

          <LinesEditor
            lines={lines}
            onChange={setLines}
            readOnly={!editable}
            priceField={kind.priceField}
            productFilters={kind.productFilters}
            allowNoProduct
            errors={errors}
            pickerKey={`products-${kind.partnerRole}`}
          />

          <div className="flex flex-wrap items-start gap-6">
            <Field label="Catatan" htmlFor="notes" className="min-w-64 flex-1">
              <Textarea id="notes" rows={3} value={values.notes} onChange={(e) => set('notes', e.target.value)} disabled={!editable} />
            </Field>
            <AmountSummary
              untaxed={editable ? totals.untaxed : (invoice?.amount_untaxed ?? 0)}
              tax={editable ? totals.tax : (invoice?.amount_tax ?? 0)}
              total={editable ? totals.total : (invoice?.amount_total ?? 0)}
              extra={
                invoice && state === 'posted'
                  ? [
                      { label: 'Sudah dibayar', value: invoice.amount_paid },
                      { label: 'Sisa', value: invoice.amount_due, strong: true },
                    ]
                  : []
              }
            />
          </div>
        </CardContent>
      </Card>

      {hasPayments && (
        <Card className="mt-4 gap-3">
          <CardContent>
            <h2 className="mb-2 font-semibold">Pembayaran</h2>
            <ul className="divide-y text-sm">
              {invoice!.payments!.map((p) => (
                <li key={p.id} className="flex flex-wrap gap-x-4 py-2">
                  <span>{formatDate(p.date)}</span>
                  <span className="text-muted-foreground">{paymentMethodLabels[p.method]}</span>
                  {p.reference && <span className="text-muted-foreground">{p.reference}</span>}
                  <span className="ml-auto font-medium tabular-nums">{formatMoney(p.amount)}</span>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}

    </form>
    {paying && invoice && <PaymentDialog invoice={invoice} onClose={() => setPaying(false)} />}
    </>
  )
}
