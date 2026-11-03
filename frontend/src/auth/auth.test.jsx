import { StrictMode } from 'react'
import { act, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AppRoutes } from '../App'
import AuthProvider from './AuthProvider'
import authService from './authService'
import useAuth from './useAuth'
import api from '../services/api'

vi.mock('./authService', () => ({
  default: { currentUser: vi.fn(), login: vi.fn(), logout: vi.fn() },
}))

const currentUser = {
  id: 1,
  name: 'Development User',
  email: 'developer@foton-detailing.local',
  roles: ['operator'],
  permissions: ['units.view', 'materials.consume'],
}

const httpError = (status, data = {}) => ({ response: { status, data } })

function renderApp(path = '/login', { strict = false } = {}) {
  const app = (
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider><AppRoutes /></AuthProvider>
    </MemoryRouter>
  )
  return render(strict ? <StrictMode>{app}</StrictMode> : app)
}

async function submitLogin() {
  const user = userEvent.setup()
  await user.type(await screen.findByLabelText('Correo electrónico'), currentUser.email)
  await user.type(screen.getByLabelText('Contraseña'), 'example-password')
  await user.click(screen.getByRole('button', { name: 'Entrar' }))
}

beforeEach(() => {
  vi.resetAllMocks()
  authService.currentUser.mockRejectedValue(httpError(401))
})

