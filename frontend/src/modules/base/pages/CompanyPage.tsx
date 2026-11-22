import { type FormEvent } from 'react'
import { toast } from 'sonner'

import { PageHeader } from '@/components/data/PageHeader'
import { Field, FormSection } from '@/components/form/Field'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { useCompany, useSaveCompany } from '@/modules/base/api'

const fields = ['name', 'legal_name', 'tax_id', 'email', 'phone', 'website', 'street', 'city', 'state', 'zip', 'country', 'currency'] as const
type CompanyForm = Record<(typeof fields)[number], string>

export function CompanyPage() {
  const { data: company, isLoading } = useCompany()

  if (isLoading || !company) return <Skeleton className="m-6 h-64" />

  return <CompanyFormView initial={Object.fromEntries(fields.map((f) => [f, company[f] ?? ''])) as CompanyForm} />
}

function CompanyFormView({ initial }: { initial: CompanyForm }) {
  const save = useSaveCompany()
  const form = useFormState<CompanyForm>(initial)
  const { values, set, errors } = form

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      await save.mutateAsync(Object.fromEntries(Object.entries(values).map(([k, v]) => [k, v || null])))
      toast.success('Profil perusahaan disimpan')
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
    }
  }

  const input = (key: keyof CompanyForm, props: React.ComponentProps<typeof Input> = {}) => (
    <Input id={key} value={values[key]} onChange={(e) => set(key, e.target.value)} aria-invalid={!!errors[key]} {...props} />
  )

  return (
    <form onSubmit={handleSubmit} className="mx-auto max-w-3xl px-4 py-6">
      <PageHeader
        title="Profil Perusahaan"
        actions={
          <Button type="submit" disabled={save.isPending}>
            {save.isPending ? 'Menyimpan…' : 'Simpan'}
          </Button>
        }
      />
      <Card>
        <CardContent className="grid gap-6">
          <FormSection>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Nama perusahaan" htmlFor="name" error={errors.name} required>
                {input('name')}
              </Field>
              <Field label="Nama resmi" htmlFor="legal_name" error={errors.legal_name} hint="Mis. PT Maju Jaya Sejahtera">
                {input('legal_name')}
              </Field>
              <Field label="NPWP" htmlFor="tax_id" error={errors.tax_id}>
                {input('tax_id')}
              </Field>
              <Field label="Mata uang" htmlFor="currency" error={errors.currency} required hint="Kode ISO, mis. IDR">
                {input('currency', { maxLength: 3, className: 'uppercase' })}
              </Field>
            </div>
          </FormSection>
          <FormSection title="Kontak">
            <div className="grid gap-4 sm:grid-cols-3">
              <Field label="Email" htmlFor="email" error={errors.email}>
                {input('email', { type: 'email' })}
              </Field>
              <Field label="Telepon" htmlFor="phone" error={errors.phone}>
                {input('phone')}
              </Field>
              <Field label="Website" htmlFor="website" error={errors.website}>
                {input('website', { placeholder: 'https://' })}
              </Field>
            </div>
          </FormSection>
          <FormSection title="Alamat">
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Jalan" htmlFor="street" error={errors.street} className="sm:col-span-2">
                {input('street')}
              </Field>
              <Field label="Kota" htmlFor="city" error={errors.city}>
                {input('city')}
              </Field>
              <Field label="Provinsi" htmlFor="state" error={errors.state}>
                {input('state')}
              </Field>
              <Field label="Kode pos" htmlFor="zip" error={errors.zip}>
                {input('zip')}
              </Field>
              <Field label="Negara" htmlFor="country" error={errors.country} required>
                {input('country')}
              </Field>
            </div>
          </FormSection>
        </CardContent>
      </Card>
    </form>
  )
}
