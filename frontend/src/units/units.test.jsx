import { act, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AppRoutes } from '../App'
import AuthProvider from '../auth/AuthProvider'
import authService from '../auth/authService'
import unitService from './unitService'

vi.mock('../auth/authService', () => ({
  default: { currentUser: vi.fn(), login: vi.fn(), logout: vi.fn() },
}))
vi.mock('./unitService', async (importOriginal) => ({
  ...await importOriginal(),
  default: { list: vi.fn(), meta: vi.fn(), get: vi.fn(), findByVin: vi.fn(), create: vi.fn(), update: vi.fn() },
}))
const statuses = [
  { value: 'registered', label: 'Registrada' },
  { value: 'in_process', label: 'En proceso' },
  { value: 'blocked', label: 'Bloqueada' },
]
const unit = {
  id: 15, vin: 'LVBV3JBB7RA123456', model: 'S5', status: 'registered', status_label: 'Registrada',
  current_location: 'Recepción', registered_by: { id: 1, name: 'Development User' },
  created_at: '2026-11-02T12:00:00Z', updated_at: '2026-11-02T12:00:00Z',
}
const currentUser = { id: 1, name: 'Development User', email: 'developer@example.test', roles: ['admin'],
  permissions: ['units.view', 'units.create', 'units.update'] }
const collection = (data = [unit], meta = {}) => ({
  data, links: {}, meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length, ...meta },
})
const httpError = (status, data = {}) => ({ response: { status, data } })
function LocationProbe() {
  const location = useLocation()
  return <span data-testid="location">{location.pathname + location.search}</span>
}
function renderApp(path = '/units') {
  return render(<MemoryRouter initialEntries={[path]}>
    <AuthProvider><AppRoutes /><LocationProbe /></AuthProvider>
  </MemoryRouter>)
}
beforeEach(() => {
  vi.resetAllMocks()
  authService.currentUser.mockResolvedValue(currentUser)
  unitService.meta.mockResolvedValue(statuses)
  unitService.list.mockResolvedValue(collection())
  unitService.get.mockResolvedValue(unit)
})

