import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import AuthProvider from './auth/AuthProvider'
import ProtectedRoute from './auth/ProtectedRoute'
import useAuth from './auth/useAuth'
import LoginPage from './pages/LoginPage'
import DashboardPage from './pages/DashboardPage'
import AuthenticatedLayout from './components/AuthenticatedLayout'
import PermissionRoute from './auth/PermissionRoute'
import UnitsPage from './pages/UnitsPage'
import UnitCreatePage from './pages/UnitCreatePage'
import UnitDetailPage from './pages/UnitDetailPage'
import { unitPermissions } from './units/permissions'

export function AppRoutes() {
  const { isLoading, sessionError, refreshUser } = useAuth()

  if (isLoading) return <main className="status-panel" role="status">Verificando sesión…</main>

  if (sessionError) {
    return (
      <main className="status-panel">
        <p role="alert">{sessionError}</p>
        <button onClick={() => refreshUser().catch(() => {})}>Reintentar</button>
      </main>
    )
  }

  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<ProtectedRoute />}>
        <Route element={<AuthenticatedLayout />}>
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route element={<PermissionRoute permission={unitPermissions.view} />}>
            <Route path="/units" element={<UnitsPage />} />
            <Route path="/units/:unitId" element={<UnitDetailPage />} />
          </Route>
          <Route element={<PermissionRoute permission={unitPermissions.create} />}>
            <Route path="/units/new" element={<UnitCreatePage />} />
          </Route>
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </BrowserRouter>
  )
}
