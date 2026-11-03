import { useCallback, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import { diagnosisError, validationErrors } from '../diagnosis/diagnosisErrors'
import FieldError from '../diagnosis/FieldError'
import diagnosisService from '../diagnosis/diagnosisService'
import useData from '../diagnosis/useDiagnosisData'
import workOrderService from './workOrderService'
import { workOrderPermissions } from './permissions'

function GenerationForm({ diagnosis, onCancel }) {
  const navigate = useNavigate()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  async function generate(event) {
    event.preventDefault()
    const notes = new FormData(event.currentTarget).get('notes').trim()
    setBusy(true)
    setError(null)
    try {
      const order = await workOrderService.generate(diagnosis.id, { notes: notes || null })
      navigate('/work-orders/' + order.id)
    } catch (failure) { setError(failure) }
    finally { setBusy(false) }
  }
  const loader = useCallback((signal) => diagnosisService.get(diagnosis.id, { signal }), [diagnosis.id])
  const source = useData('generation-preview:' + diagnosis.id, loader)
  if (source.isLoading) return <p role="status">Actualizando diagnóstico para generar…</p>
  if (source.error) return <div role="alert"><p>{diagnosisError(source.error)}</p><button onClick={source.retry}>Reintentar resumen</button><button onClick={onCancel}>Cancelar</button></div>
  const current = source.data
  if (current.work_order) return <p>Este diagnóstico ya tiene una orden de trabajo. <Link to={'/work-orders/' + current.work_order.id}>Ver orden de trabajo</Link></p>
  const fields = validationErrors(error)
  return <form onSubmit={generate}>
        <h2>Generar orden de trabajo</h2>
        <p><span className="vin-value">{current.unit.vin}</span> · {current.unit.model || 'Sin modelo'} · {current.defect_count} defectos</p>
        <p>Prioridad inicial calculada: <strong>{current.work_order_priority_preview?.label || 'Se calculará al generar'}</strong></p>
        <label htmlFor="work-order-notes">Notas de la orden (opcional)</label>
        <textarea id="work-order-notes" name="notes" maxLength={5000} disabled={busy} />
        <FieldError errors={fields} name="notes" />
        <FieldError errors={fields} name="diagnosis" />
        {error && <p role="alert" className="error">{diagnosisError(error)}</p>}
        <div className="form-actions"><button className="primary-button" disabled={busy || !current.defect_count}>{busy ? 'Generando…' : 'Confirmar generación'}</button>
          <button type="button" disabled={busy} onClick={onCancel}>Cancelar</button></div>
      </form>
}

export default function GenerateWorkOrder({ diagnosis }) {
  const { can } = useAuth()
  const [open, setOpen] = useState(false)
  if (diagnosis.work_order) return can(workOrderPermissions.view)
    ? <p><Link className="button-link" to={'/work-orders/' + diagnosis.work_order.id}>Ver orden de trabajo</Link></p>
    : <p className="muted">Este diagnóstico ya tiene una orden de trabajo.</p>
  if (!can(workOrderPermissions.create)) return null
  if (!diagnosis.defect_count) return <p className="muted">Agrega al menos un defecto para generar una orden de trabajo.</p>
  return <section className="work-order-generation" aria-label="Generación de orden">
    {open ? <GenerationForm key={JSON.stringify(diagnosis.defects.map((defect) => [defect.id, defect.priority]))} diagnosis={diagnosis} onCancel={() => setOpen(false)} />
      : <button className="primary-button" onClick={() => setOpen(true)}>Generar orden de trabajo</button>}
  </section>
}