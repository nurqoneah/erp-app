import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { api } from '@/lib/api'
import { cleanParams, toPage, type ListParams, type Page } from '@/lib/pagination'
import type { Order, OrderKind } from './types'

export function useOrders(kind: OrderKind, params: ListParams) {
  return useQuery({
    queryKey: [kind.queryKey, params],
    queryFn: async () => toPage((await api.get<Page<Order>>(kind.apiBase, { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

export function useOrder(kind: OrderKind, id?: number) {
  return useQuery({
    queryKey: [kind.queryKey, 'detail', id],
    queryFn: async () => (await api.get<{ data: Order }>(`${kind.apiBase}/${id}`)).data.data,
    enabled: !!id,
  })
}

export type OrderInput = {
  partner_id: number | null
  order_date: string
  notes: string | null
  lines: Record<string, unknown>[]
  [extraDate: string]: unknown
}

export function useSaveOrder(kind: OrderKind) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: OrderInput }) => {
      const { data } = id
        ? await api.put<{ data: Order }>(`${kind.apiBase}/${id}`, input)
        : await api.post<{ data: Order }>(kind.apiBase, input)
      return data.data
    },
    onSuccess: (order) => {
      queryClient.setQueryData([kind.queryKey, 'detail', order.id], order)
      queryClient.invalidateQueries({ queryKey: [kind.queryKey] })
    },
  })
}

export function useOrderAction(kind: OrderKind) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, action }: { id: number; action: 'confirm' | 'cancel' | 'set-to-draft' }) =>
      (await api.post<{ data: Order }>(`${kind.apiBase}/${id}/${action}`)).data.data,
    onSuccess: (order) => {
      queryClient.setQueryData([kind.queryKey, 'detail', order.id], order)
      queryClient.invalidateQueries({ queryKey: [kind.queryKey] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
    },
  })
}

export function useCreateOrderInvoice(kind: OrderKind) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) =>
      (await api.post<Record<string, number>>(`${kind.apiBase}/${id}/${kind.invoiceAction}`)).data[kind.invoiceIdField],
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [kind.queryKey] })
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
    },
  })
}

export function useDeleteOrder(kind: OrderKind) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => api.delete(`${kind.apiBase}/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: [kind.queryKey] }),
  })
}
