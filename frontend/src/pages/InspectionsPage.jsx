import { useCallback } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import useData from '../diagnosis/useDiagnosisData'
import Pagination from '../diagnosis/Pagination'
import { formatUnitDate } from '../units/unitErrors'
import operationsService from '../operations/operationsService'
import { operationsPermissions as permissions } from '../operations/permissions'
import { LoadState } from '../operations/Feedback'
export default function InspectionsPage() {
  const { workOrderId } = useParams()
  const { can } = useAuth()
  const [params, setParams] = useSearchParams()
  const query = params.toString()
  const loader = useCallback((signal) => operationsService.inspections(Object.fromEntries(new URLSearchParams(query)), workOrderId || null, { signal }), [query, workOrderId])
  const resource = useData('inspections:' + (workOrderId || '') + ':' + query, loader)
  return <section className="workspace-card"><div className="section-heading"><h1>{workOrderId ? 'Inspecciones de la orden #' + workOrderId : 'Inspecciones'}</h1>
    <div className="form-actions">{can(permissions.inspectionManage) && <Link to="/inspection/checklists">Configurar checklists</Link>}
      {can(permissions.inspectionExecute) && <Link className="button-link" to={workOrderId ? '/work-orders/' + workOrderId + '/inspections/new' : '/inspections/new'}>Nueva inspección</Link>}</div></div>
    <form key={query} className="diagnosis-search" onSubmit={(event) => {
      event.preventDefault(); const search = new FormData(event.currentTarget).get('search').trim(); setParams(search ? { search } : {})
    }}>
      <div><label htmlFor="inspection-search">Buscar inspecciones</label><input id="inspection-search" name="search" maxLength={255} defaultValue={params.get('search') || ''} placeholder="VIN, modelo, checklist o notas" /></div>
      <button>Buscar</button><button type="button" onClick={() => setParams({})}>Limpiar búsqueda</button>
    </form>
    <LoadState resource={resource}>{resource.data && <>
      {resource.data.data.length === 0 ? <p className="empty-state">No hay inspecciones para mostrar.</p> : <div className="table-scroll"><table>
        <caption className="sr-only">Listado de inspecciones</caption><thead><tr><th>ID</th><th>VIN</th><th>Orden</th><th>Checklist</th><th>Estado / Resultado</th><th>Inspector</th><th>Fecha</th><th>Detalle</th></tr></thead>
        <tbody>{resource.data.data.map((inspection) => <tr key={inspection.id}><td>{inspection.id}</td><td className="vin-value">{inspection.unit.vin}</td><td>#{inspection.work_order_id}</td>
          <td>{inspection.checklist.name}</td><td>{inspection.result_label || inspection.status_label}</td><td>{inspection.inspected_by?.name || 'Usuario no disponible'}</td>
          <td>{formatUnitDate(inspection.created_at)}</td><td><Link to={'/inspections/' + inspection.id} aria-label={'Ver inspección ' + inspection.id}>Ver detalle</Link></td>
        </tr>)}</tbody></table></div>}
      <Pagination meta={resource.data.meta} label="Paginación de inspecciones" onPage={(page) => { const next = new URLSearchParams(params); next.set('page', page); setParams(next) }} />
    </>}</LoadState>
  </section>
}
