import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { api } from '@/lib/api'
import { cleanParams, toPage, type ListParams, type Page } from '@/lib/pagination'
import type { PaymentMethod, PosConfig, PosOrder, PosProduct, PosSession, SessionSummary } from './types'

const BASE = '/pos'

export function usePosConfigs(all = false) {
  return useQuery({
    queryKey: ['pos', 'configs', all],
    queryFn: async () => (await api.get<{ data: PosConfig[] }>(`${BASE}/configs`, { params: all ? { all: 1 } : {} })).data.data,
  })
}

export function useSavePosConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: Record<string, unknown> }) =>
      id ? api.put(`${BASE}/configs/${id}`, input) : api.post(`${BASE}/configs`, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['pos'] }),
  })
}

export function useOpenSession() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (configId: number) => (await api.post<{ data: PosSession }>(`${BASE}/configs/${configId}/open`)).data.data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['pos'] }),
  })
}

export type SessionDetail = { data: PosSession; summary: SessionSummary; suggested_opening_cash: number | null }

export function useSession(id?: number) {
  return useQuery({
    queryKey: ['pos', 'sessions', 'detail', id],
    queryFn: async () => (await api.get<SessionDetail>(`${BASE}/sessions/${id}`)).data,
    enabled: !!id,
  })
}

export async function fetchSession(id: number) {
  return (await api.get<SessionDetail>(`${BASE}/sessions/${id}`)).data
}

export function useSessions(params: ListParams) {
  return useQuery({
    queryKey: ['pos', 'sessions', params],
    queryFn: async () => toPage((await api.get<Page<PosSession>>(`${BASE}/sessions`, { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

export function useOpeningControl() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, cash, notes }: { id: number; cash: string; notes: string | null }) =>
      (await api.post<SessionDetail>(`${BASE}/sessions/${id}/opening`, { cash, notes })).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['pos'] }),
  })
}

export type RegisterData = {
  session: PosSession
  config: { id: number; name: string; receipt_header: string | null; receipt_footer: string | null; cash_control: boolean }
  payment_methods: PaymentMethod[]
  products: PosProduct[]
}

export function useRegisterData(sessionId: number) {
  return useQuery({
    queryKey: ['pos', 'register', sessionId],
    queryFn: async () => (await api.get<RegisterData>(`${BASE}/sessions/${sessionId}/load`)).data,
    staleTime: 5 * 60_000,
  })
}

export function useCashMove() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ sessionId, input }: { sessionId: number; input: { type: 'in' | 'out'; amount: string; reason: string } }) =>
      api.post(`${BASE}/sessions/${sessionId}/cash-moves`, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['pos', 'sessions'] }),
  })
}

export function useCloseSession() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ sessionId, counted_cash, notes }: { sessionId: number; counted_cash: string | null; notes: string | null }) =>
      (await api.post<SessionDetail>(`${BASE}/sessions/${sessionId}/close`, { counted_cash, notes })).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['pos'] }),
  })
}

export type OrderPayload = {
  partner_id: number | null
  to_invoice: boolean
  notes: string | null
  lines: { product_id: number; quantity: number; unit_price: number; discount: number; refunded_line_id?: number | null }[]
  payments: { payment_method_id: number; amount: number }[]
}

export function useSubmitOrder(sessionId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: OrderPayload) => (await api.post<{ data: PosOrder }>(`${BASE}/sessions/${sessionId}/orders`, payload)).data.data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['pos', 'orders'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
    },
  })
}

export function usePosOrders(params: ListParams, enabled = true) {
  return useQuery({
    queryKey: ['pos', 'orders', params],
    queryFn: async () => toPage((await api.get<Page<PosOrder>>(`${BASE}/orders`, { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
    enabled,
  })
}

export function usePosOrder(id?: number) {
  return useQuery({
    queryKey: ['pos', 'orders', 'detail', id],
    queryFn: async () => (await api.get<{ data: PosOrder }>(`${BASE}/orders/${id}`)).data.data,
    enabled: !!id,
  })
}

export function usePaymentMethods() {
  return useQuery({
    queryKey: ['pos', 'payment-methods'],
    queryFn: async () => (await api.get<{ data: PaymentMethod[] }>(`${BASE}/payment-methods`)).data.data,
  })
}

export function useSavePaymentMethod() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: Omit<PaymentMethod, 'id'> }) =>
      id ? api.put(`${BASE}/payment-methods/${id}`, input) : api.post(`${BASE}/payment-methods`, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['pos'] }),
  })
}
