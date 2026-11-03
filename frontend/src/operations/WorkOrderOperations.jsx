import { useCallback, useState } from 'react'
import { Link } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import useData from '../diagnosis/useDiagnosisData'
import Pagination from '../diagnosis/Pagination'
import operationsService from './operationsService'
import { operationsPermissions as permissions } from './permissions'
import { ActionError, LoadState } from './Feedback'
import InventoryConsumptionForm from './InventoryConsumptionForm'
import InventoryReceipt from './InventoryReceipt'
import ConsumptionTable from './ConsumptionTable'

function MaterialsCard({ order, flow }) {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [adding, setAdding] = useState(false)
  const [receipt, setReceipt] = useState(null)
  const loader = useCallback((signal) => can(permissions.materialsView) ? operationsService.consumptions({ page }, order.id, { signal }) : Promise.resolve(null), [can, page, order.id])
  const resource = useData('order-consumptions:' + order.id + ':' + page, loader)
  return <section className="operations-section"><div className="section-heading"><h2>Materiales consumidos</h2>
    {!order.is_terminal && can(permissions.materialsConsume) && !adding && <button onClick={() => setAdding(true)}>Registrar consumo</button>}</div>
    {adding && <InventoryConsumptionForm order={order} flow={flow} onCancel={() => setAdding(false)} onSaved={(consumption) => { setReceipt(consumption.inventory_movement || null); setAdding(false); resource.retry() }} />}
    {receipt && <InventoryReceipt movement={receipt} />}
    <LoadState resource={resource}>{resource.data && <><ConsumptionTable data={resource.data.data} />
      <Pagination meta={resource.data.meta} label="Paginación de consumos de la orden" onPage={setPage} /></>}</LoadState>
  </section>
}
function InspectionHistory({ order }) {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const loader = useCallback((signal) => operationsService.inspections({ page }, order.id, { signal }), [order.id, page])
  const resource = useData('order-inspections:' + order.id + ':' + page, loader)
  return <section className="operations-section"><h2>Inspecciones y retrabajos</h2>
    <p><Link to={'/work-orders/' + order.id + '/inspections'}>Ver historial de inspecciones</Link></p>
    {can(permissions.inspectionExecute) && order.status === 'completed' && <Link className="button-link" to={'/work-orders/' + order.id + '/inspections/new'}>Nueva inspección</Link>}
    <LoadState resource={resource}>{resource.data && <>
      {resource.data.data.length === 0 && <p>No hay inspecciones registradas.</p>}
      <ul>{resource.data.data.map((inspection) => <li key={inspection.id}>
        <Link to={'/inspections/' + inspection.id}>Inspección #{inspection.id}</Link> · {inspection.result_label || inspection.status_label}
        {inspection.rework && <p>Retrabajo #{inspection.rework.id} · {inspection.rework.status_label} · {inspection.rework.cause}</p>}
      </li>)}</ul>
      <Pagination meta={resource.data.meta} label="Paginación de inspecciones de la orden" onPage={setPage} />
    </>}</LoadState>
  </section>
}
export default function WorkOrderOperations({ order }) {
  const { can } = useAuth()
  const loader = useCallback((signal) => operationsService.flow(order.id, { signal }), [order.id])
  const resource = useData('order-flow:' + order.id, loader)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  async function initialize() {
    setBusy(true); setError(null)
    try { await operationsService.initialize(order.id); resource.retry() }
    catch (failure) { setError(failure) }
    finally { setBusy(false) }
  }
  return <div className="order-operations">
    <section className="operations-section"><h2>Flujo de procesos</h2><LoadState resource={resource}>{resource.data && <>
      {!resource.data.initialized ? <><p className="empty-state">Secuencia de procesos no inicializada.</p>
        {can(permissions.processesManage) && <button disabled={busy} onClick={initialize}>Inicializar secuencia</button>}</>
        : <><dl className="unit-detail">
          <div><dt>Proceso actual</dt><dd>{resource.data.current ? resource.data.current.code + ' · ' + resource.data.current.name : 'Sin proceso actual'}</dd></div>
          <div><dt>Siguiente proceso</dt><dd>{resource.data.next ? resource.data.next.code + ' · ' + resource.data.next.name : 'Sin siguiente proceso'}</dd></div>
        </dl><ol>{resource.data.steps.map((step) => <li key={step.step_id}>{step.code} · {step.name}</li>)}</ol></>}
    </>}</LoadState><ActionError error={error} /></section>
    {(can(permissions.materialsView) || can(permissions.materialsConsume)) && <MaterialsCard order={order} flow={resource.data} />}
    {can(permissions.inspectionView) && <InspectionHistory order={order} />}
  </div>
}
