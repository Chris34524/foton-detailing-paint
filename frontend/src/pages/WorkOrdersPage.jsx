import { useCallback } from 'react'
import { Link, NavLink, useSearchParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import { formatUnitDate } from '../units/unitErrors'
import useData from '../diagnosis/useDiagnosisData'
import Pagination from '../diagnosis/Pagination'
import workOrderService from '../workOrders/workOrderService'
import WorkOrderError from '../workOrders/WorkOrderError'

export default function WorkOrdersPage({ queue = false }) {
  const { user } = useAuth()
  const [params, setParams] = useSearchParams()
  const query = params.toString()
  const loadMeta = useCallback((signal) => workOrderService.meta({ signal }), [])
  const metadata = useData('work-order-meta', loadMeta)
  const load = useCallback((signal) => workOrderService.list(Object.fromEntries(new URLSearchParams(query)), queue, { signal }), [query, queue])
  const collection = useData('work-orders:' + queue + '?' + query, load)
  function filter(event) {
    event.preventDefault()
    const next = new URLSearchParams()
    for (const [key, value] of new FormData(event.currentTarget)) {
      if (value.trim()) next.set(key, value.trim())
    }
    setParams(next)
  }
  function quickFilter(assignment, mine = false) {
    const next = new URLSearchParams(params)
    next.delete('page'); next.delete('assignment'); next.delete('assigned_to')
    if (assignment) next.set('assignment', assignment)
    if (mine) next.set('assigned_to', String(user.id))
    setParams(next)
  }
  function page(value) {
    const next = new URLSearchParams(params)
    next.set('page', value)
    setParams(next)
  }
  if (metadata.error || collection.error) return <WorkOrderError error={metadata.error || collection.error} retry={() => { metadata.retry(); collection.retry() }} />
  return <section className="workspace-card">
    <p className="eyebrow">TRABAJOS</p><h1>{queue ? 'Cola de trabajos' : 'Órdenes de trabajo'}</h1>
    <nav className="module-navigation" aria-label="Navegación de trabajos">
      <NavLink to="/work-orders" end>Listado</NavLink><NavLink to="/work-orders/queue">Cola de trabajos</NavLink>
    </nav>
    {queue && <p className="muted">Trabajos activos por prioridad; dentro de cada prioridad, los más antiguos primero.</p>}
    <form key={query} className="work-order-filters" onSubmit={filter}>
      <div><label htmlFor="work-search">Buscar trabajos</label><input id="work-search" name="search" maxLength={255} defaultValue={params.get('search') || ''} placeholder="VIN, modelo o notas" /></div>
      <div><label htmlFor="work-status">Estado</label><select id="work-status" name="status" defaultValue={params.get('status') || ''}>
        <option value="">Todos{queue ? ' activos' : ''}</option>{metadata.data?.statuses.filter((status) => !queue || !status.is_terminal).map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}</select></div>
      <div><label htmlFor="work-priority">Prioridad</label><select id="work-priority" name="priority" defaultValue={params.get('priority') || ''}>
        <option value="">Todas</option>{metadata.data?.priorities.map((priority) => <option key={priority.value} value={priority.value}>{priority.label}</option>)}</select></div>
      <div><label htmlFor="work-assignment">Asignación</label><select id="work-assignment" name="assignment" defaultValue={params.get('assignment') || ''}>
        <option value="">Todas</option><option value="unassigned">Sin asignar</option><option value="assigned">Asignados</option></select></div>
      {params.get('assigned_to') && <input type="hidden" name="assigned_to" value={params.get('assigned_to')} />}
      {!queue && <div><label htmlFor="work-sort">Ordenar por</label><select id="work-sort" name="sort" defaultValue={params.get('sort') || 'created_at'}>
        <option value="created_at">Fecha de creación</option><option value="updated_at">Última actualización</option><option value="priority">Prioridad</option><option value="status">Estado</option></select>
        <label htmlFor="work-direction">Dirección</label><select id="work-direction" name="direction" defaultValue={params.get('direction') || 'desc'}>
          <option value="desc">Descendente</option><option value="asc">Ascendente</option></select></div>}
      <div className="form-actions"><button className="primary-button">Aplicar filtros</button><button type="button" onClick={() => setParams({})}>Limpiar filtros</button></div>
    </form>
    <div className="form-actions queue-shortcuts" aria-label="Filtros rápidos">
      <button type="button" aria-pressed={!params.get("assignment") && !params.get("assigned_to")} onClick={() => quickFilter()}>Todos{queue ? ' activos' : ''}</button>
      <button type="button" aria-pressed={params.get("assignment") === "unassigned" && !params.get("assigned_to")} onClick={() => quickFilter('unassigned')}>Sin asignar</button><button type="button" aria-pressed={params.get("assignment") === "assigned" && !params.get("assigned_to")} onClick={() => quickFilter('assigned')}>Asignados</button>
      <button type="button" aria-pressed={params.get("assigned_to") === String(user.id)} onClick={() => quickFilter(null, true)}>Mis trabajos</button>
    </div>
    {(metadata.isLoading || collection.isLoading) && <p role="status">Cargando trabajos…</p>}
    {collection.data && <>
      {collection.data.data.length === 0 ? <div className="empty-state"><h2>No hay trabajos para mostrar</h2><p>Ajusta los filtros o genera una orden desde un diagnóstico con defectos.</p></div>
        : <div className="table-scroll" tabIndex={0} role="region" aria-label="Tabla de registros"><table><caption className="sr-only">{queue ? 'Cola de trabajos activos' : 'Listado de órdenes de trabajo'}</caption>
          <thead><tr><th>ID</th><th>VIN</th><th>Modelo</th><th>Prioridad</th><th>Estado</th><th>Operador</th><th>Fecha de creación</th><th>Detalle</th></tr></thead>
          <tbody>{collection.data.data.map((order) => <tr key={order.id}>
            <td>{order.id}</td><td className="vin-value">{order.unit.vin}</td><td>{order.unit.model || '—'}</td>
            <td><span className="priority-badge" data-priority={order.priority}>{order.priority_label}</span></td>
            <td><span className="status-badge" data-status={order.status}>{order.status_label}</span></td>
            <td>{order.assigned_to?.name || 'Sin asignar'}</td><td>{formatUnitDate(order.created_at)}</td>
            <td><Link to={'/work-orders/' + order.id} aria-label={'Ver orden ' + order.id}>Ver detalle</Link></td>
          </tr>)}</tbody></table></div>}
      <Pagination meta={collection.data.meta} onPage={page} label="Paginación de trabajos" />
    </>}
  </section>
}
