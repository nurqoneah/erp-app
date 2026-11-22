import { useState, type FormEvent } from 'react'
import { Archive, ArchiveRestore, Building2, Trash2, User } from 'lucide-react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { ConfirmDialog } from '@/components/data/ConfirmDialog'
import { NotFound } from '@/components/data/NotFound'
import { PageHeader } from '@/components/data/PageHeader'
import { CheckField, Field, FormSection } from '@/components/form/Field'
import { RecordPicker, type PickerOption } from '@/components/form/RecordPicker'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'
import { searchPartners, useDeletePartner, usePartner, useSavePartner } from '@/modules/contacts/api'
import type { Partner, PartnerInput, PartnerType } from '@/modules/contacts/types'

const empty: PartnerInput = {
  type: 'company',
  name: '',
  parent_id: null,
  ref: null,
  job_title: null,
  email: null,
  phone: null,
  mobile: null,
  website: null,
  tax_id: null,
  street: null,
  city: null,
  state: null,
  zip: null,
  country: 'Indonesia',
  is_customer: true,
  is_vendor: false,
  notes: null,
  is_active: true,
}

function toInput(p: Partner): PartnerInput {
  const { id: _id, display_name: _d, parent: _p, contacts: _c, ...rest } = p
  return rest
}

export function ContactFormPage() {
  const { id } = useParams()
  const partnerId = id ? Number(id) : undefined
  const [searchParams] = useSearchParams()
  const { data: partner, isLoading, isError } = usePartner(partnerId)

  if (partnerId && isLoading) return <Skeleton className="m-6 h-96" />
  if (partnerId && (isError || !partner)) return <NotFound backTo="/contacts" />

  if (partner) {
    return (
      <ContactForm
        partner={partner}
        initial={toInput(partner)}
        initialParent={partner.parent ? { id: partner.parent.id, label: partner.parent.name } : null}
      />
    )
  }

  // Membuat kontak orang langsung dari halaman perusahaan: /contacts/new?parent=ID&parent_name=...
  const parentId = Number(searchParams.get('parent')) || null
  return (
    <ContactForm
      initial={parentId ? { ...empty, type: 'person', parent_id: parentId } : empty}
      initialParent={parentId ? { id: parentId, label: searchParams.get('parent_name') ?? '' } : null}
    />
  )
}

