import { useCallback, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import operationsService from '../operations/operationsService'
import CatalogPicker from '../operations/CatalogPicker'
import useData from '../diagnosis/useDiagnosisData'
import { ActionError, LoadState } from '../operations/Feedback'
export default function InspectionCreatePage() {
  const { workOrderId } = useParams()
  const navigate = useNavigate()
  const loader = useCallback((signal) => workOrderId ? operationsService.eligibleOrders({ work_order_id: workOrderId }, { signal }) : Promise.resolve(null), [workOrderId])
  const source = useData('inspection-source:' + (workOrderId || ''), loader)
  const [order, setOrder] = useState(workOrderId ? { id: Number(workOrderId) } : null)
  const [checklist, setChecklist] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const selectedOrder = workOrderId ? source.data?.data[0] : order
  async function submit(event) {
    event.preventDefault()
    const notes = new FormData(event.currentTarget).get('notes').trim()
    setBusy(true); setError(null)
    try {
      const inspection = await operationsService.createInspection(selectedOrder.id, { inspection_checklist_id: checklist.id, notes: notes || null })
      navigate('/inspections/' + inspection.id)
    } catch (failure) { setError(failure) }
    finally { setBusy(false) }
  }
  return <section className="workspace-card"><Link to="/inspections">← Inspección</Link><h1>Nueva inspección</h1>
    <p>Selecciona una orden completada y un checklist activo. Los criterios se conservarán como copia histórica.</p>
    <form className="operations-form" onSubmit={submit}><fieldset disabled={busy}>
      {workOrderId ? <LoadState resource={source}>{selectedOrder ? <p>Orden #{selectedOrder.id} · <span className="vin-value">{selectedOrder.vin}</span> · {selectedOrder.model}</p> : <p>La orden no está disponible para inspección. Debe estar completada.</p>}</LoadState> : <CatalogPicker kind="orders" label="Orden completada" value={order?.id} selected={order} onChange={(value, record) => setOrder(value ? record : null)} />}
      <CatalogPicker kind="checklists" label="Checklist" value={checklist?.id} selected={checklist} onChange={(value, record) => setChecklist(value ? record : null)} />
      <label htmlFor="inspection-notes">Notas de inspección</label><textarea id="inspection-notes" name="notes" maxLength={5000} />
    </fieldset><ActionError error={error} /><button className="primary-button" disabled={busy || !selectedOrder || !checklist}>Crear inspección</button></form>
  </section>
}
