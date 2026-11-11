import { Link } from 'react-router-dom'
import { formatQuantity } from './formatQuantity'
export default function InventoryTable({ data, canManage, canMove, onEdit }) {
  return <div className="table-scroll inventory-table-scroll" tabIndex={0} role="region" aria-label="Inventario de materiales"><table className="inventory-table"><caption className="sr-only">Inventario de materiales</caption>
    <thead><tr><th>Código</th><th>Componente</th><th>Unidad</th><th>Stock actual</th><th>Mínimo</th><th>Máximo</th><th>Estado</th><th>% de nivel</th><th>Ubicación</th><th>Proveedor</th><th>Acciones</th></tr></thead>
    <tbody>{data.map((material) => <tr key={material.id}>
      <td>{material.code}</td><td>{material.name}{material.kind === 'finished_paint' && <span className="material-kind">Pintura terminada</span>}{!material.is_active && <span className="inactive-badge">Inactivo</span>}</td><td>{material.unit}</td>
      <td>{formatQuantity(material.stock_actual ?? '0.000000')}</td><td>{formatQuantity(material.minimum_stock)}</td><td>{formatQuantity(material.maximum_stock)}</td>
      <td><span className="stock-badge" data-stock-status={material.stock_status}>{material.stock_status_label || 'SIN PARÁMETROS'}</span></td>
      <td>{material.stock_percentage == null ? '—' : formatQuantity(material.stock_percentage) + '%'}</td><td>{material.location || '—'}</td><td>{material.supplier || '—'}</td>
      <td><div className="inventory-row-actions">
        {canManage && material.kind !== 'finished_paint' && <button onClick={() => onEdit(material)} aria-label={'Editar ' + material.name}>Editar</button>}
        {material.is_active && (material.kind === 'finished_paint' ? canManage : canMove) && (material.kind === 'finished_paint'
          ? <Link to={'/materials/paint-formulas?product_id=' + material.id}>Preparar pintura</Link>
          : <Link to={'/materials/movements/new?material_id=' + material.id}>Registrar movimiento</Link>)}
        <Link to={'/materials/movements?material_id=' + material.id}>Ver movimientos</Link>
      </div></td>
    </tr>)}</tbody>
  </table></div>
}
