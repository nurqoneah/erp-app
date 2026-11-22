import type { FormExtensionProps } from '@/app/extensions'
import { Field, FormSection } from '@/components/form/Field'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'

const levels = [
  ['silver', 'Silver'],
  ['gold', 'Gold'],
  ['platinum', 'Platinum'],
] as const

/** Bagian "Keanggotaan" yang disisipkan ke form Kontak. */
export function MembershipFields({ values, set, errors }: FormExtensionProps) {
  const text = (key: string) => ({
    id: key,
    value: (values[key] as string | null | undefined) ?? '',
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => set(key, e.target.value || null),
    'aria-invalid': !!errors[key],
  })

  return (
    <FormSection title="Keanggotaan">
      <div className="grid gap-4 sm:grid-cols-3">
        <Field label="Nomor member" htmlFor="member_number" error={errors.member_number}>
          <Input {...text('member_number')} placeholder="mis. M-0001" />
        </Field>
        <Field label="Level" htmlFor="member_level" error={errors.member_level}>
          <NativeSelect {...text('member_level')}>
            <option value="">—</option>
            {levels.map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </NativeSelect>
        </Field>
        <Field label="Member sejak" htmlFor="member_since" error={errors.member_since}>
          <Input {...text('member_since')} type="date" />
        </Field>
      </div>
    </FormSection>
  )
}