describe('unit authorization and list', () => {
  it('redirects guests to login before requesting units', async () => {
    authService.currentUser.mockRejectedValue(httpError(401))
    renderApp()
    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument()
    expect(unitService.list).not.toHaveBeenCalled()
  })
  it.each(['/units', '/units/new', '/units/15'])('guards direct access to %s with a professional denied state', async (path) => {
    authService.currentUser.mockResolvedValue({ ...currentUser, permissions: [] })
    renderApp(path)
    expect(await screen.findByRole('heading', { name: 'Acceso denegado' })).toBeInTheDocument()
    expect(unitService.list).not.toHaveBeenCalled()
    expect(unitService.get).not.toHaveBeenCalled()
    expect(screen.queryByRole('link', { name: 'Unidades' })).not.toBeInTheDocument()
  })
  it('renders the server list, metadata labels, and the authenticated navigation', async () => {
    renderApp()
    expect(await screen.findByText(unit.vin)).toBeInTheDocument()
    expect(screen.getByRole('table', { name: 'Listado de unidades' })).toBeInTheDocument()
    expect(screen.getByText('Registrada', { selector: '.status-badge' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Registrar unidad' })).toHaveAttribute('href', '/units/new')
    expect(screen.getByRole('option', { name: 'En proceso' })).toHaveValue('in_process')
    expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument()
  })
  it('shows loading until the API returns', async () => {
    let finish
    unitService.list.mockReturnValue(new Promise((resolve) => { finish = resolve }))
    renderApp()
    expect(await screen.findByText('Cargando unidades…')).toHaveAttribute('role', 'status')
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    await act(async () => finish(collection()))
    expect(await screen.findByText(unit.vin)).toBeInTheDocument()
  })
  it('shows a clear empty state', async () => {
    unitService.list.mockResolvedValue(collection([]))
    renderApp()
    expect(await screen.findByRole('heading', { name: 'No hay unidades para mostrar' })).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })
  it('shows a friendly network error and supports retry', async () => {
    unitService.list.mockRejectedValueOnce(new Error('private exception')).mockResolvedValue(collection())
    renderApp()
    expect(await screen.findByRole('alert')).toHaveTextContent('No fue posible cargar las unidades')
    expect(screen.queryByText('private exception')).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByText(unit.vin)).toBeInTheDocument()
  })
  it('handles server denial even when the permission payload allowed navigation', async () => {
    unitService.list.mockRejectedValue(httpError(403))
    renderApp()
    expect(await screen.findByRole('alert')).toHaveTextContent('No tienes permiso')
  })
  it('requests the next server page and updates the URL', async () => {
    unitService.list.mockResolvedValueOnce(collection([unit], { last_page: 2, total: 21 }))
      .mockResolvedValueOnce(collection([{ ...unit, id: 16, vin: 'LVBV3JBB7RA123457' }], { current_page: 2, last_page: 2, total: 21 }))
    renderApp()
    await screen.findByText(unit.vin)
    await userEvent.click(screen.getByRole('button', { name: 'Siguiente' }))
    expect(await screen.findByText('LVBV3JBB7RA123457')).toBeInTheDocument()
    expect(unitService.list).toHaveBeenLastCalledWith({ page: '2' }, expect.objectContaining({ signal: expect.any(AbortSignal) }))
    expect(screen.getByTestId('location')).toHaveTextContent('/units?page=2')
    expect(screen.getByRole('button', { name: 'Siguiente' })).toBeDisabled()
  })
  it('applies explicit filters, resets pagination, and preserves them in the URL', async () => {
    renderApp('/units?page=3')
    await screen.findByText(unit.vin)
    const user = userEvent.setup()
    await user.type(screen.getByLabelText('Buscar'), 'LVB')
    await user.type(screen.getByLabelText('Filtrar por modelo'), 'S5')
    await user.selectOptions(screen.getByLabelText('Filtrar por estado'), 'in_process')
    await user.type(screen.getByLabelText('Filtrar por ubicación'), 'Planta 1')
    expect(unitService.list).toHaveBeenCalledTimes(1)
    await user.click(screen.getByRole('button', { name: 'Aplicar filtros' }))
    await waitFor(() => expect(unitService.list).toHaveBeenLastCalledWith({
      search: 'LVB', model: 'S5', status: 'in_process', location: 'Planta 1', sort: 'created_at', direction: 'desc', per_page: '20',
    }, expect.any(Object)))
    expect(screen.getByTestId('location')).not.toHaveTextContent('page=3')
    expect(screen.getByTestId('location')).toHaveTextContent('status=in_process')
  })
  it('restores filters from the URL on page load', async () => {
    renderApp('/units?model=S5&status=in_process&location=Planta+1')
    await screen.findByText(unit.vin)
    expect(screen.getByLabelText('Filtrar por modelo')).toHaveValue('S5')
    expect(screen.getByLabelText('Filtrar por estado')).toHaveValue('in_process')
    expect(unitService.list).toHaveBeenCalledWith({ model: 'S5', status: 'in_process', location: 'Planta 1' }, expect.any(Object))
  })
  it('hides registration for a viewer while retaining list access', async () => {
    authService.currentUser.mockResolvedValue({ ...currentUser, permissions: ['units.view'] })
    renderApp()
    await screen.findByText(unit.vin)
    expect(screen.queryByRole('link', { name: 'Registrar unidad' })).not.toBeInTheDocument()
  })
})

describe('VIN lookup and registration', () => {
  it('looks up an uppercase canonical VIN and opens its detail', async () => {
    unitService.findByVin.mockResolvedValue(unit)
    renderApp()
    const user = userEvent.setup()
    await user.type(await screen.findByLabelText('Consultar por VIN'), unit.vin.toLowerCase())
    expect(screen.getByLabelText('Consultar por VIN')).toHaveValue(unit.vin)
    await user.click(screen.getByRole('button', { name: 'Consultar VIN' }))
    expect(await screen.findByRole('heading', { name: 'Detalle de unidad' })).toBeInTheDocument()
    expect(unitService.findByVin).toHaveBeenCalledWith(unit.vin)
  })
  it.each([[404, 'Unidad no encontrada'], [422, 'Revisa los datos'], [500, 'No fue posible consultar el VIN']])('handles lookup failure %s', async (status, message) => {
    unitService.findByVin.mockRejectedValue(httpError(status))
    renderApp()
    await userEvent.type(await screen.findByLabelText('Consultar por VIN'), unit.vin)
    await userEvent.click(screen.getByRole('button', { name: 'Consultar VIN' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(message)
  })
  it('rejects invalid VINs locally without querying the API', async () => {
    renderApp()
    await userEvent.type(await screen.findByLabelText('Consultar por VIN'), 'INVALID')
    await userEvent.click(screen.getByRole('button', { name: 'Consultar VIN' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('17 letras')
    expect(unitService.findByVin).not.toHaveBeenCalled()
  })
  it('renders registration fields without initial status or VIN editing workflows', async () => {
    renderApp('/units/new')
    expect(await screen.findByLabelText('VIN')).toHaveAttribute('maxlength', '17')
    expect(screen.getByLabelText('Modelo')).toBeInTheDocument()
    expect(screen.getByLabelText('Ubicación actual')).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })
  it('registers a normalized VIN and navigates to the new detail', async () => {
    unitService.create.mockResolvedValue(unit)
    renderApp('/units/new')
    const user = userEvent.setup()
    await user.type(await screen.findByLabelText('VIN'), unit.vin.toLowerCase())
    await user.type(screen.getByLabelText('Modelo'), ' S5 ')
    await user.type(screen.getByLabelText('Ubicación actual'), ' Recepción ')
    await user.click(screen.getByRole('button', { name: 'Registrar unidad' }))
    expect(await screen.findByRole('heading', { name: 'Detalle de unidad' })).toBeInTheDocument()
    expect(unitService.create).toHaveBeenCalledWith({ vin: unit.vin, model: 'S5', current_location: 'Recepción' })
    expect(screen.getByTestId('location')).toHaveTextContent('/units/15')
  })
  it('shows duplicate VIN validation beside the VIN field', async () => {
    unitService.create.mockRejectedValue(httpError(422, { errors: { vin: ['Este VIN ya está registrado.'] } }))
    renderApp('/units/new')
    await userEvent.type(await screen.findByLabelText('VIN'), unit.vin)
    await userEvent.click(screen.getByRole('button', { name: 'Registrar unidad' }))
    expect(await screen.findByText('Este VIN ya está registrado.')).toBeInTheDocument()
    expect(screen.getByLabelText('VIN')).toHaveAttribute('aria-invalid', 'true')
  })
  it('does not submit an invalid VIN from registration', async () => {
    renderApp('/units/new')
    await screen.findByLabelText('VIN')
    await userEvent.click(screen.getByRole('button', { name: 'Registrar unidad' }))
    expect(await screen.findByText(/El VIN debe tener/)).toBeInTheDocument()
    expect(unitService.create).not.toHaveBeenCalled()
  })
  it.each([[403, 'No tienes permiso'], [500, 'No fue posible registrar la unidad']])('handles registration failure %s', async (status, message) => {
    unitService.create.mockRejectedValue(httpError(status))
    renderApp('/units/new')
    await userEvent.type(await screen.findByLabelText('VIN'), unit.vin)
    await userEvent.click(screen.getByRole('button', { name: 'Registrar unidad' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(message)
  })
})

describe('unit detail and updates', () => {
  it('loads all detail fields and uses the authoritative status label', async () => {
    renderApp('/units/15')
    await screen.findByRole('heading', { name: 'Detalle de unidad' })
    expect(screen.getByText(unit.vin)).toBeInTheDocument()
    expect(screen.getByText('Development User')).toBeInTheDocument()
    expect(screen.getByText('Fecha de registro')).toBeInTheDocument()
    expect(screen.getByText('Registrada', { selector: '.status-badge' })).toBeInTheDocument()
    expect(screen.queryByLabelText('VIN')).not.toBeInTheDocument()
  })
  it('shows read-only details without units.update', async () => {
    authService.currentUser.mockResolvedValue({ ...currentUser, permissions: ['units.view'] })
    renderApp('/units/15')
    await screen.findByRole('heading', { name: 'Detalle de unidad' })
    expect(screen.getByText('Consulta de solo lectura.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar cambios' })).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })
  it('lets an authorized user update model, status and location without sending a VIN', async () => {
    const updated = { ...unit, model: 'S6', status: 'in_process', status_label: 'En proceso', current_location: 'Planta 1 - Detallado' }
    unitService.update.mockResolvedValue(updated)
    renderApp('/units/15')
    const user = userEvent.setup()
    await screen.findByRole('heading', { name: 'Detalle de unidad' })
    await user.clear(screen.getByLabelText('Modelo'))
    await user.type(screen.getByLabelText('Modelo'), 'S6')
    await user.selectOptions(screen.getByLabelText('Estado'), 'in_process')
    await user.clear(screen.getByLabelText('Ubicación actual'))
    await user.type(screen.getByLabelText('Ubicación actual'), 'Planta 1 - Detallado')
    await user.click(screen.getByRole('button', { name: 'Guardar cambios' }))
    expect(await screen.findByText('Cambios guardados.')).toBeInTheDocument()
    expect(unitService.update).toHaveBeenCalledWith(15, { model: 'S6', status: 'in_process', current_location: 'Planta 1 - Detallado' })
    expect(screen.getByText('En proceso', { selector: '.status-badge' })).toBeInTheDocument()
    expect(screen.getByText('Planta 1 - Detallado', { selector: 'dd' })).toBeInTheDocument()
  })
  it('shows backend update validation and does not claim success', async () => {
    unitService.update.mockRejectedValue(httpError(422, { errors: { current_location: ['Ubicación no válida.'] } }))
    renderApp('/units/15')
    await userEvent.click(await screen.findByRole('button', { name: 'Guardar cambios' }))
    expect(await screen.findByText('Ubicación no válida.')).toBeInTheDocument()
    expect(screen.queryByText('Cambios guardados.')).not.toBeInTheDocument()
  })
  it('renders a friendly missing-unit page with navigation back', async () => {
    unitService.get.mockRejectedValue(httpError(404))
    renderApp('/units/999')
    expect(await screen.findByRole('heading', { name: 'Unidad no encontrada' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver a Unidades' })).toHaveAttribute('href', '/units')
  })
  it('rejects stale results when navigating away from a pending list', async () => {
    let finish
    unitService.list.mockReturnValue(new Promise((resolve) => { finish = resolve }))
    renderApp()
    await userEvent.click(await screen.findByRole('link', { name: 'Dashboard' }))
    await screen.findByRole('heading', { name: 'Bienvenido, Development User' })
    await act(async () => finish(collection()))
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })
})
