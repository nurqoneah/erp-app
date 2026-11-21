import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { errorMessage } from '@/lib/api'
import { ModuleIcon } from '@/components/ModuleIcon'
import { moduleTone } from '@/lib/icons'
import { cn } from '@/lib/utils'
import { useModules, useToggleModule } from '@/modules/base/api'
import type { ModuleInfo } from '@/modules/base/types'

export function ModulesPage() {
  const { data: modules, isLoading } = useModules()
  const toggle = useToggleModule()
  const { can } = useAuth()
  const canManage = can('base.module.manage')
  const labels = new Map(modules?.map((m) => [m.name, m.label]))

  function handleToggle(module: ModuleInfo, enable: boolean) {
    toggle.mutate(
      { name: module.name, enable },
      {
        onSuccess: (data) => {
          const extra = data.activated?.filter((n) => n !== module.name) ?? []
          toast.success(
            enable
              ? `${module.label} diaktifkan` +
                  (extra.length ? ` (beserta ${extra.map((n) => labels.get(n) ?? n).join(', ')})` : '')
              : `${module.label} dinonaktifkan`,
          )
        },
        onError: (error) => toast.error(errorMessage(error)),
      },
    )
  }

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold tracking-tight">Modul</h1>
        <p className="text-muted-foreground text-sm">
          Aktifkan modul yang dibutuhkan. Dependensi akan ikut diaktifkan otomatis.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {isLoading && Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-40 rounded-xl" />)}

        {modules?.map((module) => {
          const pending = toggle.isPending && toggle.variables?.name === module.name
          return (
            <Card key={module.name} className="gap-4">
              <CardHeader className="grid-cols-[auto_1fr_auto] items-center gap-3">
                <span
                  className={cn(
                    'row-span-2 flex size-10 items-center justify-center rounded-xl text-white',
                    moduleTone(module.icon),
                  )}
                >
                  <ModuleIcon icon={module.icon} className="size-5" />
                </span>
                <CardTitle>{module.label}</CardTitle>
                <Switch
                  className="row-span-2"
                  checked={module.enabled}
                  disabled={!canManage || module.required || pending}
                  onCheckedChange={(checked) => handleToggle(module, checked)}
                  aria-label={`${module.enabled ? 'Nonaktifkan' : 'Aktifkan'} ${module.label}`}
                />
                <CardDescription className="text-xs">v{module.version}</CardDescription>
              </CardHeader>
              <CardContent className="flex flex-1 flex-col gap-3">
                <p className="text-muted-foreground text-sm">{module.description}</p>
                <div className="mt-auto flex flex-wrap gap-1.5">
                  {module.required && <Badge variant="secondary">Wajib</Badge>}
                  {module.enabled && !module.required && <Badge variant="success">Aktif</Badge>}
                  {module.depends.map((dep) => (
                    <Badge key={dep} variant="outline">
                      Butuh {labels.get(dep) ?? dep}
                    </Badge>
                  ))}
                </div>
              </CardContent>
            </Card>
          )
        })}
      </div>
    </div>
  )
}
