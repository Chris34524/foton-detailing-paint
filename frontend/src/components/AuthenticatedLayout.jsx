import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import AppIcon from './AppIcon'
import { roleLabels, workspaceModules, workspaceSection } from './workspaceNavigation'

export default function AuthenticatedLayout() {
  const { can, user, roles, logout } = useAuth()
  const { pathname } = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)
  const [isLoggingOut, setIsLoggingOut] = useState(false)
  const [error, setError] = useState('')
  const menuButton = useRef(null)
  const content = useRef(null)
  const navigation = useRef(null)
  const visibleModules = workspaceModules.filter((module) => module.to === '/materials' && can(module.permission))
  const section = workspaceSection(pathname)
  useEffect(() => { document.title = section + ' · FOTON' }, [section])
  useEffect(() => { if (menuOpen) navigation.current?.querySelector('a')?.focus() }, [menuOpen])
  async function handleLogout() {
    setIsLoggingOut(true); setError('')
    try { await logout() }
    catch { setError('No fue posible cerrar la sesión. Revisa la conexión e intenta de nuevo.') }
    finally { setIsLoggingOut(false) }
  }
  function closeMenu(focusContent = false) {
    setMenuOpen(false)
    if (focusContent) content.current?.focus()
  }
  return <div className="app-shell" data-menu-open={menuOpen} onKeyDown={(event) => {
    if (event.key === 'Escape' && menuOpen) { closeMenu(); menuButton.current?.focus() }
  }}>
    <a className="skip-link" href="#workspace-content" onClick={() => closeMenu()}>Saltar al contenido</a>
    <aside className="app-sidebar" id="workspace-navigation" ref={navigation}>
      <div className="sidebar-brand"><span className="brand-mark" aria-hidden="true">F</span><p className="brand">FOTON <span>Detailing &amp; Paint</span></p></div>
      <nav aria-label="Navegación principal">
        
        {['Operación', 'Recursos y calidad'].map((group) => visibleModules.some((module) => module.group === group) && <div className="navigation-group" key={group}>
          <p className="navigation-label">{group}</p>
          {visibleModules.filter((module) => module.group === group).map((module) => <Link key={module.to} to={module.to} aria-current={section === module.label ? "page" : undefined}
            className={section === module.label ? 'active' : undefined} onClick={() => closeMenu(true)}>
            <AppIcon name={module.icon} /><span>{module.label}</span>
          </Link>)}
        </div>)}
      </nav>
      <div className="sidebar-account">
        <div className="account-identity"><span className="account-avatar" aria-hidden="true">{user.name.slice(0, 1).toUpperCase()}</span>
          <div><span className="account-role">{roles.length ? roles.map((role) => roleLabels[role] || role).join(' · ') : 'Cuenta de usuario'}</span>
            <span className="account-email" title={user.email}>{user.email}</span></div></div>
        <button className="logout-button" onClick={handleLogout} disabled={isLoggingOut}><AppIcon name="logout" />
          {isLoggingOut ? 'Cerrando sesión…' : 'Cerrar sesión'}</button>
      </div>
    </aside>
    {menuOpen && <button className="navigation-backdrop" aria-label="Cerrar navegación" onClick={() => { closeMenu(); menuButton.current?.focus() }} />}
    <div className="app-main">
      <header className="app-topbar">
        <button className="menu-toggle" ref={menuButton} aria-expanded={menuOpen} aria-controls="workspace-navigation" aria-label={menuOpen ? 'Cerrar menú' : 'Abrir menú'} onClick={() => setMenuOpen((open) => !open)}>
          <AppIcon name={menuOpen ? 'close' : 'menu'} />
        </button>
        <div className="workspace-context"><span>Espacio de trabajo</span><strong>{section}</strong></div>
        <span className="topbar-product">Detallado y pintura</span>
      </header>
      <main id="workspace-content" className="workspace-content" ref={content} tabIndex={-1}>
        {error && <p className="error" role="alert">{error}</p>}
        <Outlet />
      </main>
      <footer className="app-footer"><span>FOTON · Detailing &amp; Paint</span><span>Control de operación</span></footer>
    </div>
  </div>
}
