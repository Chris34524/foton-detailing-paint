import { useCallback, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import { formatUnitDate } from '../units/unitErrors'
import { diagnosisPermissions } from '../diagnosis/permissions'
import useData from '../diagnosis/useDiagnosisData'
import DefectCard from '../diagnosis/DefectCard'
import workOrderService from '../workOrders/workOrderService'
import { workOrderPermissions } from '../workOrders/permissions'
import WorkOrderError from '../workOrders/WorkOrderError'
import WorkOrderActions from '../workOrders/WorkOrderActions'

export default function WorkOrderDetailPage() {
  const { workOrderId } = useParams()
  const [saved, setSaved] = useState(false)
  const { can } = useAuth()
  const canUpdate = can(workOrderPermissions.update)
  const canAssign = can(workOrderPermissions.assign)
  const loader = useCallback(async (signal) => {
    const [order, meta] = await Promise.all([
      workOrderService.get(workOrderId, { signal }),
      canUpdate ? workOrderService.meta({ signal }) : Promise.resolve({ priorities: [] }),
    ])
    return { order, meta }
  }, [workOrderId, canUpdate])
  const { data, error, isLoading, retry, setData } = useData('work-order:' + workOrderId + ':' + canUpdate, loader)
  if (isLoading) return <p role="status">Cargando orden de trabajo…</p>
  if (error) return <WorkOrderError error={error} retry={retry} />
  const { order, meta } = data
  async function reload() {
    const current = await workOrderService.get(workOrderId)
    setData((previous) => ({ ...previous, order: current }))
    setSaved(true)
  }
  const formKey = JSON.stringify([order.id, order.updated_at, order.priority, order.notes, order.status, order.assigned_to?.id])
  return <section className="workspace-card">
    <Link to="/work-orders">← Volver a Trabajos</Link>
    <div className="section-heading page-title"><h1>Orden de trabajo #{order.id}</h1><Link to="/work-orders/queue">Cola de trabajos</Link></div>
    <dl className="unit-detail">
      <div><dt>VIN</dt><dd className="vin-value">{order.unit.vin}</dd></div>
      <div><dt>Modelo</dt><dd>{order.unit.model || '—'}</dd></div>
      <div><dt>Estado</dt><dd><span className="status-badge" data-status={order.status}>{order.status_label}</span></dd></div>
      <div><dt>Prioridad</dt><dd><span className="priority-badge" data-priority={order.priority}>{order.priority_label}</span></dd></div>
      <div><dt>Operador asignado</dt><dd>{order.assigned_to?.name || 'Sin asignar'}</dd></div>
      <div><dt>Asignado por</dt><dd>{order.assigned_by?.name || '—'}</dd></div>
      <div><dt>Fecha de asignación</dt><dd>{order.assigned_at ? formatUnitDate(order.assigned_at) : '—'}</dd></div>
      <div><dt>Creado por</dt><dd>{order.created_by?.name || 'Usuario no disponible'}</dd></div>
      <div><dt>Fecha de creación</dt><dd>{formatUnitDate(order.created_at)}</dd></div>
    </dl>
    <h2>Notas de la orden</h2><p className="plain-text">{order.notes || 'Sin notas.'}</p>
    {saved && <p className="success-message" role="status">Orden actualizada.</p>}
    <WorkOrderActions key={formKey} order={order} priorities={meta.priorities} canUpdate={canUpdate} canAssign={canAssign} onSaved={reload} />
    <section aria-label="Diagnóstico fuente" className="source-diagnosis">
      <div className="section-heading"><h2>Diagnóstico fuente #{order.diagnosis.id}</h2>
        {can(diagnosisPermissions.view) && <Link to={'/diagnoses/' + order.diagnosis.id}>Ver diagnóstico</Link>}</div>
      <p>Diagnosticado por {order.diagnosis.diagnosed_by?.name || 'Usuario no disponible'} · {formatUnitDate(order.diagnosis.created_at)}</p>
      <p className="plain-text">{order.diagnosis.notes || 'Sin notas generales.'}</p>
      <h3>Defectos ({order.diagnosis.defect_count})</h3>
      <div className="defect-list">{order.diagnosis.defects.map((defect) => <DefectCard key={defect.id} diagnosisId={order.diagnosis.id} defect={defect} canEdit={false} />)}</div>
    </section>
  </section>
}
