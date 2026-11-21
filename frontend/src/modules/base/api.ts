import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { api } from '@/lib/api'
import type { MenuGroup, ModuleInfo, User } from './types'

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
