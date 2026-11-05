import { useCallback } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import { operationsPermissions as permissions } from '../operations/permissions'
import useData from '../diagnosis/useDiagnosisData'
import Pagination from '../diagnosis/Pagination'
import { formatUnitDate } from '../units/unitErrors'
import inventoryService from '../operations/inventoryService'
import InventoryNavigation from '../operations/InventoryNavigation'
import useInventoryRefresh from '../operations/useInventoryRefresh'
import { LoadState } from '../operations/Feedback'
function Filters({ params, meta, onSearch, onClear }) {
  return <form className="inventory-filters" onSubmit={(event) => {
    event.preventDefault()
    const next = new URLSearchParams()
    for (const [name, value] of new FormData(event.currentTarget)) if (value.trim()) next.set(name, value.trim())
    if (params.get('material_id')) next.set('material_id', params.get('material_id'))
    onSearch(next)
  }}>
    <div><label htmlFor="movement-search">Buscar código, componente o folio</label><input id="movement-search" name="search" defaultValue={params.get('search') || ''} maxLength={255} /></div>
    <div><label htmlFor="filter-movement-type">Tipo de movimiento</label><select id="filter-movement-type" name="movement_type" defaultValue={params.get('movement_type') || ''}>
      <option value="">Todos</option>{meta.movement_types.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}</select></div>
    <div><label htmlFor="filter-area">Área / destino</label><select id="filter-area" name="area_destination" defaultValue={params.get('area_destination') || ''}>
      <option value="">Todos</option>{meta.areas.map((area) => <option key={area} value={area}>{area}</option>)}</select></div>
    <div><label htmlFor="date-from">Desde</label><input id="date-from" name="date_from" type="date" defaultValue={params.get('date_from') || ''} /></div>
    <div><label htmlFor="date-to">Hasta</label><input id="date-to" name="date_to" type="date" defaultValue={params.get('date_to') || ''} /></div>
    <div className="form-actions"><button>Aplicar filtros</button><button type="button" onClick={onClear}>Limpiar filtros</button></div>
  </form>
}
export default function InventoryMovementsPage() {
  const { canAny } = useAuth()
  const [params, setParams] = useSearchParams()
  const query = params.toString()
  const materialId = params.get('material_id')
  const loader = useCallback(async (signal) => {
    const [collection, meta] = await Promise.all([inventoryService.movements(Object.fromEntries(new URLSearchParams(query)), { signal }),
      inventoryService.meta({ signal })])
    return { collection, meta }
  }, [query])
  const resource = useData('inventory-movements:' + query, loader)
  useInventoryRefresh(resource.retry)
  return <section className="workspace-card"><div className="section-heading"><h1>Movimientos de inventario</h1>
    {canAny([permissions.materialsManage, permissions.materialsConsume]) && <Link className="button-link" to={'/materials/movements/new' + (materialId ? '?material_id=' + materialId : '')}>Registrar movimiento</Link>}</div>
    <InventoryNavigation /><LoadState resource={resource}>{resource.data && <>
      <Filters key={query} params={params} meta={resource.data.meta} onSearch={setParams} onClear={() => setParams({})} />
      {resource.data.collection.data.length === 0 ? <p className="empty-state">No hay movimientos para mostrar.</p> : <div className="table-scroll" tabIndex={0} role="region" aria-label="Tabla de registros"><table>
        <caption className="sr-only">Historial de movimientos</caption><thead><tr><th>Fecha</th><th>Folio</th><th>Código</th><th>Componente</th><th>Tipo</th><th>Cantidad</th><th>Unidad movimiento</th><th>Cantidad equivalente</th><th>Responsable</th><th>Área / destino</th><th>Observaciones</th></tr></thead>
        <tbody>{resource.data.collection.data.map((movement) => <tr key={movement.id}>
          <td>{formatUnitDate(movement.occurred_at)}</td><td><Link to={'/materials/movements/' + movement.id}>{movement.folio}</Link></td>
          <td>{movement.material.code}</td><td>{movement.material.name}</td><td>{movement.movement_type_label}</td><td>{movement.quantity_original}</td><td>{movement.unit_original}</td>
          <td>{movement.quantity_equivalent} {movement.material.unit}</td><td>{movement.performed_by?.name || 'Usuario no disponible'}</td><td>{movement.area_destination || '—'}</td><td>{movement.notes || '—'}</td>
        </tr>)}</tbody></table></div>}
      <Pagination meta={resource.data.collection.meta} label="Paginación de movimientos" onPage={(page) => { const next = new URLSearchParams(params); next.set('page', String(page)); setParams(next) }} />
    </>}</LoadState>
  </section>
}
