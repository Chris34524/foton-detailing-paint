import { Navigate, Outlet, useLocation } from 'react-router-dom'
import useAuth from './useAuth'

export default function ProtectedRoute() {
  const { isAuthenticated, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) return <p role="status">Verificando sesión…</p>
  if (!isAuthenticated) return <Navigate to="/login" state={{ from: location.pathname }} replace />
  return <Outlet />
}
