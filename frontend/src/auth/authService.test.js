import { afterEach, describe, expect, it, vi } from 'vitest'
import api, { backendBaseUrl } from '../services/api'
import authService from './authService'

afterEach(() => vi.restoreAllMocks())

describe('Sanctum HTTP client', () => {
  it('uses credentialed cookies and sends the XSRF header for the backend origin', () => {
    expect(api.defaults.withCredentials).toBe(true)
    expect(api.defaults.withXSRFToken).toBe(true)
    expect(api.defaults.headers.Accept).toBe('application/json')
  })

  it('requests CSRF before login and then fetches the effective current user', async () => {
    const user = { id: 1, roles: ['operator'], permissions: ['units.view'] }
    const calls = []
    vi.spyOn(api, 'get').mockImplementation(async (url) => {
      calls.push(url)
      return { data: url === '/auth/me' ? { user } : {} }
    })
    vi.spyOn(api, 'post').mockImplementation(async (url) => {
      calls.push(url)
      return { data: {} }
    })

    const credentials = { email: 'user@example.test', password: 'example-password' }
    expect(await authService.login(credentials)).toEqual(user)
    expect(calls).toEqual(['/sanctum/csrf-cookie', '/auth/login', '/auth/me'])
    expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', { baseURL: backendBaseUrl })
    expect(api.post).toHaveBeenCalledWith('/auth/login', credentials)
  })

  it('does not post credentials when CSRF initialization fails', async () => {
    vi.spyOn(api, 'get').mockRejectedValue(new Error('CSRF unavailable'))
    vi.spyOn(api, 'post')
    await expect(authService.login({ email: 'user@example.test', password: 'example-password' })).rejects.toThrow('CSRF unavailable')
    expect(api.post).not.toHaveBeenCalled()
  })
})
