import { type FormEvent } from 'react'
import { Trash2 } from 'lucide-react'
import { useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'

import { ConfirmDialog } from '@/components/data/ConfirmDialog'
import { NotFound } from '@/components/data/NotFound'
import { PageHeader } from '@/components/data/PageHeader'
import { CheckField, Field } from '@/components/form/Field'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { useFormState } from '@/hooks/useFormState'
import { errorMessage } from '@/lib/api'
import { useDeleteRole, usePermissionGroups, useRole, useSaveRole } from '@/modules/base/api'
import type { Role } from '@/modules/base/types'

export function RoleFormPage() {
  const { id } = useParams()
  const roleId = id ? Number(id) : undefined
  const { data: role, isLoading, isError } = useRole(roleId)

  if (roleId && isLoading) return <Skeleton className="m-6 h-64" />
  if (roleId && (isError || !role)) return <NotFound backTo="/settings/roles" />

  return <RoleForm role={role} />
}

function RoleForm({ role }: { role?: Role }) {
  const roleId = role?.id
  const navigate = useNavigate()
  const { data: groups = [] } = usePermissionGroups()
  const save = useSaveRole()
  const remove = useDeleteRole()
  const form = useFormState({ name: role?.name ?? '', permissions: [...(role?.permissions ?? [])] })
  const { values, set, errors } = form
  const locked = !!role?.is_system

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    try {
      const saved = await save.mutateAsync({ id: roleId, input: values })
      toast.success('Role disimpan')
      if (!roleId) navigate(`/settings/roles/${saved.id}`, { replace: true })
    } catch (error) {
      if (!form.applyServerErrors(error)) toast.error(errorMessage(error))
    }
  }

  function setMany(names: string[], checked: boolean) {
    const current = new Set(values.permissions)
    names.forEach((n) => (checked ? current.add(n) : current.delete(n)))
    set('permissions', [...current])
  }

  return (
    <form onSubmit={handleSubmit} className="mx-auto max-w-4xl px-4 py-6">
      <PageHeader
        crumbs={[{ label: 'Role', to: '/settings/roles' }]}
        title={roleId ? role?.name ?? '' : 'Baru'}
        actions={
          !locked && (
            <>
              {roleId && (
                <ConfirmDialog
                  title="Hapus role?"
                  description={`Role "${role?.name}" akan dicabut dari ${role?.users_count ?? 0} pengguna.`}
                  confirmLabel="Hapus"
                  destructive
                  onConfirm={async () => {
                    try {
                      await remove.mutateAsync(roleId)
                      toast.success('Role dihapus')
                      navigate('/settings/roles')
                    } catch (error) {
                      toast.error(errorMessage(error))
                    }
                  }}
                  trigger={(open) => (
                    <Button type="button" variant="outline" onClick={open}>
                      <Trash2 /> Hapus
                    </Button>
                  )}
                />
              )}
              <Button type="submit" disabled={save.isPending}>
                {save.isPending ? 'Menyimpan…' : 'Simpan'}
              </Button>
            </>
          )
        }
      />

      {locked && (
        <p className="bg-accent text-accent-foreground mb-4 rounded-md px-3 py-2 text-sm">
          Role super-admin otomatis punya semua izin dan tidak bisa diubah.
        </p>
      )}

      <fieldset disabled={locked} className="grid gap-4">
        <Card>
          <CardContent>
            <Field label="Nama role" htmlFor="name" error={errors.name} required className="max-w-sm">
              <Input id="name" value={values.name} onChange={(e) => set('name', e.target.value)} aria-invalid={!!errors.name} />
            </Field>
          </CardContent>
        </Card>

        {!locked &&
          groups.map((group) => {
            const names = group.permissions.map((p) => p.name)
            const selected = names.filter((n) => values.permissions.includes(n)).length
            const all = selected === names.length
            return (
              <Card key={group.module} className="gap-4">
                <CardHeader className="flex flex-row items-center justify-between">
                  <CardTitle>{group.label}</CardTitle>
                  <CheckField id={`all-${group.module}`} label="Semua">
                    <Checkbox
                      id={`all-${group.module}`}
                      checked={all ? true : selected > 0 ? 'indeterminate' : false}
                      onCheckedChange={() => setMany(names, !all)}
                    />
                  </CheckField>
                </CardHeader>
                <CardContent className="grid gap-2 sm:grid-cols-2">
                  {group.permissions.map((perm) => (
                    <CheckField key={perm.name} id={perm.name} label={perm.label}>
                      <Checkbox
                        id={perm.name}
                        checked={values.permissions.includes(perm.name)}
                        onCheckedChange={(checked) => setMany([perm.name], checked === true)}
                      />
                    </CheckField>
                  ))}
                </CardContent>
              </Card>
            )
          })}
        {errors.permissions && <p className="text-destructive text-sm">{errors.permissions}</p>}
      </fieldset>
    </form>
  )
}
