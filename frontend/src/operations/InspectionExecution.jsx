import { useState } from 'react'
import operationsService from './operationsService'
import { ActionError } from './Feedback'
export default function InspectionExecution({ inspection, onSaved }) {
  const [items, setItems] = useState(inspection.items.map((item) => ({ id: item.id, result: item.result, comment: item.comment || '' })))
  const [notes, setNotes] = useState(inspection.notes || '')
  const [cause, setCause] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const summary = { compliant: 0, non_compliant: 0, pending: 0 }
  items.forEach((item) => { if (item.result) summary[item.result] += 1; else summary.pending += 1 })
  const requiredPending = inspection.items.some((item, index) => item.is_required_snapshot && !items[index].result)
  const requiredFailed = inspection.items.some((item, index) => item.is_required_snapshot && items[index].result === 'non_compliant')
  function change(index, field, value) { setItems((current) => current.map((item, position) => position === index ? { ...item, [field]: value } : item)) }
  async function save(completing) {
    setBusy(true); setError(null)
    try {
      await operationsService.saveInspection(inspection.id, { notes: notes.trim() || null, items: items.map((item) => ({ ...item, comment: item.comment.trim() || null })) })
      if (completing) await operationsService.complete(inspection.id, { rework_cause: cause.trim() || null })
      await onSaved()
    } catch (failure) { setError(failure) }
    finally { setBusy(false) }
  }
  return <form className="operations-form" onSubmit={(event) => { event.preventDefault(); save(false) }}>
    <h2>Evaluar criterios</h2><fieldset disabled={busy}>
      {inspection.items.map((item, index) => <article className="inspection-criterion" key={item.id}>
        <h3>{item.item_code_snapshot} · {item.item_label_snapshot}</h3><p>{item.item_description_snapshot}</p>
        <p className="muted">{item.is_required_snapshot ? 'Obligatorio' : 'Opcional'}</p>
        <label htmlFor={'result-' + item.id}>Resultado: {item.item_label_snapshot}</label>
        <select id={'result-' + item.id} value={items[index].result || ''} onChange={(event) => change(index, 'result', event.target.value || null)}>
          <option value="">Pendiente</option>{inspection.item_result_options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </select>
        <label htmlFor={'comment-' + item.id}>Comentario: {item.item_label_snapshot}</label>
        <textarea id={'comment-' + item.id} maxLength={2000} value={items[index].comment} onChange={(event) => change(index, 'comment', event.target.value)} />
      </article>)}
      <label htmlFor="execution-notes">Notas de inspección</label><textarea id="execution-notes" maxLength={5000} value={notes} onChange={(event) => setNotes(event.target.value)} />
      <div className="completion-summary" role="status">Cumple: {summary.compliant} · No cumple: {summary.non_compliant} · Pendiente: {summary.pending}</div>
      {requiredPending && <p>Responde los criterios obligatorios antes de completar.</p>}
      {requiredFailed && <><label htmlFor="rework-cause">Causa de retrabajo</label><textarea id="rework-cause" maxLength={5000} value={cause} onChange={(event) => setCause(event.target.value)} />
        <p className="muted">Al completar se generará el retrabajo automáticamente.</p></>}
    </fieldset><ActionError error={error} /><div className="form-actions">
      <button disabled={busy}>Guardar respuestas</button>
      <button className="primary-button" type="button" disabled={busy || requiredPending || (requiredFailed && !cause.trim())} onClick={() => save(true)}>Completar inspección</button>
    </div>
  </form>
}
