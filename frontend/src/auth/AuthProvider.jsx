import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import AuthContext from './AuthContext'
import authService from './authService'
import { onSessionExpired } from '../services/api'

const isGuestResponse = (error) => [401, 419].includes(error.response?.status)
const connectionError = 'No fue posible verificar la sesión. Revisa la conexión e intenta de nuevo.'

export default function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [sessionError, setSessionError] = useState(null)
  const generation = useRef(0)
  const bootstrapRequest = useRef(null)

  useEffect(() => onSessionExpired(() => {
    generation.current += 1
    setUser(null)
    setSessionError(null)
  }), [])

  useEffect(() => {
    let active = true
    const requestGeneration = generation.current
    // Reuse the bootstrap request when React StrictMode replays effects.
    bootstrapRequest.current ??= authService.currentUser()
    bootstrapRequest.current
      .then((currentUser) => {
        if (active && requestGeneration === generation.current) setUser(currentUser)
      })
      .catch((error) => {
        if (active && !isGuestResponse(error)) setSessionError(connectionError)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })
    return () => { active = false }
  }, [])

  const refreshUser = useCallback(async () => {
    const requestGeneration = ++generation.current
    setIsLoading(true)
    setSessionError(null)
    try {
      const currentUser = await authService.currentUser()
      if (requestGeneration === generation.current) setUser(currentUser)
      return currentUser
    } catch (error) {
      if (requestGeneration === generation.current) {
        setUser(null)
        if (!isGuestResponse(error)) setSessionError(connectionError)
      }
      throw error
    } finally {
      setIsLoading(false)
    }
  }, [])

  const login = useCallback(async (credentials) => {
    const requestGeneration = ++generation.current
    const currentUser = await authService.login(credentials)
    if (requestGeneration === generation.current) {
      setUser(currentUser)
      setSessionError(null)
    }
    return currentUser
  }, [])

  const logout = useCallback(async () => {
    generation.current += 1
    try {
      await authService.logout()
      setUser(null)
    } catch (error) {
      if (isGuestResponse(error)) {
        setUser(null)
        return
      }
      // A network failure does not prove that the server destroyed the session.
      throw error
    }
  }, [])

  const value = useMemo(() => {
    const roles = user?.roles ?? []
    const permissions = user?.permissions ?? []
    return {
      user,
      roles,
      permissions,
      isAuthenticated: Boolean(user),
      isLoading,
      sessionError,
      login,
      logout,
      refreshUser,
      hasRole: (role) => roles.includes(role),
      hasAnyRole: (candidates) => candidates.some((role) => roles.includes(role)),
      can: (permission) => permissions.includes(permission),
      canAny: (candidates) => candidates.some((permission) => permissions.includes(permission)),
    }
  }, [user, isLoading, sessionError, login, logout, refreshUser])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
