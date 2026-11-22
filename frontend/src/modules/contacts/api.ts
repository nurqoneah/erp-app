import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import type { PickerOption } from '@/components/form/RecordPicker'
import { api } from '@/lib/api'
import { cleanParams, toPage, type ListParams, type Page } from '@/lib/pagination'
import type { Partner, PartnerInput } from './types'

const BASE = '/contacts/partners'

export function usePartners(params: ListParams) {
  return useQuery({
    queryKey: ['partners', params],
    queryFn: async () => toPage((await api.get<Page<Partner>>(BASE, { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

export function usePartner(id?: number) {
  return useQuery({
    queryKey: ['partners', 'detail', id],
    queryFn: async () => (await api.get<{ data: Partner }>(`${BASE}/${id}`)).data.data,
    enabled: !!id,
  })
}

export function useSavePartner() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: PartnerInput }) => {
      const { data } = id ? await api.put<{ data: Partner }>(`${BASE}/${id}`, input) : await api.post<{ data: Partner }>(BASE, input)
      return data.data
    },
    onSuccess: (partner) => {
      queryClient.setQueryData(['partners', 'detail', partner.id], partner)
      queryClient.invalidateQueries({ queryKey: ['partners'] })
      queryClient.invalidateQueries({ queryKey: ['picker'] })
    },
  })
}

export function useDeletePartner() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => api.delete(`${BASE}/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['partners'] }),
  })
}

/** Pencarian kontak untuk RecordPicker. */
export async function searchPartners(term: string, filters: ListParams = {}): Promise<PickerOption[]> {
  const { data } = await api.get<Page<Partner>>(BASE, { params: cleanParams({ search: term, per_page: 20, ...filters }) })
  return data.data.map((p) => ({ id: p.id, label: p.display_name, hint: p.city ?? undefined }))
}