describe('session authentication', () => {
  it('reveals and hides the password without changing its value or submitting', async () => {
    renderApp()
    const clicker = userEvent.setup()
    const password = await screen.findByLabelText('Contraseña')
    await clicker.type(password, 'example-password')
    await clicker.click(screen.getByRole('button', { name: 'Mostrar contraseña' }))
    expect(password).toHaveAttribute('type', 'text')
    expect(password).toHaveValue('example-password')
    expect(screen.getByRole('button', { name: 'Ocultar contraseña' })).toHaveAttribute('aria-pressed', 'true')
    await clicker.click(screen.getByRole('button', { name: 'Ocultar contraseña' }))
    expect(password).toHaveAttribute('type', 'password')
    expect(authService.login).not.toHaveBeenCalled()
  })

  it('focuses the login error so keyboard users can recover', async () => {
    authService.login.mockRejectedValue(httpError(401))
    renderApp()
    await submitLogin()
    expect(await screen.findByRole('alert')).toHaveFocus()
  })

  it('renders the login form after determining that the browser is a guest', async () => {
    renderApp()
    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument()
    expect(screen.getByLabelText('Correo electrónico')).toHaveAttribute('type', 'email')
    expect(screen.getByLabelText('Contraseña')).toHaveAttribute('type', 'password')
  })

  it('updates authentication state after successful login', async () => {
    authService.login.mockResolvedValue(currentUser)
    renderApp()
    await submitLogin()
    expect(await screen.findByRole('heading', { name: 'Bienvenido, Development User' })).toBeInTheDocument()
    expect(screen.getByText('Operador')).toBeInTheDocument()
    expect(authService.login).toHaveBeenCalledWith({ email: currentUser.email, password: 'example-password' })
  })

  it('displays a generic error for invalid credentials', async () => {
    authService.login.mockRejectedValue(httpError(401))
    renderApp()
    await submitLogin()
    expect(await screen.findByRole('alert')).toHaveTextContent('El correo o la contraseña son incorrectos.')
    expect(screen.queryByRole('heading', { name: /Bienvenido/ })).not.toBeInTheDocument()
  })

  it('displays server validation errors next to the corresponding field', async () => {
    authService.login.mockRejectedValue(httpError(422, { errors: { email: ['El correo es obligatorio.'] } }))
    renderApp()
    await userEvent.click(await screen.findByRole('button', { name: 'Entrar' }))
    expect(await screen.findByText('El correo es obligatorio.')).toBeInTheDocument()
    expect(screen.getByLabelText('Correo electrónico')).toHaveAttribute('aria-invalid', 'true')
  })

  it('shows a rate limit message', async () => {
    authService.login.mockRejectedValue(httpError(429))
    renderApp()
    await submitLogin()
    expect(await screen.findByRole('alert')).toHaveTextContent('Demasiados intentos.')
  })

  it('redirects a guest away from a protected route', async () => {
    renderApp('/dashboard')
    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument()
    expect(screen.queryByText(currentUser.email)).not.toBeInTheDocument()
  })

  it('restores an existing browser session and displays protected content', async () => {
    authService.currentUser.mockResolvedValue(currentUser)
    renderApp('/dashboard')
    expect(await screen.findByRole('heading', { name: 'Bienvenido, Development User' })).toBeInTheDocument()
    expect(screen.getByText(currentUser.email)).toBeInTheDocument()
  })

  it('redirects an authenticated user visiting login to the dashboard', async () => {
    authService.currentUser.mockResolvedValue(currentUser)
    renderApp('/login')
    expect(await screen.findByRole('heading', { name: 'Bienvenido, Development User' })).toBeInTheDocument()
    expect(screen.queryByLabelText('Contraseña')).not.toBeInTheDocument()
  })

  it('keeps protected content hidden until bootstrap finishes and deduplicates StrictMode requests', async () => {
    let finishBootstrap
    authService.currentUser.mockReturnValue(new Promise((resolve) => { finishBootstrap = resolve }))
    renderApp('/dashboard', { strict: true })
    expect(screen.getByRole('status')).toHaveTextContent('Verificando sesión')
    expect(screen.queryByRole('button', { name: 'Cerrar sesión' })).not.toBeInTheDocument()
    expect(authService.currentUser).toHaveBeenCalledTimes(1)
    await act(async () => finishBootstrap(currentUser))
    expect(await screen.findByRole('heading', { name: 'Bienvenido, Development User' })).toBeInTheDocument()
  })

  it('clears auth state when logout succeeds', async () => {
    authService.currentUser.mockResolvedValue(currentUser)
    authService.logout.mockResolvedValue(undefined)
    renderApp('/dashboard')
    await userEvent.click(await screen.findByRole('button', { name: 'Cerrar sesión' }))
    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument()
    expect(authService.logout).toHaveBeenCalledOnce()
  })

  it('shows a logout connection failure without claiming the server session ended', async () => {
    authService.currentUser.mockResolvedValue(currentUser)
    authService.logout.mockRejectedValue(new Error('Network unavailable'))
    renderApp('/dashboard')
    await userEvent.click(await screen.findByRole('button', { name: 'Cerrar sesión' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('No fue posible cerrar la sesión.')
    expect(screen.getByRole('heading', { name: 'Bienvenido, Development User' })).toBeInTheDocument()
  })

  it.each([401, 419])('clears stale state after a protected API request returns %s without refetch loops', async (status) => {
    authService.currentUser.mockResolvedValue(currentUser)
    renderApp('/dashboard')
    await screen.findByRole('heading', { name: 'Bienvenido, Development User' })
    await act(async () => {
      await api.get('/protected-example', {
        adapter: async (config) => { throw { config, response: { status } } },
      }).catch(() => {})
    })
    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument()
    expect(authService.currentUser).toHaveBeenCalledTimes(1)
  })

  it('offers retry when bootstrap cannot reach the backend', async () => {
    authService.currentUser.mockRejectedValueOnce(new Error('Network unavailable')).mockResolvedValue(currentUser)
    renderApp('/dashboard')
    expect(await screen.findByRole('alert')).toHaveTextContent('No fue posible verificar la sesión.')
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByRole('heading', { name: 'Bienvenido, Development User' })).toBeInTheDocument()
    expect(authService.currentUser).toHaveBeenCalledTimes(2)
  })

  it('exposes role and permission helpers from the effective user payload', async () => {
    authService.currentUser.mockResolvedValue(currentUser)
    function Permissions() {
      const auth = useAuth()
      if (auth.isLoading) return null
      return <output data-testid="authorization">{JSON.stringify({
        hasRole: auth.hasRole('operator'),
        hasAnyRole: auth.hasAnyRole(['admin', 'operator']),
        can: auth.can('units.view'),
        canAny: auth.canAny(['users.manage', 'materials.consume']),
        cannot: auth.can('users.manage'),
      })}</output>
    }
    render(<AuthProvider><Permissions /></AuthProvider>)
    await waitFor(() => expect(screen.getByTestId('authorization')).toHaveTextContent(JSON.stringify({
      hasRole: true, hasAnyRole: true, can: true, canAny: true, cannot: false,
    })))
  })
})
