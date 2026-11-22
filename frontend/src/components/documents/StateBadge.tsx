import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

import type { Tone } from './states'

const tones: Record<Tone, string> = {
  neutral: 'bg-secondary text-secondary-foreground',
  info: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  danger: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
}

export function StateBadge({ label, tone = 'neutral' }: { label: string; tone?: Tone }) {
  return <Badge className={cn('border-transparent', tones[tone])}>{label}</Badge>
}
