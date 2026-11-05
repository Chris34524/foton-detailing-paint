import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import AuthContext from '../auth/AuthContext'
import AuthenticatedLayout from './AuthenticatedLayout'
import DashboardPage from '../pages/DashboardPage'

function renderWorkspace(permissions = [], path = '/dashboard', { email = 'test@example.com', roles = ['operator'] } = {}) {
  const auth = { user: { id: 1, name: 'Cristian', email }, roles, logout: vi.fn(),
    can: (permission) => roles.includes('super_admin') || permissions.includes(permission) }
  return render(<MemoryRouter initialEntries={[path]}><AuthContext.Provider value={auth}>
    <Routes><Route element={<AuthenticatedLayout />}>
      <Route path="/dashboard" element={<DashboardPage />} />
       <Route path="/materials" element={<h1>Materiales</h1>} />
      <Route path="/materials/movements/10" element={<h1>Detalle del movimiento</h1>} />
      <Route path="/inspection/checklists" element={<h1>Checklists</h1>} />
    </Route></Routes>
  </AuthContext.Provider></MemoryRouter>)
}
describe('workspace navigation and operational home', () => {
  it('offers only the modules and operational shortcuts authorized for the account', () => {
    renderWorkspace(['units.view', 'work_orders.view'])
    const navigation = screen.getByRole('navigation', { name: 'Navegación principal' })
    expect(within(navigation).queryByRole('link', { name: 'Unidades' })).not.toBeInTheDocument()
    expect(within(navigation).queryByRole('link', { name: 'Trabajos' })).not.toBeInTheDocument()
    expect(within(navigation).queryByRole('link', { name: 'Materiales' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Consultar unidades' })).toHaveAttribute('href', '/units')
    expect(screen.getByRole('link', { name: /Cola de trabajo/ })).toHaveAttribute('href', '/work-orders/queue')
    expect(screen.queryByRole('link', { name: /Registrar unidad/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Alertas de inventario/ })).not.toBeInTheDocument()
    expect(screen.getByText('Operador')).toBeInTheDocument()
  })
  it('shows all modules and Dashboard to the developer super administrator', () => {
    renderWorkspace([], '/dashboard', { email: 'developer@foton-detailing.local', roles: ['super_admin'] })
    const navigation = screen.getByRole('navigation', { name: 'Navegación principal' })
    for (const label of ['Dashboard', 'Unidades', 'Diagnóstico', 'Trabajos', 'Procesos', 'Materiales', 'Inspección']) {
      expect(within(navigation).getByRole('link', { name: label })).toBeInTheDocument()
    }
  })
  it('does not grant full navigation based solely on email or super admin role', () => {
    const first = renderWorkspace(['materials.view'], '/materials', {
      email: 'developer@foton-detailing.local', roles: ['operator'],
    })
    const navigation = screen.getByRole('navigation', { name: 'Navegación principal' })
    expect(within(navigation).queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument()
    expect(within(navigation).queryByRole('link', { name: 'Unidades' })).not.toBeInTheDocument()
    expect(within(navigation).getByRole('link', { name: 'Materiales' })).toBeInTheDocument()
    first.unmount()

    renderWorkspace([], '/materials', { email: 'other@example.com', roles: ['super_admin'] })
    const otherNavigation = screen.getByRole('navigation', { name: 'Navegación principal' })
    expect(within(otherNavigation).queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument()
    expect(within(otherNavigation).queryByRole('link', { name: 'Unidades' })).not.toBeInTheDocument()
    expect(within(otherNavigation).getByRole('link', { name: 'Materiales' })).toBeInTheDocument()
  })
  it('enables creation shortcuts with their specific permissions', () => {
    renderWorkspace(['units.view', 'units.create', 'materials.view', 'inspection.execute'])
    expect(screen.getByRole('link', { name: /Registrar unidad/ })).toHaveAttribute('href', '/units/new')
    expect(screen.getByRole('link', { name: /Alertas de inventario/ })).toHaveAttribute('href', '/materials/inventory/dashboard')
    expect(screen.getByRole('link', { name: /Nueva inspección/ })).toHaveAttribute('href', '/inspections/new')
  })
  it('explains how to proceed when no modules are enabled', () => {
    renderWorkspace()
    expect(screen.getByRole('heading', { name: 'Tu cuenta aún no tiene módulos habilitados' })).toBeInTheDocument()
    expect(screen.getByText(/Solicita a un administrador/)).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Accesos rápidos' })).not.toBeInTheDocument()
  })
  it('opens navigation with focus and returns focus on Escape', async () => {
    renderWorkspace(['materials.view'])
    const clicker = userEvent.setup()
    await clicker.click(screen.getByRole('button', { name: 'Abrir menú' }))
    expect(screen.getByRole('button', { name: 'Cerrar menú' })).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByRole('link', { name: 'Materiales' })).toHaveFocus()
    await clicker.keyboard('{Escape}')
    expect(screen.getByRole('button', { name: 'Abrir menú' })).toHaveAttribute('aria-expanded', 'false')
    expect(screen.getByRole('button', { name: 'Abrir menú' })).toHaveFocus()
  })
  it('closes the menu and focuses content after following a navigation link', async () => {
    renderWorkspace(['materials.view'])
    const clicker = userEvent.setup()
    await clicker.click(screen.getByRole('button', { name: 'Abrir menú' }))
    await clicker.click(screen.getByRole('link', { name: 'Materiales' }))
    expect(screen.getByRole('main')).toHaveFocus()
    expect(screen.getByRole('button', { name: 'Abrir menú' })).toHaveAttribute('aria-expanded', 'false')
    expect(screen.getByRole('link', { name: 'Saltar al contenido' })).toHaveAttribute('href', '#workspace-content')
  })
  it.each([['/materials/movements/10', 'Materiales', 'materials.view'], ['/inspection/checklists', 'Inspección', 'inspection.view']])('retains module context on %s', (path, label, permission) => {
    renderWorkspace([permission], path)
    const navigation = screen.getByRole('navigation', { name: 'Navegación principal' })
    if (label === 'Materiales') expect(within(navigation).getByRole('link', { name: label })).toHaveAttribute('aria-current', 'page')
    else expect(within(navigation).queryByRole('link', { name: label })).not.toBeInTheDocument()
    expect(document.title).toBe(label + ' · FOTON')
  })
})
