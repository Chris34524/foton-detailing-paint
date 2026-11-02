import { useState } from 'react'
import useAuth from '../auth/useAuth'

export default function DashboardPage() {
  const { user, roles, logout } = useAuth()
  const [isLoggingOut, setIsLoggingOut] = useState(false)
  const [error, setError] = useState('')

  async function handleLogout() {
    setIsLoggingOut(true)
    setError('')
    try {
      await logout()
    } catch {
      setError('No fue posible cerrar la sesión. Revisa la conexión e intenta de nuevo.')
    } finally {
      setIsLoggingOut(false)
    }
  }

  return (
    <main className="dashboard-layout">
      <header className="dashboard-header">
        <p className="brand">FOTON <span>Detailing &amp; Paint</span></p>
        <button onClick={handleLogout} disabled={isLoggingOut}>
          {isLoggingOut ? 'Cerrando sesión…' : 'Cerrar sesión'}
        </button>
      </header>
      <section className="workspace-card">
        <p className="eyebrow">ESPACIO DE TRABAJO</p>
        <h1>Bienvenido, {user.name}</h1>
        <p className="muted">{user.email}</p>
        <h2>Roles asignados</h2>
        {roles.length > 0
          ? <ul className="role-list">{roles.map((role) => <li key={role}>{role}</li>)}</ul>
          : <p>Sin roles asignados.</p>}
        <p className="muted">Los módulos operativos estarán disponibles en las siguientes fases.</p>
        {error && <p className="error" role="alert">{error}</p>}
      </section>
    </main>
  )
}
