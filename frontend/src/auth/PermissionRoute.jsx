import { Link, Outlet } from 'react-router-dom'
import useAuth from './useAuth'

export default function PermissionRoute({ permission }) {
  const { can } = useAuth()
  if (can(permission)) return <Outlet />
  return <section className="workspace-card">
    <h1>Acceso denegado</h1>
    <p>No tienes permiso para acceder a esta sección.</p>
    <Link to="/dashboard">Volver al Dashboard</Link>
  </section>
}