import { useCallback, useState } from 'react'
import useData from '../diagnosis/useDiagnosisData'
import { diagnosisError, validationErrors } from '../diagnosis/diagnosisErrors'
import FieldError from '../diagnosis/FieldError'
import Pagination from '../diagnosis/Pagination'
import workOrderService from './workOrderService'

function OperatorPicker({ order, busy, onSubmit }) {
  const [filters, setFilters] = useState({ page: 1 })
  const loader = useCallback((signal) => workOrderService.operators(filters, { signal }), [filters])
  const { data, error, isLoading, retry } = useData('operators:' + JSON.stringify(filters), loader)
  return <section aria-label="Asignación de operador">
    <h2>Asignar operador</h2>
    <form className="diagnosis-search" onSubmit={(event) => {
      event.preventDefault()
      const search = new FormData(event.currentTarget).get('operator_search').trim()
      setFilters({ page: 1, ...(search ? { search } : {}) })
    }}>
      <div><label htmlFor="operator-search">Buscar operador</label><input id="operator-search" name="operator_search" maxLength={255} /></div>
      <button disabled={busy}>Buscar operador</button>
    </form>
    {isLoading && <p role="status">Cargando operadores…</p>}
    {error && <div role="alert"><p>{diagnosisError(error)}</p><button onClick={retry}>Reintentar operadores</button></div>}
    {data && <>
      {data.data.length === 0 && <p className="empty-state">No hay operadores disponibles con estos criterios. Un administrador debe configurar usuarios con rol de operador.</p>}
      <form onSubmit={(event) => {
        event.preventDefault()
        const value = new FormData(event.currentTarget).get('assigned_to')
        onSubmit(value === '' ? null : Number(value))
      }}>
        <label htmlFor="work-operator">Operador asignado</label>
        <select id="work-operator" name="assigned_to" defaultValue={order.assigned_to?.id ?? ''} disabled={busy} required={!order.can_unassign}>
          <option value="" disabled={!order.can_unassign}>Sin asignar</option>
          {order.assigned_to && !data.data.some((operator) => operator.id === order.assigned_to.id)
            && <option value={order.assigned_to.id}>{order.assigned_to.name} (actual)</option>}
          {data.data.map((operator) => <option key={operator.id} value={operator.id}>{operator.name}</option>)}
        </select>
        <button className="primary-button" disabled={busy}>Guardar asignación</button>
        {!order.can_unassign && <p className="muted">Una orden en proceso debe conservar un operador.</p>}
      </form>
      <Pagination meta={data.meta} label="Paginación de operadores" onPage={(page) => setFilters((current) => ({ ...current, page }))} />
    </>}
  </section>
}

export default function WorkOrderActions({ order, priorities, canUpdate, canAssign, onSaved }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  async function save(action) {
    setBusy(true); setError(null)
    try {
      await action()
      await onSaved()
    } catch (failure) { setError(failure) }
    finally { setBusy(false) }
  }
  if (order.is_terminal) return <p className="empty-state">Orden finalizada · solo lectura.</p>
  const fields = validationErrors(error)
  return <div className="work-order-actions">
    {error && <div role="alert" className="error"><p>{diagnosisError(error)}</p>
      {Object.entries(fields).map(([field]) => <FieldError key={field} errors={fields} name={field} />)}</div>}
    {canUpdate && <section><h2>Prioridad y notas</h2>
      <form onSubmit={(event) => {
        event.preventDefault()
        const form = new FormData(event.currentTarget)
        save(() => workOrderService.update(order.id, { priority: form.get('priority'), notes: form.get('notes').trim() || null }))
      }}>
        <label htmlFor="edit-work-priority">Prioridad de la orden</label>
        <select id="edit-work-priority" name="priority" defaultValue={order.priority} disabled={busy}>
          {priorities.map((priority) => <option key={priority.value} value={priority.value}>{priority.label}</option>)}
        </select>
        <label htmlFor="edit-work-notes">Notas de la orden</label>
        <textarea id="edit-work-notes" name="notes" maxLength={5000} defaultValue={order.notes || ''} disabled={busy} />
        <button className="primary-button" disabled={busy}>Guardar prioridad y notas</button>
      </form>
      <h2>Cambiar estado</h2>
      {order.allowed_status_transitions.length > 0 ? <form onSubmit={(event) => {
        event.preventDefault()
        const status = new FormData(event.currentTarget).get('status')
        save(() => workOrderService.status(order.id, status))
      }}>
        <label htmlFor="edit-work-status">Nuevo estado</label>
        <select id="edit-work-status" name="status" required disabled={busy} defaultValue="">
          <option value="" disabled>Selecciona una transición</option>
          {order.allowed_status_transitions.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
        </select>
        <button className="primary-button" disabled={busy}>Guardar estado</button>
      </form> : <p>No hay transiciones disponibles.</p>}
      {!order.assigned_to && <p className="muted">Asigna un operador para habilitar el inicio del trabajo.</p>}
    </section>}
    {canAssign && <OperatorPicker order={order} busy={busy} onSubmit={(assignedTo) => save(() => workOrderService.assign(order.id, assignedTo))} />}
    {!canUpdate && !canAssign && <p className="muted">Consulta de solo lectura.</p>}
  </div>
}
