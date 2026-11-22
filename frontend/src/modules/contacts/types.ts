export type PartnerType = 'company' | 'person'

export type Partner = {
  id: number
  type: PartnerType
  name: string
  display_name: string
  parent_id: number | null
  parent?: { id: number; name: string } | null
  ref: string | null
  job_title: string | null
  email: string | null
  phone: string | null
  mobile: string | null
  website: string | null
  tax_id: string | null
  street: string | null
  city: string | null
  state: string | null
  zip: string | null
  country: string | null
  is_customer: boolean
  is_vendor: boolean
  notes: string | null
  is_active: boolean
  contacts?: Partner[]
}

export type PartnerInput = Omit<Partner, 'id' | 'display_name' | 'parent' | 'contacts'>
