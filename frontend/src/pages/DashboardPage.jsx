import { Link } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import AppIcon from '../components/AppIcon'
import { workspaceModules } from '../components/workspaceNavigation'
import { workOrderPermissions } from '../workOrders/permissions'
import { unitPermissions } from '../units/permissions'
import { operationsPermissions } from '../operations/permissions'

export default function DashboardPage() {
  const { user, can } = useAuth()
  const modules = workspaceModules.filter((module) => can(module.permission))
  const shortcuts = [
    { permission: workOrderPermissions.view, to: '/work-orders/queue', icon: 'work', title: 'Cola de trabajo', description: 'Revisa las órdenes activas por prioridad.' },
    { permission: unitPermissions.create, to: '/units/new', icon: 'units', title: 'Registrar unidad', description: 'Inicia el registro de una nueva unidad.' },
    { permission: operationsPermissions.materialsView, to: '/materials/inventory/dashboard', icon: 'material', title: 'Alertas de inventario', description: 'Identifica insumos que necesitan atención.' },
    { permission: operationsPermissions.inspectionExecute, to: '/inspections/new', icon: 'inspection', title: 'Nueva inspección', description: 'Evalúa la calidad de un trabajo terminado.' },
  ].filter((shortcut) => can(shortcut.permission))
  return <div className="home-page">
    <section className="home-welcome">
      <div><p className="eyebrow">CONTROL DE OPERACIÓN</p><h1>Bienvenido, {user.name}</h1>
        <p>Tu punto de partida para dar seguimiento a las unidades, los recursos y la calidad del trabajo.</p></div>
      <div className="welcome-emblem" aria-hidden="true"><AppIcon name="units" /></div>
    </section>
    {shortcuts.length > 0 && <section className="home-shortcuts" aria-labelledby="shortcuts-heading">
      <div className="section-heading"><div><p className="eyebrow">A UN CLIC</p><h2 id="shortcuts-heading">Accesos rápidos</h2></div></div>
      <div className="shortcut-grid">{shortcuts.map((shortcut) => <Link className="shortcut-card" to={shortcut.to} key={shortcut.to}>
        <AppIcon name={shortcut.icon} /><div><strong>{shortcut.title}</strong><span>{shortcut.description}</span></div><AppIcon name="arrow" />
      </Link>)}</div>
    </section>}
    <section aria-labelledby="modules-heading">
      <div className="section-heading"><div><p className="eyebrow">ESPACIO DE TRABAJO</p><h2 id="modules-heading">Módulos disponibles</h2></div>
        <p className="muted">Selecciona el área en la que vas a trabajar.</p></div>
      {modules.length ? <div className="module-grid">{modules.map((module) => <article className="module-card" key={module.to}>
        <span className="module-icon"><AppIcon name={module.icon} /></span><h3>{module.label}</h3><p>{module.description}</p>
        <Link to={module.to}>{module.action}<AppIcon name="arrow" /></Link>
      </article>)}</div> : <div className="empty-state"><h3>Tu cuenta aún no tiene módulos habilitados</h3><p>Solicita a un administrador los accesos necesarios para tu trabajo.</p></div>}
    </section>
  </div>
}
