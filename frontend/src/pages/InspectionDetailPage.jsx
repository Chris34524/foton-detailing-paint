import { useCallback, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import useData from '../diagnosis/useDiagnosisData'
import { formatUnitDate } from '../units/unitErrors'
import operationsService from '../operations/operationsService'
import { operationsPermissions as permissions } from '../operations/permissions'
import { ActionError, LoadState } from '../operations/Feedback'
import InspectionExecution from '../operations/InspectionExecution'
const checkLabels = { inspection_completed: 'Inspección completada', inspection_passed: 'Inspección aprobada', latest_completed_inspection: 'Última inspección completada',
  work_order_completed: 'Orden de trabajo completada', no_open_rework: 'Sin retrabajos abiertos', not_released: 'Sin liberación previa' }
function ReleasePanel({ inspection, onSaved }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const allowed = Object.values(inspection.release_checks).every(Boolean)
  async function submit(event) {
    event.preventDefault()
    const notes = new FormData(event.currentTarget).get('notes').trim()
    setBusy(true); setError(null)
    try { await operationsService.release(inspection.id, { notes: notes || null }); await onSaved() }
    catch (failure) { setError(failure) }
    finally { setBusy(false) }
  }
  return <section className="operations-section"><h2>Validación de liberación</h2>
    <ul>{Object.entries(inspection.release_checks).map(([key, value]) => <li key={key}>{checkLabels[key]}: <strong>{value ? 'Cumple' : 'No cumple'}</strong></li>)}</ul>
    <form className="operations-form" onSubmit={submit}><label htmlFor="release-notes">Notas de liberación</label><textarea id="release-notes" name="notes" maxLength={5000} disabled={busy} />
      <ActionError error={error} /><button className="primary-button" disabled={busy || !allowed}>Liberar unidad</button></form>
  </section>
}
export default function InspectionDetailPage() {
  const { inspectionId } = useParams()
  const { can } = useAuth()
  const loader = useCallback((signal) => operationsService.inspection(inspectionId, { signal }), [inspectionId])
  const resource = useData('inspection:' + inspectionId, loader)
  async function reload() { const current = await operationsService.inspection(inspectionId); resource.setData(current) }
  const inspection = resource.data
  return <section className="workspace-card"><Link to="/inspections">← Inspección</Link><h1>Detalle de inspección</h1>
    <LoadState resource={resource}>{inspection && <>
      <h2>Inspección #{inspection.id} · {inspection.result_label || inspection.status_label}</h2>
      <dl className="unit-detail">
        <div><dt>VIN</dt><dd className="vin-value">{inspection.unit.vin}</dd></div><div><dt>Modelo</dt><dd>{inspection.unit.model || '—'}</dd></div>
        <div><dt>Orden de trabajo</dt><dd>#{inspection.work_order_id}</dd></div><div><dt>Checklist</dt><dd>{inspection.checklist.name}</dd></div>
        <div><dt>Inspector</dt><dd>{inspection.inspected_by?.name || 'Usuario no disponible'}</dd></div>
        <div><dt>Estado de la unidad</dt><dd>{inspection.unit.status_label}</dd></div>
      </dl>
      {inspection.status === 'draft' && can(permissions.inspectionExecute)
        ? <InspectionExecution key={JSON.stringify(inspection.items) + inspection.updated_at} inspection={inspection} onSaved={reload} />
        : <><p className="plain-text">{inspection.notes || 'Sin notas.'}</p><ul className="inspection-results">{inspection.items.map((item) => <li key={item.id}>
          <strong>{item.item_code_snapshot} · {item.item_label_snapshot}</strong> · {item.result_label || 'Pendiente'}
          <p>{item.item_description_snapshot}</p><p className="plain-text">{item.comment}</p>
        </li>)}</ul></>}
      {inspection.rework && <section className="operations-section"><h2>Retrabajo generado automáticamente</h2>
        <p>Retrabajo #{inspection.rework.id} · {inspection.rework.status_label}</p><p className="plain-text">{inspection.rework.cause}</p><h3>Criterios que no cumplen</h3>
        <ul>{inspection.items.filter((item) => item.result === 'non_compliant').map((item) => <li key={item.id}>{item.item_code_snapshot} · {item.item_label_snapshot}</li>)}</ul>
      </section>}
      {inspection.unit.status === 'ready_for_release' && <p className="success-message">Unidad lista para liberación.</p>}
      {inspection.release ? <section className="success-message"><h2>Unidad liberada</h2><p>Liberación #{inspection.release.id} · {inspection.release.released_by?.name || 'Usuario no disponible'} · {formatUnitDate(inspection.release.released_at)}</p></section>
        : can(permissions.release) && inspection.status === 'completed' && <ReleasePanel key={inspection.id} inspection={inspection} onSaved={reload} />}
    </>}</LoadState>
  </section>
}
