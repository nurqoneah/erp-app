import { createContext, useCallback, useContext, useEffect, useMemo, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'

import { setUnauthorizedHandler, tokenStorage } from '@/lib/api'
import { baseKeys, fetchMe, login as loginRequest, logout as logoutRequest } from '@/modules/base/api'
import type { User } from '@/modules/base/types'

const SUPER_ADMIN = 'super-admin'

type AuthContextValue = {
  user: User | null
  isLoading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  can: (permission: string) => boolean
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

  const { data: user, isLoading } = useQuery({
    queryKey: baseKeys.me,
    queryFn: fetchMe,
    enabled: !!tokenStorage.get(),
    retry: false,
    staleTime: 5 * 60_000,
  })

  const reset = useCallback(() => {
    tokenStorage.clear()
    queryClient.setQueryData(baseKeys.me, null)
    queryClient.removeQueries({ predicate: (q) => q.queryKey[0] !== baseKeys.me[0] })
  }, [queryClient])

  useEffect(() => {
    setUnauthorizedHandler(reset)
    return () => setUnauthorizedHandler(null)
  }, [reset])

  const login = useCallback(
    async (email: string, password: string) => {
      const result = await loginRequest({ email, password })
      tokenStorage.set(result.token)
      queryClient.setQueryData(baseKeys.me, result.user)
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    try {
      await logoutRequest()
    } finally {
      reset()
    }
  }, [reset])

  const value = useMemo<AuthContextValue>(
    () => ({
      user: user ?? null,
      isLoading: isLoading && !!tokenStorage.get(),
      login,
      logout,
      can: (permission) =>
        !!user && (user.roles.includes(SUPER_ADMIN) || user.permissions.includes(permission)),
    }),
    [user, isLoading, login, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth harus dipakai di dalam <AuthProvider>')
  return ctx
}
