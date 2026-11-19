import { act, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AppRoutes } from '../App'
import AuthProvider from '../auth/AuthProvider'
import authService from '../auth/authService'
import unitService from '../units/unitService'
import diagnosisService from './diagnosisService'
import { unit, type, inactiveType, priorities, defect, diagnosis, currentUser, collection, httpError } from './diagnosisFixtures'

vi.mock('../auth/authService', () => ({ default: { currentUser: vi.fn(), login: vi.fn(), logout: vi.fn() } }))
vi.mock('../units/unitService', () => ({ default: { get: vi.fn(), meta: vi.fn() } }))
vi.mock('./diagnosisService', () => ({
  default: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), meta: vi.fn(), types: vi.fn(),
    createType: vi.fn(), updateType: vi.fn(), createDefect: vi.fn(), updateDefect: vi.fn(), upload: vi.fn(), image: vi.fn() },
}))
function LocationProbe() {
  const location = useLocation()
  return <span data-testid="location">{location.pathname + location.search}</span>
}
function renderApp(path = '/diagnoses') {
  return render(<MemoryRouter initialEntries={[path]}><AuthProvider><AppRoutes /><LocationProbe /></AuthProvider></MemoryRouter>)
}
beforeEach(() => {
  vi.resetAllMocks()
  authService.currentUser.mockResolvedValue(currentUser)
  unitService.get.mockResolvedValue(unit)
  unitService.meta.mockResolvedValue([{ value: 'registered', label: 'Registrada' }])
  diagnosisService.list.mockResolvedValue(collection())
  diagnosisService.get.mockResolvedValue(structuredClone(diagnosis))
  diagnosisService.meta.mockResolvedValue(priorities)
  diagnosisService.types.mockImplementation(async (params) => collection(params?.include_inactive ? [type, inactiveType] : [type]))
})

