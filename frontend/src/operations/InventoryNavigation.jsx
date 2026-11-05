import { NavLink } from 'react-router-dom'
export default function InventoryNavigation() {
  return <nav className="module-navigation" aria-label="Navegación de materiales">
    <NavLink to="/materials" end>Inventario</NavLink><NavLink to="/materials/movements">Movimientos</NavLink>
    <NavLink to="/materials/inventory/dashboard">Dashboard de inventario</NavLink>
  </nav>
}
