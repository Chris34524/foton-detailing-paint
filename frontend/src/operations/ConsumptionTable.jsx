import { formatUnitDate } from '../units/unitErrors'
import { formatQuantity } from './formatQuantity'
export default function ConsumptionTable({ data }) {
  if (!data.length) return <p className="empty-state">No hay consumos registrados.</p>
  return <div className="table-scroll" tabIndex={0} role="region" aria-label="Tabla de registros"><table><caption className="sr-only">Consumos de materiales</caption>
    <thead><tr><th>VIN</th><th>Orden</th><th>Material</th><th>Cantidad equivalente</th><th>Folio de inventario</th><th>Proceso</th><th>Registrado por</th><th>Fecha</th></tr></thead>
    <tbody>{data.map((item) => <tr key={item.id}><td className="vin-value">{item.unit.vin}</td><td>#{item.work_order_id}</td>
      <td>{item.material.code} · {item.material.name}</td><td>{formatQuantity(item.quantity)} {item.material.unit}</td><td>{item.inventory_movement?.folio || "Anterior al control de inventario"}</td>
      <td>{item.process_step ? item.process_step.code + ' · ' + item.process_step.name : 'Consumo general'}</td>
      <td>{item.consumed_by?.name || 'Usuario no disponible'}</td><td>{formatUnitDate(item.consumed_at)}</td></tr>)}</tbody></table></div>
}
