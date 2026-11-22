import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { api } from '@/lib/api'
import { cleanParams, toPage, type ListParams, type Page } from '@/lib/pagination'
import type { AdjustmentRow, Location, Picking, StockRow, Warehouse } from './types'

const BASE = '/inventory'

export function useStock(params: ListParams) {
  return useQuery({
    queryKey: ['inventory', 'stock', params],
    queryFn: async () => toPage((await api.get<Page<StockRow>>(`${BASE}/stock`, { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

/** Stok tersedia per produk (semua lokasi internal). */
export function useOnHand(productIds: number[]) {
  return useQuery({
    queryKey: ['inventory', 'on-hand', productIds],
    queryFn: async () =>
      (await api.get<{ data: Record<string, number> }>(`${BASE}/stock/summary`, { params: { product_ids: productIds } })).data.data,
    enabled: productIds.length > 0,
  })
}

export function useLocations(usage?: string) {
  return useQuery({
    queryKey: ['inventory', 'locations', usage],
    queryFn: async () => (await api.get<{ data: Location[] }>(`${BASE}/locations`, { params: usage ? { usage } : {} })).data.data,
  })
}

export function usePickings(params: ListParams) {
  return useQuery({
    queryKey: ['inventory', 'pickings', params],
    queryFn: async () => toPage((await api.get<Page<Picking>>(`${BASE}/transfers`, { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

export function usePicking(id?: number) {
  return useQuery({
    queryKey: ['inventory', 'pickings', 'detail', id],
    queryFn: async () => (await api.get<{ data: Picking }>(`${BASE}/transfers/${id}`)).data.data,
    enabled: !!id,
  })
}

export type PickingInput = {
  type?: string
  partner_id: number | null
  scheduled_date: string
  source_location_id: number | null
  dest_location_id: number | null
  notes: string | null
  lines: { product_id: number | null; quantity: string }[]
}

/** Setelah stok berubah, data terkait di modul lain (pesanan, produk) juga dimuat ulang. */
function invalidateStock(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: ['inventory'] })
  queryClient.invalidateQueries({ queryKey: ['sales-orders'] })
  queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
}

export function useSavePicking() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: PickingInput }) => {
      const { data } = id
        ? await api.put<{ data: Picking }>(`${BASE}/transfers/${id}`, input)
        : await api.post<{ data: Picking }>(`${BASE}/transfers`, input)
      return data.data
    },
    onSuccess: (picking) => {
      queryClient.setQueryData(['inventory', 'pickings', 'detail', picking.id], picking)
      invalidateStock(queryClient)
    },
  })
}

export function usePickingAction() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, action }: { id: number; action: 'validate' | 'cancel' }) =>
      (await api.post<{ data: Picking }>(`${BASE}/transfers/${id}/${action}`)).data.data,
    onSuccess: (picking) => {
      queryClient.setQueryData(['inventory', 'pickings', 'detail', picking.id], picking)
      invalidateStock(queryClient)
    },
  })
}

export function useAdjustments(params: ListParams) {
  return useQuery({
    queryKey: ['inventory', 'adjustments', params],
    queryFn: async () => toPage((await api.get<Page<AdjustmentRow>>(`${BASE}/adjustments`, { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

export function useAdjust() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (input: { location_id: number | null; reason: string | null; lines: { product_id: number | null; quantity: string }[] }) =>
      (await api.post<{ message: string; changed: number }>(`${BASE}/adjustments`, input)).data,
    onSuccess: () => invalidateStock(queryClient),
  })
}

export function useWarehouses() {
  return useQuery({
    queryKey: ['inventory', 'warehouses'],
    queryFn: async () => (await api.get<{ data: Warehouse[] }>(`${BASE}/warehouses`)).data.data,
  })
}

export function useSaveWarehouse() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: { name: string; code?: string; is_active?: boolean } }) =>
      id ? api.put(`${BASE}/warehouses/${id}`, input) : api.post(`${BASE}/warehouses`, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['inventory'] }),
  })
}

export function useCreateLocation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (input: { name: string; parent_id: number }) => api.post(`${BASE}/locations`, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['inventory'] }),
  })
}
