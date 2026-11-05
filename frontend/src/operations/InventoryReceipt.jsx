import { Link } from 'react-router-dom'
import { formatUnitDate } from '../units/unitErrors'
import { formatQuantity } from './formatQuantity'
export default function InventoryReceipt({ movement }) {
  return <section className="success-message" aria-label="Movimiento confirmado">
    <h2>Movimiento confirmado</h2><dl className="unit-detail">
      <div><dt>Folio</dt><dd>{movement.folio}</dd></div><div><dt>Responsable</dt><dd>{movement.performed_by?.name || 'Usuario no disponible'}</dd></div>
      <div><dt>Fecha</dt><dd>{formatUnitDate(movement.occurred_at)}</dd></div>
      <div><dt>Componente</dt><dd>{movement.material.code} · {movement.material.name}</dd></div>
      <div><dt>Cantidad original</dt><dd>{formatQuantity(movement.quantity_original)} {movement.unit_original}</dd></div>
      <div><dt>Cantidad equivalente</dt><dd>{formatQuantity(movement.quantity_equivalent)} {movement.material.unit}</dd></div>
      <div><dt>Stock antes</dt><dd>{formatQuantity(movement.stock_before)}</dd></div><div><dt>Stock resultante</dt><dd>{formatQuantity(movement.stock_after)}</dd></div>
    </dl><Link to="/materials/inventory/dashboard">Consultar dashboard actualizado</Link>
  </section>
}