describe('diagnosis navigation, lists and permissions', () => {
  it('shows Diagnosis navigation and keeps catalog administration inside the module', async () => {
    renderApp()
    await screen.findByRole('heading', { name: 'Diagnósticos' })
    const navigation = screen.getByRole('navigation', { name: 'Navegación principal' })
    expect(within(navigation).queryByRole('link', { name: 'Diagnóstico' })).not.toBeInTheDocument()
    expect(within(navigation).queryByRole('link', { name: 'Catálogo de tipos' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Catálogo de tipos' })).toBeInTheDocument()
  })
  it('hides Diagnosis navigation from users without diagnosis.view', async () => {
    authService.currentUser.mockResolvedValue({ ...currentUser, permissions: ['units.view'] })
    renderApp('/dashboard')
    await screen.findByRole('heading', { name: /Bienvenido/ })
    expect(screen.queryByRole('link', { name: 'Diagnóstico' })).not.toBeInTheDocument()
  })
  it.each(['/diagnoses', '/diagnoses/12', '/units/7/diagnoses', '/units/7/diagnoses/new', '/diagnosis/defect-types'])('guards direct access to %s', async (path) => {
    authService.currentUser.mockResolvedValue({ ...currentUser, permissions: [] })
    renderApp(path)
    expect(await screen.findByRole('heading', { name: 'Acceso denegado' })).toBeInTheDocument()
    expect(diagnosisService.list).not.toHaveBeenCalled()
    expect(diagnosisService.get).not.toHaveBeenCalled()
    expect(diagnosisService.types).not.toHaveBeenCalled()
  })
  it('redirects a guest to login', async () => {
    authService.currentUser.mockRejectedValue(httpError(401))
    renderApp()
    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument()
    expect(diagnosisService.list).not.toHaveBeenCalled()
  })
  it('renders server list data, defect count and author', async () => {
    renderApp()
    expect(await screen.findByText(unit.vin)).toBeInTheDocument()
    expect(screen.getByText('Técnico')).toBeInTheDocument()
    expect(screen.getByRole('table', { name: 'Listado de diagnósticos' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver diagnóstico 12' })).toHaveAttribute('href', '/diagnoses/12')
  })
  it('waits for the server without showing stale rows', async () => {
    let finish
    diagnosisService.list.mockReturnValue(new Promise((resolve) => { finish = resolve }))
    renderApp()
    expect(await screen.findByText('Cargando diagnósticos…')).toHaveAttribute('role', 'status')
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    await act(async () => finish(collection()))
    expect(await screen.findByText(unit.vin)).toBeInTheDocument()
  })
  it('shows a useful empty state', async () => {
    diagnosisService.list.mockResolvedValue(collection([]))
    renderApp()
    expect(await screen.findByRole('heading', { name: 'No hay diagnósticos para mostrar' })).toBeInTheDocument()
  })
  it('shows a network error and retries without exposing exception details', async () => {
    diagnosisService.list.mockRejectedValueOnce(new Error('internal filesystem path')).mockResolvedValue(collection())
    renderApp()
    expect(await screen.findByRole('alert')).toHaveTextContent('No fue posible completar')
    expect(screen.queryByText('internal filesystem path')).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByText(unit.vin)).toBeInTheDocument()
  })
  it('paginates through the API and preserves page in the URL', async () => {
    diagnosisService.list.mockResolvedValueOnce(collection([diagnosis], { last_page: 2, total: 21 }))
      .mockResolvedValueOnce(collection([{ ...diagnosis, id: 13 }], { current_page: 2, last_page: 2, total: 21 }))
    renderApp()
    await screen.findByText(unit.vin)
    await userEvent.click(screen.getByRole('button', { name: 'Siguiente' }))
    expect(await screen.findByRole('link', { name: 'Ver diagnóstico 13' })).toBeInTheDocument()
    expect(diagnosisService.list).toHaveBeenLastCalledWith({ page: '2' }, undefined, expect.any(Object))
    expect(screen.getByTestId('location')).toHaveTextContent('page=2')
  })
  it('applies explicit search rather than issuing a request for each keystroke', async () => {
    renderApp('/diagnoses?page=3')
    await screen.findByText(unit.vin)
    await userEvent.type(screen.getByLabelText('Buscar diagnósticos'), 'Rayón')
    expect(diagnosisService.list).toHaveBeenCalledTimes(1)
    await userEvent.click(screen.getByRole('button', { name: 'Buscar', exact: true }))
    await waitFor(() => expect(diagnosisService.list).toHaveBeenLastCalledWith({ search: 'Rayón' }, undefined, expect.any(Object)))
    expect(screen.getByTestId('location')).toHaveTextContent('search=Ray')
    expect(screen.getByTestId('location')).not.toHaveTextContent('page=3')
  })
  it('loads paginated diagnosis history in a Unit context', async () => {
    renderApp('/units/7/diagnoses')
    await screen.findByRole('heading', { name: 'Diagnósticos de la unidad' })
    await screen.findByRole('table')
    expect(diagnosisService.list).toHaveBeenCalledWith({}, '7', expect.any(Object))
    expect(screen.getByRole('link', { name: 'Nuevo diagnóstico' })).toHaveAttribute('href', '/units/7/diagnoses/new')
  })
  it('integrates diagnosis actions without removing Unit editing', async () => {
    renderApp('/units/7')
    await screen.findByRole('heading', { name: 'Detalle de unidad' })
    expect(screen.getByRole('link', { name: 'Ver diagnósticos' })).toHaveAttribute('href', '/units/7/diagnoses')
    expect(screen.getByRole('link', { name: 'Nuevo diagnóstico' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Guardar cambios' })).toBeInTheDocument()
  })
})

describe('diagnosis creation and editing', () => {
  it('shows Unit context and creates a diagnosis with optional plain notes', async () => {
    diagnosisService.create.mockResolvedValue(diagnosis)
    renderApp('/units/7/diagnoses/new')
    await screen.findByLabelText('Notas generales')
    expect(screen.getByText(unit.vin)).toBeInTheDocument()
    expect(screen.getByText('Recepción')).toBeInTheDocument()
    await userEvent.type(screen.getByLabelText('Notas generales'), ' Diagnóstico inicial ')
    await userEvent.click(screen.getByRole('button', { name: 'Crear diagnóstico' }))
    expect(await screen.findByRole('heading', { name: 'Detalle de diagnóstico' })).toBeInTheDocument()
    expect(diagnosisService.create).toHaveBeenCalledWith(7, { notes: 'Diagnóstico inicial' })
    expect(screen.getByTestId('location')).toHaveTextContent('/diagnoses/12')
  })
  it('renders creation validation beside notes', async () => {
    diagnosisService.create.mockRejectedValue(httpError(422, { errors: { notes: ['Ingresa texto sin HTML.'] } }))
    renderApp('/units/7/diagnoses/new')
    await userEvent.click(await screen.findByRole('button', { name: 'Crear diagnóstico' }))
    expect(await screen.findByText('Ingresa texto sin HTML.')).toBeInTheDocument()
  })
  it.each([[403, 'No tienes permiso'], [503, 'No fue posible completar']])('handles creation failure %s', async (status, message) => {
    diagnosisService.create.mockRejectedValue(httpError(status))
    renderApp('/units/7/diagnoses/new')
    await userEvent.click(await screen.findByRole('button', { name: 'Crear diagnóstico' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(message)
  })
  it('renders diagnosis/defect details and hides all editing controls for a viewer', async () => {
    authService.currentUser.mockResolvedValue({ ...currentUser, permissions: ['diagnosis.view'] })
    renderApp('/diagnoses/12')
    await screen.findByRole('heading', { name: 'Detalle de diagnóstico' })
    expect(screen.getByText('Consulta de solo lectura.')).toBeInTheDocument()
    expect(screen.getByText('Alta', { selector: '.priority-badge' })).toBeInTheDocument()
    expect(screen.getByText(defect.location)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Agregar defecto' })).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Seleccionar evidencia')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar notas' })).not.toBeInTheDocument()
    expect(diagnosisService.meta).not.toHaveBeenCalled()
    expect(diagnosisService.types).not.toHaveBeenCalled()
  })
  it('updates notes without refetching metadata repeatedly', async () => {
    diagnosisService.update.mockResolvedValue({ ...diagnosis, notes: 'Nuevas notas' })
    renderApp('/diagnoses/12')
    const input = await screen.findByLabelText('Editar notas generales')
    await userEvent.clear(input); await userEvent.type(input, ' Nuevas notas ')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar notas' }))
    expect(await screen.findByText('Notas guardadas.')).toBeInTheDocument()
    expect(diagnosisService.update).toHaveBeenCalledWith(12, { notes: 'Nuevas notas' })
    expect(diagnosisService.meta).toHaveBeenCalledTimes(1)
    expect(diagnosisService.types).toHaveBeenCalledTimes(1)
  })
  it.each([[403, 'No tienes permiso'], [404, 'Registro no encontrado']])('shows a friendly detail failure %s', async (status, message) => {
    diagnosisService.get.mockRejectedValue(httpError(status))
    renderApp('/diagnoses/12')
    expect(await screen.findByRole('alert')).toHaveTextContent(message)
    if (status === 404) expect(screen.getByRole('heading', { name: 'Diagnóstico no encontrado' })).toBeInTheDocument()
  })
})

describe('defect workflow and catalog governance', () => {
  async function openDefectForm() {
    renderApp('/diagnoses/12')
    await userEvent.click(await screen.findByRole('button', { name: 'Agregar defecto' }))
  }
  it('loads active catalog types and authoritative priorities without a silent default', async () => {
    diagnosisService.meta.mockResolvedValue([{ value: 'high', label: 'Prioridad del servidor' }])
    await openDefectForm()
    expect(screen.getByRole('option', { name: type.name })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: inactiveType.name })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Prioridad')).toHaveValue('')
    expect(screen.getByRole('option', { name: 'Prioridad del servidor' })).toHaveValue('high')
  })
  it('creates a defect with physical location and explicit priority', async () => {
    diagnosisService.createDefect.mockResolvedValue({ ...defect, id: 46, location: 'Cabina', description: null })
    await openDefectForm()
    await userEvent.selectOptions(screen.getByLabelText('Tipo de defecto'), '3')
    await userEvent.type(screen.getByLabelText('Ubicación del defecto'), ' Cabina ')
    await userEvent.selectOptions(screen.getByLabelText('Prioridad'), 'high')
    await userEvent.click(screen.getByRole('button', { name: 'Registrar defecto' }))
    expect(await screen.findByRole('article', { name: 'Defecto 46' })).toBeInTheDocument()
    expect(diagnosisService.createDefect).toHaveBeenCalledWith(12, { defect_type_id: 3, location: 'Cabina', priority: 'high', description: null })
  })
  it('displays backend defect validation', async () => {
    diagnosisService.createDefect.mockRejectedValue(httpError(422, { errors: { location: ['La ubicación del defecto es obligatoria.'] } }))
    await openDefectForm()
    await userEvent.click(screen.getByRole('button', { name: 'Registrar defecto' }))
    expect(await screen.findByText('La ubicación del defecto es obligatoria.')).toBeInTheDocument()
    expect(screen.getByLabelText('Ubicación del defecto')).toHaveAttribute('aria-invalid', 'true')
  })
  it('edits defect location and priority', async () => {
    diagnosisService.updateDefect.mockResolvedValue({ ...defect, location: 'Cabina', priority: 'critical', priority_label: 'Crítica' })
    renderApp('/diagnoses/12')
    await userEvent.click(await screen.findByRole('button', { name: 'Editar defecto' }))
    const location = screen.getByLabelText('Ubicación del defecto')
    await userEvent.clear(location); await userEvent.type(location, 'Cabina')
    await userEvent.selectOptions(screen.getByLabelText('Prioridad'), 'critical')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar defecto' }))
    expect(await screen.findByText('Crítica', { selector: '.priority-badge' })).toBeInTheDocument()
    expect(diagnosisService.updateDefect).toHaveBeenCalledWith(12, 45, { defect_type_id: 3, location: 'Cabina', priority: 'critical', description: defect.description })
  })
  it('preserves an inactive historical type when editing an existing defect', async () => {
    diagnosisService.get.mockResolvedValue({ ...diagnosis, defects: [{ ...defect, defect_type: { ...type, is_active: false } }] })
    diagnosisService.types.mockResolvedValue(collection([]))
    renderApp('/diagnoses/12')
    await userEvent.click(await screen.findByRole('button', { name: 'Editar defecto' }))
    expect(screen.getByRole('option', { name: type.name + ' (inactivo actual)' })).toHaveValue('3')
    expect(screen.getByText('Tipo inactivo · conservado en el historial')).toBeInTheDocument()
  })
  it('offers a professional empty catalog state rather than inventing types', async () => {
    diagnosisService.types.mockResolvedValue(collection([]))
    await openDefectForm()
    expect(screen.getByText('No hay tipos de defecto configurados.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Registrar defecto' })).toBeDisabled()
    expect(screen.getByRole('link', { name: 'Crear un tipo de defecto' })).toHaveAttribute('href', '/diagnosis/defect-types')
    expect(screen.queryByLabelText('Buscar tipo por nombre')).not.toBeInTheDocument()
  })
  it('allows an authorized catalog manager to create a type', async () => {
    const created = { ...type, id: 5, name: 'Nuevo tipo' }
    diagnosisService.createType.mockResolvedValue(created)
    diagnosisService.types.mockResolvedValueOnce(collection([type])).mockResolvedValueOnce(collection([type, created]))
    renderApp('/diagnosis/defect-types')
    await screen.findByRole('table')
    await userEvent.type(screen.getByLabelText('Nombre del tipo'), ' Nuevo tipo ')
    await userEvent.click(screen.getByRole('button', { name: 'Crear tipo', exact: true }))
    expect(await screen.findByText('Nuevo tipo', { selector: 'td' })).toBeInTheDocument()
    expect(diagnosisService.createType).toHaveBeenCalledWith({ name: 'Nuevo tipo', description: null, is_active: true })
  })
  it('lets a manager configure an empty catalog and return to the same diagnosis to add a defect', async () => {
    diagnosisService.types.mockResolvedValueOnce(collection([])).mockResolvedValueOnce(collection([]))
      .mockResolvedValueOnce(collection([type])).mockResolvedValueOnce(collection([type]))
    diagnosisService.createType.mockResolvedValue(type)
    await openDefectForm()
    await userEvent.click(screen.getByRole('link', { name: 'Crear un tipo de defecto' }))
    await screen.findByRole('heading', { name: 'Catálogo de tipos de defecto' })
    await userEvent.type(screen.getByLabelText('Nombre del tipo'), type.name)
    await userEvent.click(screen.getByRole('button', { name: 'Crear tipo', exact: true }))
    await screen.findByText(type.name, { selector: 'td' })
    await userEvent.click(screen.getByRole('link', { name: /Volver al diagnóstico/ }))
    await userEvent.click(await screen.findByRole('button', { name: 'Agregar defecto' }))
    expect(screen.getByRole('option', { name: type.name })).toHaveValue('3')
    expect(screen.getByRole('button', { name: 'Registrar defecto' })).toBeEnabled()
  })
  it('distinguishes an empty type search from an unconfigured catalog and can restore the options', async () => {
    diagnosisService.types.mockResolvedValueOnce(collection([type])).mockResolvedValueOnce(collection([]))
    await openDefectForm()
    await userEvent.type(screen.getByLabelText('Buscar tipo por nombre'), 'Golpe')
    await userEvent.click(screen.getByRole('button', { name: 'Buscar tipos', exact: true }))
    expect(await screen.findByText(/No hay tipos activos que coincidan/)).toBeInTheDocument()
    expect(screen.queryByText('No hay tipos de defecto configurados.')).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Limpiar búsqueda de tipos' }))
    expect(screen.getByRole('option', { name: type.name })).toBeInTheDocument()
  })
  it('allows catalog name and description editing', async () => {
    const updated = { ...type, name: 'Tipo corregido', description: 'Descripción corregida' }
    diagnosisService.updateType.mockResolvedValue(updated)
    diagnosisService.types.mockResolvedValueOnce(collection([type])).mockResolvedValueOnce(collection([updated]))
    renderApp('/diagnosis/defect-types')
    await userEvent.click(await screen.findByRole('button', { name: 'Editar tipo ' + type.name }))
    const name = screen.getByLabelText('Nombre del tipo')
    await userEvent.clear(name); await userEvent.type(name, updated.name)
    await userEvent.type(screen.getByLabelText('Descripción del tipo'), updated.description)
    await userEvent.click(screen.getByRole('button', { name: 'Guardar tipo' }))
    expect(await screen.findByText(updated.name, { selector: 'td' })).toBeInTheDocument()
    expect(diagnosisService.updateType).toHaveBeenCalledWith(3, { name: updated.name, description: updated.description, is_active: true })
  })
  it('deactivates types without providing a delete action', async () => {
    diagnosisService.updateType.mockResolvedValue({ ...type, is_active: false })
    diagnosisService.types.mockResolvedValueOnce(collection([type])).mockResolvedValueOnce(collection([{ ...type, is_active: false }]))
    renderApp('/diagnosis/defect-types')
    await userEvent.click(await screen.findByRole('button', { name: 'Desactivar ' + type.name }))
    expect(await screen.findByText('Inactivo', { selector: 'span' })).toBeInTheDocument()
    expect(diagnosisService.updateType).toHaveBeenCalledWith(3, { is_active: false })
    expect(screen.queryByRole('button', { name: /Eliminar/ })).not.toBeInTheDocument()
  })
  it('hides catalog administration for a non-manager', async () => {
    authService.currentUser.mockResolvedValue({ ...currentUser, permissions: ['diagnosis.view'] })
    renderApp()
    await screen.findByText(unit.vin)
    expect(screen.queryByRole('link', { name: 'Catálogo de tipos' })).not.toBeInTheDocument()
  })
})
