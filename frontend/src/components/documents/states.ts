export type Tone = 'neutral' | 'info' | 'success' | 'warning' | 'danger'

/** Label & warna status yang dipakai di banyak dokumen. */
export const states = {
  picking: {
    draft: { label: 'Siap', tone: 'info' },
    done: { label: 'Selesai', tone: 'success' },
    cancelled: { label: 'Dibatalkan', tone: 'danger' },
  },
  invoice: {
    draft: { label: 'Draf', tone: 'neutral' },
    posted: { label: 'Diposting', tone: 'info' },
    cancelled: { label: 'Dibatalkan', tone: 'danger' },
  },
  payment: {
    not_paid: { label: 'Belum dibayar', tone: 'warning' },
    partial: { label: 'Sebagian', tone: 'info' },
    paid: { label: 'Lunas', tone: 'success' },
  },
} satisfies Record<string, Record<string, { label: string; tone: Tone }>>

export function stateOf(group: keyof typeof states, key: string): { label: string; tone: Tone } {
  return (states[group] as Record<string, { label: string; tone: Tone }>)[key] ?? { label: key, tone: 'neutral' as Tone }
}
