import { useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import { unitPermissions } from '../units/permissions'
import { diagnosisPermissions } from '../diagnosis/permissions'

export default function AuthenticatedLayout() {
  const { can, logout } = useAuth()
  const [isLoggingOut, setIsLoggingOut] = useState(false)
  const [error, setError] = useState('')
  async function handleLogout() {
    setIsLoggingOut(true)
    setError('')
    try { await logout() }
    catch { setError('No fue posible cerrar la sesión. Revisa la conexión e intenta de nuevo.') }
    finally { setIsLoggingOut(false) }
  }
  return <main className="dashboard-layout">
    <header className="dashboard-header">
      <p className="brand">FOTON <span>Detailing &amp; Paint</span></p>
      <nav aria-label="Navegación principal">
        <NavLink to="/dashboard">Dashboard</NavLink>
        {can(unitPermissions.view) && <NavLink to="/units">Unidades</NavLink>}
        {can(diagnosisPermissions.view) && <NavLink to="/diagnoses">Diagnóstico</NavLink>}
      </nav>
      <button onClick={handleLogout} disabled={isLoggingOut}>
        {isLoggingOut ? 'Cerrando sesión…' : 'Cerrar sesión'}
      </button>
    </header>
    {error && <p className="error" role="alert">{error}</p>}
    <Outlet />
  </main>
}
