import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ArrowUpCircle, Download, Search, Trash2 } from 'lucide-react'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { ModuleIcon } from '@/components/ModuleIcon'
import { CheckField } from '@/components/form/Field'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { errorMessage } from '@/lib/api'
import { moduleTone } from '@/lib/icons'
import { cn } from '@/lib/utils'
import { fetchUninstallPlan, useModuleAction, useModules } from '@/modules/base/api'
import type { ModuleInfo } from '@/modules/base/types'

type Filter = 'all' | 'installed' | 'uninstalled'

function UninstallDialog({ module, onClose }: { module: ModuleInfo; onClose: () => void }) {
  const action = useModuleAction()
  const [agreed, setAgreed] = useState(false)
  const { data: plan, error: planError } = useQuery({
    queryKey: ['modules', 'uninstall-plan', module.name],
    queryFn: () => fetchUninstallPlan(module.name),
    gcTime: 0,
    retry: false,
  })
  const error = planError ? errorMessage(planError) : null

  async function confirm() {
    try {
      const result = await action.mutateAsync({ name: module.name, action: 'uninstall' })
      toast.success(result.message)
      onClose()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const others = plan?.filter((m) => m.name !== module.name) ?? []

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Uninstall {module.label}?</DialogTitle>
          <DialogDescription>
            Semua tabel dan data milik modul ini akan <strong className="text-destructive">dihapus permanen</strong>, termasuk izin
            di role dan penomoran dokumennya. Tindakan ini tidak bisa dibatalkan.
          </DialogDescription>
        </DialogHeader>

        {error && <p className="text-destructive text-sm">{error}</p>}
        {!plan && !error && <Skeleton className="h-10" />}
        {others.length > 0 && (
          <div className="bg-destructive/5 border-destructive/30 rounded-md border p-3 text-sm">
            <p className="font-medium">Modul berikut bergantung pada {module.label} dan ikut di-uninstall:</p>
            <ul className="mt-1 list-disc pl-5">
              {others.map((m) => (
                <li key={m.name}>{m.label}</li>
              ))}
            </ul>
          </div>
        )}

        <CheckField id="uninstall-agree" label="Saya mengerti datanya akan hilang permanen.">
          <Checkbox id="uninstall-agree" checked={agreed} onCheckedChange={(v) => setAgreed(v === true)} />
        </CheckField>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={action.isPending}>
            Batal
          </Button>
          <Button variant="destructive" onClick={confirm} disabled={!agreed || !plan || action.isPending}>
            {action.isPending ? 'Menghapus…' : 'Uninstall'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function ModulesPage() {
  const { data: modules, isLoading } = useModules()
  const action = useModuleAction()
  const { can } = useAuth()
  const canManage = can('base.module.manage')
  const [filter, setFilter] = useState<Filter>('all')
  const [search, setSearch] = useState('')
  const [uninstalling, setUninstalling] = useState<ModuleInfo | null>(null)
  const labels = useMemo(() => new Map(modules?.map((m) => [m.name, m.label])), [modules])

  const visible = (modules ?? []).filter((m) => {
    if (filter === 'installed' && !m.installed) return false
    if (filter === 'uninstalled' && m.installed) return false
    const term = search.trim().toLowerCase()
    return !term || `${m.label} ${m.name} ${m.description} ${m.category}`.toLowerCase().includes(term)
  })

  function run(module: ModuleInfo, kind: 'install' | 'upgrade') {
    action.mutate(
      { name: module.name, action: kind },
      {
        onSuccess: (data) => {
          const extra = data.installed?.filter((n) => n !== module.name) ?? []
          toast.success(data.message + (extra.length ? ` Ikut terpasang: ${extra.map((n) => labels.get(n) ?? n).join(', ')}.` : ''))
        },
        onError: (error) => toast.error(errorMessage(error)),
      },
    )
  }

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      <div className="mb-5 flex flex-wrap items-end gap-3">
        <div className="min-w-0 flex-1">
          <h1 className="text-2xl font-semibold tracking-tight">Apps</h1>
          <p className="text-muted-foreground text-sm">Install modul yang dibutuhkan. Dependensi akan ikut terpasang otomatis.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <div className="bg-muted inline-flex rounded-lg p-[3px]" role="tablist" aria-label="Filter modul">
            {(
              [
                ['all', 'Semua'],
                ['installed', 'Terpasang'],
                ['uninstalled', 'Belum terpasang'],
              ] as [Filter, string][]
            ).map(([value, label]) => (
              <button
                key={value}
                role="tab"
                aria-selected={filter === value}
                onClick={() => setFilter(value)}
                className={cn(
                  'rounded-md px-3 py-1 text-sm font-medium',
                  filter === value ? 'bg-background shadow-sm' : 'text-muted-foreground',
                )}
              >
                {label}
              </button>
            ))}
          </div>
          <div className="relative w-full sm:w-56">
            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari app…" className="pl-8" aria-label="Cari app" />
          </div>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {isLoading && Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-44 rounded-xl" />)}

        {visible.map((module) => {
          const pending = action.isPending && action.variables?.name === module.name
          return (
            <Card key={module.name} className="gap-4">
              <CardHeader className="grid-cols-[auto_1fr] items-center gap-x-3">
                <span className={cn('row-span-2 flex size-11 items-center justify-center rounded-xl text-white', moduleTone(module.icon))}>
                  <ModuleIcon icon={module.icon} className="size-5" />
                </span>
                <CardTitle>{module.label}</CardTitle>
                <CardDescription className="text-xs">
                  {module.name} · v{module.version}
                  {module.state === 'to_upgrade' && module.installed_version && ` (terpasang v${module.installed_version})`}
                </CardDescription>
              </CardHeader>
              <CardContent className="flex flex-1 flex-col gap-3">
                <p className="text-muted-foreground text-sm">{module.description}</p>
                <div className="flex flex-wrap gap-1.5">
                  {module.required && <Badge variant="secondary">Wajib</Badge>}
                  {module.state === 'installed' && !module.required && <Badge variant="success">Terpasang</Badge>}
                  {module.state === 'to_upgrade' && <Badge className="bg-amber-100 text-amber-800">Perlu upgrade</Badge>}
                  {module.category && <Badge variant="outline">{module.category}</Badge>}
                  {module.depends.map((dep) => (
                    <Badge key={dep} variant="outline">
                      Butuh {labels.get(dep) ?? dep}
                    </Badge>
                  ))}
                </div>
                {canManage && !module.required && (
                  <div className="mt-auto flex flex-wrap gap-2 pt-1">
                    {!module.installed && (
                      <Button size="sm" onClick={() => run(module, 'install')} disabled={action.isPending}>
                        <Download /> {pending ? 'Memasang…' : 'Install'}
                      </Button>
                    )}
                    {module.state === 'to_upgrade' && (
                      <Button size="sm" onClick={() => run(module, 'upgrade')} disabled={action.isPending}>
                        <ArrowUpCircle /> Upgrade
                      </Button>
                    )}
                    {module.installed && (
                      <Button size="sm" variant="outline" onClick={() => setUninstalling(module)} disabled={action.isPending}>
                        <Trash2 /> Uninstall
                      </Button>
                    )}
                  </div>
                )}
                {canManage && module.required && module.state === 'to_upgrade' && (
                  <Button size="sm" className="mt-auto w-fit" onClick={() => run(module, 'upgrade')} disabled={action.isPending}>
                    <ArrowUpCircle /> Upgrade
                  </Button>
                )}
              </CardContent>
            </Card>
          )
        })}

        {!isLoading && visible.length === 0 && <p className="text-muted-foreground col-span-full py-12 text-center">Tidak ada app.</p>}
      </div>

      {uninstalling && <UninstallDialog key={uninstalling.name} module={uninstalling} onClose={() => setUninstalling(null)} />}
    </div>
  )
}
