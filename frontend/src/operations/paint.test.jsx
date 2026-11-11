import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AppRoutes } from '../App'
import AuthProvider from '../auth/AuthProvider'
import authService from '../auth/authService'
import operationsService from './operationsService'
import paintService from './paintService'
import { user } from './operationsFixtures'

vi.mock('../auth/authService', () => ({ default: { currentUser: vi.fn(), login: vi.fn(), logout: vi.fn() } }))
vi.mock('./operationsService', () => ({ default: { catalog: vi.fn() } }))
vi.mock('./paintService', () => ({ default: { list: vi.fn(), get: vi.fn(), versions: vi.fn(), create: vi.fn(),
  revise: vi.fn(), preview: vi.fn(), prepare: vi.fn(), preparations: vi.fn() } }))

const base = [
  { id: 1, code: 'PROD-001', name: 'Base blanca', kind: 'raw_material', is_active: true, unit: 'gr' },
  { id: 2, code: 'PROD-002', name: 'Pigmento negro', kind: 'raw_material', is_active: true, unit: 'gr' },
]
const formula = {
  id: 5, vehicle_model: 'FOTON S5', color_name: 'Blanco Perla', color_code: 'WP01', version: 1,
  is_current: true, total_grams: '500.00',
  product: { id: 3, code: 'PROD-003', name: 'Pintura Blanco Perla', unit: 'gr', stock_actual: '0.000000' },
  components: [
    { material_id: 1, code: 'PROD-001', name: 'Base blanca', grams: '400.00', unit: 'gr', stock_actual: '1000.000000' },
    { material_id: 2, code: 'PROD-002', name: 'Pigmento negro', grams: '100.00', unit: 'gr', stock_actual: '1000.000000' },
  ],
}
const preview = {
  formula_id: 5, version: 1, quantity_grams: '1000.00', total_ingredient_grams: '1000.00', can_confirm: true,
  components: [
    { material_id: 1, code: 'PROD-001', name: 'Base blanca', grams: '800.00', available_quantity: '1000.000000', unit: 'gr', sufficient: true },
    { material_id: 2, code: 'PROD-002', name: 'Pigmento negro', grams: '200.00', available_quantity: '1000.000000', unit: 'gr', sufficient: true },
  ],
  product: { id: 3, code: 'PROD-003', stock_before: '0.000000', unit: 'gr' },
}
const preparation = {
  id: 8, quantity_grams: '1000.00', output_folio: 'MOV-0005',
  components: [
    { material_id: 1, name: 'Base blanca', grams: '800.00', movement_folio: 'MOV-0003' },
    { material_id: 2, name: 'Pigmento negro', grams: '200.00', movement_folio: 'MOV-0004' },
  ],
}
const collection = (items) => ({ data: items, meta: { current_page: 1, last_page: 1, total: items.length } })
function renderApp() {
  return render(<MemoryRouter initialEntries={['/materials/paint-formulas']}>
    <AuthProvider><AppRoutes /></AuthProvider>
  </MemoryRouter>)
}
beforeEach(() => {
  vi.resetAllMocks()
  authService.currentUser.mockResolvedValue(user)
  operationsService.catalog.mockResolvedValue(collection(base))
  paintService.list.mockResolvedValue(collection([formula]))
  paintService.get.mockResolvedValue({ ...formula, product: { ...formula.product, stock_actual: '1000.000000' } })
  paintService.preparations.mockResolvedValue(collection([]))
  paintService.preview.mockResolvedValue(preview)
  paintService.prepare.mockResolvedValue(preparation)
  paintService.create.mockResolvedValue(formula)
  paintService.revise.mockResolvedValue({ ...formula, id: 6, version: 2 })
})
describe('paint formulation interface', () => {
  it('offers a tab, shows recipe components and previews exact grams without creating a movement', async () => {
    renderApp()
    expect(await screen.findByRole('heading', { name: 'Formulación de Pintura' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Formulación de Pintura' })).toHaveAttribute('href', '/materials/paint-formulas')
    const clicker = userEvent.setup()
    await clicker.click(await screen.findByRole('button', { name: 'Ver / Preparar' }))
    expect(screen.getByRole('region', { name: 'Composición de pintura' })).toHaveTextContent('400 g')
    await clicker.type(screen.getByLabelText('Cantidad a preparar (g)'), '1000.00')
    expect(await screen.findByText('Consumo calculado para 1000 g')).toBeInTheDocument()
    expect(paintService.preview).toHaveBeenCalledWith(5, '1000.00', expect.anything())
    expect(paintService.prepare).not.toHaveBeenCalled()
  })
  it('prepares a batch with UUID and displays the automatic movement folios', async () => {
    renderApp()
    const clicker = userEvent.setup()
    await clicker.click(await screen.findByRole('button', { name: 'Ver / Preparar' }))
    await clicker.type(screen.getByLabelText('Cantidad a preparar (g)'), '1000.00')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Confirmar preparación y actualizar inventario' })).toBeEnabled())
    await clicker.click(screen.getByRole('button', { name: 'Confirmar preparación y actualizar inventario' }))
    expect(await screen.findByText('Lote preparado #8')).toBeInTheDocument()
    expect(screen.getByText(/MOV-0005/)).toBeInTheDocument()
    expect(paintService.prepare).toHaveBeenCalledWith(5, expect.objectContaining({
      quantity_grams: '1000.00', idempotency_key: expect.any(String),
    }))
  })
  it('creates a new recipe from only raw materials and requires two-decimal grams', async () => {
    renderApp()
    const clicker = userEvent.setup()
    await clicker.click(await screen.findByRole('button', { name: 'Nueva fórmula' }))
    const editor = screen.getByRole('form', { name: 'Editor de fórmula' })
    await clicker.type(within(editor).getByLabelText('Modelo de vehículo'), 'FOTON S5')
    await clicker.type(within(editor).getByLabelText('Nombre de color'), 'Blanco Perla')
    await clicker.type(within(editor).getByLabelText('Código de color'), 'WP01')
    await clicker.selectOptions(within(editor).getByLabelText('Material base 1'), '1')
    await clicker.type(within(editor).getByLabelText('Gramaje (g)'), '125.35')
    await clicker.click(within(editor).getByRole('button', { name: 'Guardar fórmula' }))
    await waitFor(() => expect(paintService.create).toHaveBeenCalledWith(expect.objectContaining({
      color_code: 'WP01', components: [{ material_id: 1, grams: '125.35' }],
    })))
    expect(paintService.prepare).not.toHaveBeenCalled()
  })
  it('does not offer formula creation or preparation to a read-only Materials user', async () => {
    authService.currentUser.mockResolvedValue({ ...user, permissions: ['materials.view'] })
    renderApp()
    expect(await screen.findByText('Blanco Perla', { selector: 'strong' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Nueva fórmula' })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Ver / Preparar' }))
    expect(screen.queryByRole('button', { name: 'Confirmar preparación y actualizar inventario' })).not.toBeInTheDocument()
  })
})
