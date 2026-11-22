import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { api } from '@/lib/api'
import { cleanParams, toPage, type ListParams, type Page } from '@/lib/pagination'
import type { Company, MenuGroup, ModuleInfo, PermissionGroup, Role, Sequence, User } from './types'

export const baseKeys = {
  me: ['auth', 'me'] as const,
  menu: ['menu'] as const,
  modules: ['modules'] as const,
}

export async function login(input: { email: string; password: string }) {
  const { data } = await api.post<{ token: string; user: User }>('/auth/login', {
    ...input,
    device_name: 'web',
  })
  return data
}

export async function fetchMe() {
  const { data } = await api.get<{ data: User }>('/auth/me')
  return data.data
}

export async function logout() {
  await api.post('/auth/logout')
}

export function useMenu() {
  return useQuery({
    queryKey: baseKeys.menu,
    queryFn: async () => (await api.get<{ data: MenuGroup[] }>('/menu')).data.data,
  })
}

export function useModules() {
  return useQuery({
    queryKey: baseKeys.modules,
    queryFn: async () => (await api.get<{ data: ModuleInfo[] }>('/modules')).data.data,
  })
}

export function useToggleModule() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ name, enable }: { name: string; enable: boolean }) => {
      const { data } = await api.post<{ message: string; activated?: string[] }>(
        `/modules/${name}/${enable ? 'enable' : 'disable'}`,
      )
      return data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: baseKeys.modules })
      queryClient.invalidateQueries({ queryKey: baseKeys.menu })
    },
  })
}

// ---------------------------------------------------------------------------
// Pengaturan: pengguna, role, perusahaan, penomoran
// ---------------------------------------------------------------------------

export const settingsKeys = {
  users: (params?: ListParams) => ['users', params] as const,
  user: (id: number) => ['users', 'detail', id] as const,
  roles: (params?: ListParams) => ['roles', params] as const,
  role: (id: number) => ['roles', 'detail', id] as const,
  roleOptions: ['roles', 'options'] as const,
  permissions: ['permissions'] as const,
  company: ['company'] as const,
  sequences: ['sequences'] as const,
}

export type UserInput = {
  name: string
  email: string
  password?: string
  is_active: boolean
  roles: string[]
}

export function useUsers(params: ListParams) {
  return useQuery({
    queryKey: settingsKeys.users(params),
    queryFn: async () => toPage((await api.get<Page<User>>('/users', { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

export function useUser(id?: number) {
  return useQuery({
    queryKey: settingsKeys.user(id ?? 0),
    queryFn: async () => (await api.get<{ data: User }>(`/users/${id}`)).data.data,
    enabled: !!id,
  })
}

export function useSaveUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: UserInput }) => {
      const { data } = id
        ? await api.put<{ data: User }>(`/users/${id}`, input)
        : await api.post<{ data: User }>('/users', input)
      return data.data
    },
    onSuccess: (user) => {
      queryClient.setQueryData(settingsKeys.user(user.id), user)
      queryClient.invalidateQueries({ queryKey: ['users'] })
    },
  })
}

export function useRoleOptions({ enabled = true } = {}) {
  return useQuery({
    queryKey: settingsKeys.roleOptions,
    queryFn: async () => (await api.get<{ data: string[] }>('/role-options')).data.data,
    enabled,
  })
}

export function useRoles(params: ListParams) {
  return useQuery({
    queryKey: settingsKeys.roles(params),
    queryFn: async () => toPage((await api.get<Page<Role>>('/roles', { params: cleanParams(params) })).data),
    placeholderData: keepPreviousData,
  })
}

export function useRole(id?: number) {
  return useQuery({
    queryKey: settingsKeys.role(id ?? 0),
    queryFn: async () => (await api.get<{ data: Role }>(`/roles/${id}`)).data.data,
    enabled: !!id,
  })
}

export function usePermissionGroups() {
  return useQuery({
    queryKey: settingsKeys.permissions,
    queryFn: async () => (await api.get<{ data: PermissionGroup[] }>('/permissions')).data.data,
  })
}

export function useSaveRole() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: { name: string; permissions: string[] } }) => {
      const { data } = id
        ? await api.put<{ data: Role }>(`/roles/${id}`, input)
        : await api.post<{ data: Role }>('/roles', input)
      return data.data
    },
    onSuccess: (role) => {
      queryClient.setQueryData(settingsKeys.role(role.id), role)
      queryClient.invalidateQueries({ queryKey: ['roles'] })
      queryClient.invalidateQueries({ queryKey: baseKeys.me })
      queryClient.invalidateQueries({ queryKey: baseKeys.menu })
    },
  })
}

export function useDeleteRole() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => api.delete(`/roles/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['roles'] }),
  })
}

export function useCompany() {
  return useQuery({
    queryKey: settingsKeys.company,
    queryFn: async () => (await api.get<{ data: Company }>('/company')).data.data,
  })
}

export function useSaveCompany() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (input: Partial<Company>) => (await api.put<{ data: Company }>('/company', input)).data.data,
    onSuccess: (company) => queryClient.setQueryData(settingsKeys.company, company),
  })
}

export function useSequences() {
  return useQuery({
    queryKey: settingsKeys.sequences,
    queryFn: async () => (await api.get<{ data: Sequence[] }>('/sequences')).data.data,
  })
}

export function useSaveSequence() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input }: { id: number; input: Omit<Sequence, 'id' | 'code' | 'module' | 'module_label' | 'preview'> }) =>
      (await api.put<{ data: Sequence }>(`/sequences/${id}`, input)).data.data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: settingsKeys.sequences }),
  })
}