function ContactForm({ partner, initial, initialParent }: { partner?: Partner; initial: PartnerInput; initialParent: PickerOption | null }) {
  const partnerId = partner?.id
  const navigate = useNavigate()
  const { can } = useAuth()
  const readOnly = !can('contacts.partner.manage')
  const save = useSavePartner()
  const remove = useDeletePartner()
  const form = useFormState<PartnerInput>(initial)
  const { values, set, errors } = form
  const [parent, setParent] = useState<PickerOption | null>(initialParent)

  const text = (key: keyof PartnerInput) => ({
    id: key,
    value: (values[key] as string | null) ?? '',
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => set(key, (e.target.value || null) as never),
    'aria-invalid': !!errors[key],
  })

  async function persist(input: PartnerInput, message: string) {
    try {
      const saved = await save.mutateAsync({ id: partnerId, input })
      form.setValues(input)
      toast.success(message)
      if (!partnerId) navigate(`/contacts/${saved.id}`, { replace: true })
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
    }
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    void persist(values, 'Kontak disimpan')
  }

  const isCompany = values.type === 'company'

  return (
    <form onSubmit={handleSubmit} className="mx-auto max-w-4xl px-4 py-6">
      <PageHeader
        crumbs={[{ label: 'Kontak', to: '/contacts' }]}
        title={partnerId ? partner?.display_name ?? '' : 'Baru'}
        actions={
          !readOnly && (
            <>
              {partnerId && (
                <>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => persist({ ...values, is_active: !values.is_active }, values.is_active ? 'Kontak diarsipkan' : 'Kontak dipulihkan')}
                  >
                    {values.is_active ? <Archive /> : <ArchiveRestore />}
                    {values.is_active ? 'Arsipkan' : 'Pulihkan'}
                  </Button>
                  <ConfirmDialog
                    title="Hapus kontak?"
                    description="Kontak akan dihapus. Gunakan Arsipkan bila hanya ingin menyembunyikannya."
                    confirmLabel="Hapus"
                    destructive
                    onConfirm={async () => {
                      try {
                        await remove.mutateAsync(partnerId)
                        toast.success('Kontak dihapus')
                        navigate('/contacts')
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

      {partnerId && !values.is_active && (
        <p className="bg-muted text-muted-foreground mb-4 rounded-md px-3 py-2 text-sm">Kontak ini diarsipkan.</p>
      )}

      <Card>
        <CardContent>
          <fieldset disabled={readOnly} className="grid gap-6">
            <div className="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Tipe kontak">
              {(['company', 'person'] as PartnerType[]).map((type) => (
                <button
                  key={type}
                  type="button"
                  role="radio"
                  aria-checked={values.type === type}
                  onClick={() => {
                    set('type', type)
                    if (type === 'company') {
                      set('parent_id', null)
                      setParent(null)
                    }
                  }}
                  className={cn(
                    'flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm',
                    values.type === type ? 'border-primary bg-accent text-accent-foreground' : 'text-muted-foreground',
                  )}
                >
                  {type === 'company' ? <Building2 className="size-4" /> : <User className="size-4" />}
                  {type === 'company' ? 'Perusahaan' : 'Perorangan'}
                </button>
              ))}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Nama" htmlFor="name" error={errors.name} required className="sm:col-span-2">
                <Input {...text('name')} className="text-base font-medium" placeholder={isCompany ? 'mis. PT Maju Jaya' : 'mis. Budi Santoso'} />
              </Field>
              {!isCompany && (
                <>
                  <Field label="Perusahaan" htmlFor="parent_id" error={errors.parent_id}>
                    <RecordPicker
                      id="parent_id"
                      value={parent}
                      onChange={(option) => {
                        setParent(option)
                        set('parent_id', option?.id ?? null)
                      }}
                      queryKey="partners-companies"
                      search={(term) => searchPartners(term, { type: 'company' })}
                      placeholder="Pilih perusahaan…"
                      invalid={!!errors.parent_id}
                    />
                  </Field>
                  <Field label="Jabatan" htmlFor="job_title" error={errors.job_title}>
                    <Input {...text('job_title')} />
                  </Field>
                </>
              )}
            </div>

            <div className="flex flex-wrap gap-6">
              <CheckField id="is_customer" label="Pelanggan">
                <Checkbox id="is_customer" checked={values.is_customer} onCheckedChange={(v) => set('is_customer', v === true)} />
              </CheckField>
              <CheckField id="is_vendor" label="Pemasok">
                <Checkbox id="is_vendor" checked={values.is_vendor} onCheckedChange={(v) => set('is_vendor', v === true)} />
              </CheckField>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
              <FormSection title="Kontak">
                <Field label="Email" htmlFor="email" error={errors.email}>
                  <Input {...text('email')} type="email" />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="Telepon" htmlFor="phone" error={errors.phone}>
                    <Input {...text('phone')} type="tel" />
                  </Field>
                  <Field label="Ponsel" htmlFor="mobile" error={errors.mobile}>
                    <Input {...text('mobile')} type="tel" />
                  </Field>
                </div>
                <Field label="Website" htmlFor="website" error={errors.website}>
                  <Input {...text('website')} placeholder="https://" />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label={isCompany ? 'NPWP' : 'NPWP / NIK'} htmlFor="tax_id" error={errors.tax_id}>
                    <Input {...text('tax_id')} />
                  </Field>
                  <Field label="Kode internal" htmlFor="ref" error={errors.ref}>
                    <Input {...text('ref')} />
                  </Field>
                </div>
              </FormSection>

              <FormSection title="Alamat">
                <Field label="Jalan" htmlFor="street" error={errors.street}>
                  <Input {...text('street')} />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="Kota" htmlFor="city" error={errors.city}>
                    <Input {...text('city')} />
                  </Field>
                  <Field label="Provinsi" htmlFor="state" error={errors.state}>
                    <Input {...text('state')} />
                  </Field>
                  <Field label="Kode pos" htmlFor="zip" error={errors.zip}>
                    <Input {...text('zip')} />
                  </Field>
                  <Field label="Negara" htmlFor="country" error={errors.country}>
                    <Input {...text('country')} />
                  </Field>
                </div>
              </FormSection>
            </div>

            <FormSection title="Catatan">
              <Textarea {...text('notes')} rows={3} aria-label="Catatan" />
            </FormSection>
          </fieldset>
        </CardContent>
      </Card>

      {partner && isCompany && (
        <Card className="mt-4 gap-3">
          <CardContent>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="font-semibold">Kontak di perusahaan ini</h2>
              {!readOnly && (
                <Button asChild variant="outline" size="sm">
                  <Link to={`/contacts/new?parent=${partner.id}&parent_name=${encodeURIComponent(partner.name)}`}>Tambah kontak</Link>
                </Button>
              )}
            </div>
            {partner.contacts?.length ? (
              <ul className="divide-y">
                {partner.contacts.map((c) => (
                  <li key={c.id}>
                    <Link to={`/contacts/${c.id}`} className="hover:bg-muted/50 flex flex-wrap items-center gap-x-3 gap-y-1 px-1 py-2 text-sm">
                      <span className="font-medium">{c.name}</span>
                      {c.job_title && <span className="text-muted-foreground">{c.job_title}</span>}
                      <span className="text-muted-foreground ml-auto">{c.email ?? c.phone ?? c.mobile}</span>
                      {!c.is_active && <Badge variant="outline">Arsip</Badge>}
                    </Link>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-muted-foreground text-sm">Belum ada kontak orang.</p>
            )}
          </CardContent>
        </Card>
      )}
    </form>
  )
}
