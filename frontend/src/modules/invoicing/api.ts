import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { api } from '@/lib/api'
import { cleanParams, toPage, type ListParams, type Page } from '@/lib/pagination'
import type { Invoice, InvoiceType } from './types'

const BASE = '/invoicing/invoices'

export function useInvoices(params: ListParams) {
  return useQuery({
    queryKey: ['invoices', params],
    queryFn: async () => toPage((await api.get<Page<Invoice>>(BASE, { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

export function useInvoice(id?: number) {
  return useQuery({
    queryKey: ['invoices', 'detail', id],
    queryFn: async () => (await api.get<{ data: Invoice }>(`${BASE}/${id}`)).data.data,
    enabled: !!id,
  })
}

export type InvoiceInput = {
  type?: InvoiceType
  partner_id: number | null
  invoice_date: string | null
  due_date: string | null
  vendor_reference: string | null
  notes: string | null
  lines: Record<string, unknown>[]
}

/** Faktur memengaruhi status pesanan penjualan / pembelian. */
function refresh(queryClient: ReturnType<typeof useQueryClient>, invoice?: Invoice) {
  if (invoice) queryClient.setQueryData(['invoices', 'detail', invoice.id], invoice)
  queryClient.invalidateQueries({ queryKey: ['invoices'] })
  queryClient.invalidateQueries({ queryKey: ['sales-orders'] })
  queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
}

export function useSaveInvoice() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: InvoiceInput }) => {
      const { data } = id ? await api.put<{ data: Invoice }>(`${BASE}/${id}`, input) : await api.post<{ data: Invoice }>(BASE, input)
      return data.data
    },
    onSuccess: (invoice) => refresh(queryClient, invoice),
  })
}

export function useInvoiceAction() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, action }: { id: number; action: 'post' | 'cancel' | 'reset-to-draft' }) =>
      (await api.post<{ data: Invoice }>(`${BASE}/${id}/${action}`)).data.data,
    onSuccess: (invoice) => refresh(queryClient, invoice),
  })
}

export function useDeleteInvoice() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => api.delete(`${BASE}/${id}`),
    onSuccess: () => refresh(queryClient),
  })
}

export function useRegisterPayment() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id: number; input: { amount: string; date: string; method: string; reference: string | null } }) =>
      (await api.post<{ data: Invoice }>(`${BASE}/${id}/payments`, input)).data.data,
    onSuccess: (invoice) => refresh(queryClient, invoice),
  })
}
