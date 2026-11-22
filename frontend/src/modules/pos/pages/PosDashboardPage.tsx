import { useState } from 'react'
import { Monitor } from 'lucide-react'
import { useNavigate } from 'react-router'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth'
import { PageHeader } from '@/components/data/PageHeader'
import { StateBadge } from '@/components/documents/StateBadge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { errorMessage } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'
import { useOpenSession, usePosConfigs } from '../api'
import { OpeningDialog } from '../register/dialogs'
import { sessionStateLabels, type PosConfig } from '../types'

/** Dasbor POS: satu kartu per Point of Sale, seperti dasbor POS di Odoo. */
export function PosDashboardPage() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const { data: configs, isLoading } = usePosConfigs()
  const open = useOpenSession()
  const [opening, setOpening] = useState<{ sessionId: number; config: PosConfig } | null>(null)

  async function start(config: PosConfig) {
    try {
      const session = await open.mutateAsync(config.id)
      if (session.state === 'opening_control') setOpening({ sessionId: session.id, config })
      else navigate(`/pos/ui/${session.id}`)
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <PageHeader title="Point of Sale" />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {isLoading && <Skeleton className="h-44 rounded-xl" />}
        {configs?.map((config) => {
          const session = config.current_session
          return (
            <Card key={config.id} className="gap-4">
              <CardHeader className="flex flex-row items-center gap-3">
                <span className="flex size-10 items-center justify-center rounded-xl bg-rose-500 text-white">
                  <Monitor className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                  <CardTitle className="truncate">{config.name}</CardTitle>
                  <div className="text-muted-foreground text-xs">{config.warehouse.name}</div>
                </div>
                {session ? (
                  <StateBadge label={sessionStateLabels[session.state]} tone={session.state === 'opened' ? 'success' : 'info'} />
                ) : (
                  <StateBadge label="Ditutup" />
                )}
              </CardHeader>
              <CardContent className="grid gap-3 text-sm">
                {session?.state === 'opened' ? (
                  <p className="text-muted-foreground">
                    {session.name} dibuka oleh {session.user}
                  </p>
                ) : config.last_closing ? (
                  <p className="text-muted-foreground">
                    Terakhir ditutup {formatDate(config.last_closing.closed_at)} · kas {formatMoney(config.last_closing.cash)}
                  </p>
                ) : (
                  <p className="text-muted-foreground">Belum pernah dibuka.</p>
                )}
                {can('pos.session.use') && (
                  <Button onClick={() => start(config)} disabled={open.isPending}>
                    {session?.state === 'opened' ? 'Lanjutkan penjualan' : session ? 'Kontrol pembukaan' : 'Buka kasir'}
                  </Button>
                )}
              </CardContent>
            </Card>
          )
        })}
        {configs && configs.length === 0 && <p className="text-muted-foreground">Belum ada Point of Sale. Buat di menu Point of Sale.</p>}
      </div>
      {opening && (
        <OpeningDialog
          sessionId={opening.sessionId}
          cashControl={opening.config.cash_control}
          onClose={() => setOpening(null)}
          onOpened={() => navigate(`/pos/ui/${opening.sessionId}`)}
        />
      )}
    </div>
  )
}
