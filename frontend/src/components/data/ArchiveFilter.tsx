import { NativeSelect } from '@/components/ui/native-select'

/** Filter aktif / arsip / semua (param `active` di API). */
export function ArchiveFilter({ value, onChange }: { value?: string; onChange: (value: string) => void }) {
  return (
    <NativeSelect value={value ?? '1'} onChange={(e) => onChange(e.target.value)} aria-label="Status" className="w-32">
      <option value="1">Aktif</option>
      <option value="0">Diarsipkan</option>
      <option value="all">Semua</option>
    </NativeSelect>
  )
}
