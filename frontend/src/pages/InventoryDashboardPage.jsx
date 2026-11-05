import { useCallback } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import { operationsPermissions as permissions } from '../operations/permissions'
import useData from '../diagnosis/useDiagnosisData'
import Pagination from '../diagnosis/Pagination'
import inventoryService from '../operations/inventoryService'
import { formatQuantity } from '../operations/formatQuantity'
import InventoryNavigation from '../operations/InventoryNavigation'
import useInventoryRefresh from '../operations/useInventoryRefresh'
import { LoadState } from '../operations/Feedback'
export default function InventoryDashboardPage() {
  const { canAny } = useAuth()
  const [params, setParams] = useSearchParams()
  const query = params.toString()
  const loader = useCallback(async (signal) => {
    const [dashboard, meta] = await Promise.all([inventoryService.dashboard(Object.fromEntries(new URLSearchParams(query)), { signal }), inventoryService.meta({ signal })])
    return { dashboard, meta }
  }, [query])
  const resource = useData('inventory-dashboard:' + query, loader)
  useInventoryRefresh(resource.retry)
  return <section className="workspace-card"><div className="section-heading"><h1>Dashboard de inventario</h1>
    {canAny([permissions.materialsManage, permissions.materialsConsume]) && <Link className="button-link" to="/materials/movements/new">Registrar movimiento</Link>}</div>
    <InventoryNavigation /><LoadState resource={resource}>{resource.data && <>
      <div className="inventory-indicators">
        <article><span>Total componentes</span><strong>{resource.data.dashboard.indicators.total_components}</strong></article>
        {resource.data.meta.stock_statuses.map((status) => <article key={status.value} data-stock-status={status.value}><span>{status.label}</span><strong>{resource.data.dashboard.indicators[status.value] || 0}</strong></article>)}
      </div>
      <h2>Alertas y acciones recomendadas</h2>
      {resource.data.dashboard.alerts.data.length === 0 ? <p className="empty-state">No hay alertas de inventario.</p> : <div className="table-scroll" tabIndex={0} role="region" aria-label="Tabla de registros"><table>
        <caption className="sr-only">Alertas de inventario</caption><thead><tr><th>Código</th><th>Componente</th><th>Ubicación</th><th>Stock actual</th><th>Mínimo</th><th>Máximo</th><th>Estado</th><th>Acción recomendada</th></tr></thead>
        <tbody>{resource.data.dashboard.alerts.data.map((material) => <tr key={material.id}>
          <td>{material.code}</td><td><Link to={'/materials/movements?material_id=' + material.id}>{material.name}</Link></td><td>{material.location || '—'}</td>
          <td>{formatQuantity(material.stock_actual)} {material.unit}</td><td>{formatQuantity(material.minimum_stock)}</td><td>{formatQuantity(material.maximum_stock)}</td>
          <td><span className="stock-badge" data-stock-status={material.stock_status}>{material.stock_status_label}</span></td><td>{material.recommended_action}</td>
        </tr>)}</tbody></table></div>}
      <Pagination meta={resource.data.dashboard.alerts.meta} label="Paginación de alertas" onPage={(page) => setParams({ page: String(page) })} />
    </>}</LoadState>
  </section>
}
