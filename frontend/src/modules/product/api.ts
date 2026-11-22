import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import type { PickerOption } from '@/components/form/RecordPicker'
import { api } from '@/lib/api'
import { cleanParams, toPage, type ListParams, type Page } from '@/lib/pagination'
import type { Product, ProductCategory, ProductInput, Uom, UomCategory } from './types'

const BASE = '/product'

export function useProducts(params: ListParams) {
  return useQuery({
    queryKey: ['products', params],
    queryFn: async () => toPage((await api.get<Page<Product>>(`${BASE}/products`, { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

export function useProduct(id?: number) {
  return useQuery({
    queryKey: ['products', 'detail', id],
    queryFn: async () => (await api.get<{ data: Product }>(`${BASE}/products/${id}`)).data.data,
    enabled: !!id,
  })
}

export function useSaveProduct() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: ProductInput }) => {
      const { data } = id
        ? await api.put<{ data: Product }>(`${BASE}/products/${id}`, input)
        : await api.post<{ data: Product }>(`${BASE}/products`, input)
      return data.data
    },
    onSuccess: (product) => {
      queryClient.setQueryData(['products', 'detail', product.id], product)
      queryClient.invalidateQueries({ queryKey: ['products'] })
      queryClient.invalidateQueries({ queryKey: ['product-categories'] })
    },
  })
}

export function useDeleteProduct() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => api.delete(`${BASE}/products/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['products'] }),
  })
}

// Kategori ----------------------------------------------------------------

export function useCategories(params: ListParams) {
  return useQuery({
    queryKey: ['product-categories', params],
    queryFn: async () =>
      toPage((await api.get<Page<ProductCategory>>(`${BASE}/categories`, { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

export async function searchCategories(term: string): Promise<PickerOption[]> {
  const { data } = await api.get<Page<ProductCategory>>(`${BASE}/categories`, { params: cleanParams({ search: term, per_page: 30 }) })
  return data.data.map((c) => ({ id: c.id, label: c.complete_name }))
}

export function useSaveCategory() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: { name: string; parent_id: number | null } }) => {
      const { data } = id
        ? await api.put<{ data: ProductCategory }>(`${BASE}/categories/${id}`, input)
        : await api.post<{ data: ProductCategory }>(`${BASE}/categories`, input)
      return data.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['product-categories'] })
      queryClient.invalidateQueries({ queryKey: ['picker'] })
    },
  })
}

export function useDeleteCategory() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => api.delete(`${BASE}/categories/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['product-categories'] }),
  })
}

// Satuan (UoM) ------------------------------------------------------------

export function useUoms(all = false) {
  return useQuery({
    queryKey: ['uoms', all],
    queryFn: async () => (await api.get<{ data: UomCategory[] }>(`${BASE}/uoms`, { params: all ? { all: 1 } : {} })).data.data,
  })
}

export function useSaveUom() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: Omit<Uom, 'id' | 'rounding'> & { rounding?: string } }) => {
      const { data } = id
        ? await api.put<{ data: Uom }>(`${BASE}/uoms/${id}`, input)
        : await api.post<{ data: Uom }>(`${BASE}/uoms`, input)
      return data.data
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['uoms'] }),
  })
}

export function useCreateUomCategory() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (input: { name: string; reference_name: string }) =>
      (await api.post<{ data: { id: number; name: string } }>(`${BASE}/uom-categories`, input)).data.data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['uoms'] }),
  })
}
