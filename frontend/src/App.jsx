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
import { diagnosisPermissions } from './diagnosis/permissions'
import DiagnosesPage from './pages/DiagnosesPage'
import DiagnosisCreatePage from './pages/DiagnosisCreatePage'
import DiagnosisDetailPage from './pages/DiagnosisDetailPage'
import DefectTypesPage from './pages/DefectTypesPage'
import WorkOrdersPage from './pages/WorkOrdersPage'
import WorkOrderDetailPage from './pages/WorkOrderDetailPage'
import { workOrderPermissions } from './workOrders/permissions'

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
          <Route element={<PermissionRoute permission={workOrderPermissions.view} />}>
            <Route path="/work-orders" element={<WorkOrdersPage />} />
            <Route path="/work-orders/queue" element={<WorkOrdersPage queue />} />
            <Route path="/work-orders/:workOrderId" element={<WorkOrderDetailPage />} />
          </Route>
          <Route element={<PermissionRoute permission={diagnosisPermissions.view} />}>
            <Route path="/diagnoses" element={<DiagnosesPage />} />
            <Route path="/diagnoses/:diagnosisId" element={<DiagnosisDetailPage />} />
            <Route path="/units/:unitId/diagnoses" element={<DiagnosesPage />} />
          </Route>
          <Route element={<PermissionRoute permission={diagnosisPermissions.create} />}>
            <Route path="/units/:unitId/diagnoses/new" element={<DiagnosisCreatePage />} />
          </Route>
          <Route element={<PermissionRoute permission={diagnosisPermissions.manageCatalog} />}>
            <Route path="/diagnosis/defect-types" element={<DefectTypesPage />} />
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
