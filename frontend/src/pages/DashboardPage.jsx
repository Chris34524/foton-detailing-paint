import useAuth from '../auth/useAuth'

export default function DashboardPage() {
  const { user, roles } = useAuth()
  return <section className="workspace-card">
    <p className="eyebrow">ESPACIO DE TRABAJO</p>
    <h1>Bienvenido, {user.name}</h1>
    <p className="muted">{user.email}</p>
    <h2>Roles asignados</h2>
    {roles.length > 0
      ? <ul className="role-list">{roles.map((role) => <li key={role}>{role}</li>)}</ul>
      : <p>Sin roles asignados.</p>}
    <p className="muted">Consulta las unidades disponibles desde la navegación.</p>
  </section>
}