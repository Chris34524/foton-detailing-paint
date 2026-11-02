import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import AuthProvider from './auth/AuthProvider'
import ProtectedRoute from './auth/ProtectedRoute'
import useAuth from './auth/useAuth'
import LoginPage from './pages/LoginPage'
import DashboardPage from './pages/DashboardPage'

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
        <Route path="/dashboard" element={<DashboardPage />} />
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
