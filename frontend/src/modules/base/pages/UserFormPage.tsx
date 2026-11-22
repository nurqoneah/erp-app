import { type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { NotFound } from '@/components/data/NotFound'
import { PageHeader } from '@/components/data/PageHeader'
import { CheckField, Field, FormSection } from '@/components/form/Field'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { useRoleOptions, useSaveUser, useUser, type UserInput } from '@/modules/base/api'
import type { User } from '@/modules/base/types'

const empty: UserInput = { name: '', email: '', password: '', is_active: true, roles: [] }

export function UserFormPage() {
  const { id } = useParams()
  const userId = id ? Number(id) : undefined
  const { data: user, isLoading, isError } = useUser(userId)

  if (userId && isLoading) return <Skeleton className="m-6 h-64" />
  if (userId && (isError || !user)) return <NotFound backTo="/settings/users" />

  return (
    <UserForm
      user={user}
      initial={user ? { name: user.name, email: user.email, password: '', is_active: user.is_active, roles: [...user.roles] } : empty}
    />
  )
}

function UserForm({ user, initial }: { user?: User; initial: UserInput }) {
  const userId = user?.id
  const navigate = useNavigate()
  const { can, user: me } = useAuth()
  const readOnly = !can('base.user.manage')
  const { data: roleOptions = [] } = useRoleOptions({ enabled: !readOnly })
  const save = useSaveUser()
  const form = useFormState<UserInput>(initial)
  const { values, set, errors } = form

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      const input = { ...values, password: values.password || undefined }
      const saved = await save.mutateAsync({ id: userId, input })
      toast.success('Pengguna disimpan')
      if (!userId) navigate(`/settings/users/${saved.id}`, { replace: true })
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
    }
  }

  function toggleRole(role: string, checked: boolean) {
    set('roles', checked ? [...values.roles, role] : values.roles.filter((r) => r !== role))
  }

  const isSelf = !!userId && me?.id === userId

  return (
    <form onSubmit={handleSubmit} className="mx-auto max-w-3xl px-4 py-6">
      <PageHeader
        crumbs={[{ label: 'Pengguna', to: '/settings/users' }]}
        title={userId ? user?.name ?? '' : 'Baru'}
        actions={
          !readOnly && (
            <Button type="submit" disabled={save.isPending}>
              {save.isPending ? 'Menyimpan…' : 'Simpan'}
            </Button>
          )
        }
      />
      <Card>
        <CardContent className="grid gap-6">
          <fieldset disabled={readOnly} className="grid gap-6">
            <FormSection>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Nama" htmlFor="name" error={errors.name} required>
                  <Input id="name" value={values.name} onChange={(e) => set('name', e.target.value)} aria-invalid={!!errors.name} />
                </Field>
                <Field label="Email" htmlFor="email" error={errors.email} required>
                  <Input id="email" type="email" value={values.email} onChange={(e) => set('email', e.target.value)} aria-invalid={!!errors.email} />
                </Field>
                <Field
                  label={userId ? 'Kata sandi baru' : 'Kata sandi'}
                  htmlFor="password"
                  error={errors.password}
                  hint={userId ? 'Kosongkan bila tidak diubah. Minimal 8 karakter.' : 'Minimal 8 karakter.'}
                  required={!userId}
                >
                  <Input
                    id="password"
                    type="password"
                    autoComplete="new-password"
                    value={values.password}
                    onChange={(e) => set('password', e.target.value)}
                    aria-invalid={!!errors.password}
                  />
                </Field>
                <Field label="Status" error={errors.is_active} hint={isSelf ? 'Akun sendiri tidak bisa dinonaktifkan.' : undefined}>
                  <div className="flex h-9 items-center gap-2">
                    <Switch
                      id="is_active"
                      checked={values.is_active}
                      onCheckedChange={(v) => set('is_active', v)}
                      disabled={isSelf}
                    />
                    <label htmlFor="is_active" className="text-sm">
                      {values.is_active ? 'Aktif' : 'Nonaktif'}
                    </label>
                  </div>
                </Field>
              </div>
            </FormSection>

            <FormSection title="Role">
              {errors.roles && <p className="text-destructive text-xs">{errors.roles}</p>}
              <div className="grid gap-2 sm:grid-cols-3">
                {(readOnly ? values.roles : roleOptions).map((role) => (
                  <CheckField key={role} id={`role-${role}`} label={role}>
                    <Checkbox
                      id={`role-${role}`}
                      checked={values.roles.includes(role)}
                      onCheckedChange={(checked) => toggleRole(role, checked === true)}
                    />
                  </CheckField>
                ))}
              </div>
            </FormSection>
          </fieldset>
        </CardContent>
      </Card>
    </form>
  )
}
