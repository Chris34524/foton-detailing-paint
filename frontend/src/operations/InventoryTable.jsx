import { Link } from 'react-router-dom'
import InventoryStockDetails from './InventoryStockDetails'
export default function InventoryTable({ data, canManage, canMove, onEdit }) {
  return <div className="table-scroll inventory-table-scroll" tabIndex={0} role="region" aria-label="Inventario de materiales"><table className="inventory-table"><caption className="sr-only">Inventario de materiales</caption>
    <thead><tr><th>Código</th><th>Componente</th><th>Unidad</th><th>Stock actual</th><th>Mínimo</th><th>Máximo</th><th>Estado</th><th>% de nivel</th><th>Ubicación</th><th>Proveedor</th><th>Acciones</th></tr></thead>
    <tbody>{data.map((material) => <tr key={material.id}>
      <td>{material.code}</td><td>{material.name}{!material.is_active && <span className="inactive-badge">Inactivo</span>}<InventoryStockDetails material={material} /></td><td>{material.unit}</td>
      <td>{material.stock_actual ?? '0.000000'}</td><td>{material.minimum_stock ?? '—'}</td><td>{material.maximum_stock ?? '—'}</td>
      <td><span className="stock-badge" data-stock-status={material.stock_status}>{material.stock_status_label || 'SIN PARÁMETROS'}</span></td>
      <td>{material.stock_percentage == null ? '—' : material.stock_percentage + '%'}</td><td>{material.location || '—'}</td><td>{material.supplier || '—'}</td>
      <td><div className="inventory-row-actions">
        {canManage && <button onClick={() => onEdit(material)} aria-label={'Editar ' + material.name}>Editar</button>}
        {canMove && material.is_active && <Link to={'/materials/movements/new?material_id=' + material.id}>Registrar movimiento</Link>}
        <Link to={'/materials/movements?material_id=' + material.id}>Ver movimientos</Link>
      </div></td>
    </tr>)}</tbody>
  </table></div>
}
