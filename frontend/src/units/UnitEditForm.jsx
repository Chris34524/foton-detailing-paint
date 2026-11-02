import { useState } from 'react'
import unitService from './unitService'
import UnitFields from './UnitFields'
import { unitErrorMessage } from './unitErrors'

export default function UnitEditForm({ unit, statuses, onSaved }) {
  const [values, setValues] = useState({ model: unit.model ?? '', status: unit.status, current_location: unit.current_location ?? '' })
  const [errors, setErrors] = useState({})
  const [error, setError] = useState('')
  const [saved, setSaved] = useState(false)
  const [saving, setSaving] = useState(false)
  function change(name, value) {
    setSaved(false)
    setValues((current) => ({ ...current, [name]: value }))
  }
  async function submit(event) {
    event.preventDefault()
    if (saving) return
    setSaving(true)
    setErrors({})
    setError('')
    setSaved(false)
    try {
      const updated = await unitService.update(unit.id, {
        model: values.model.trim() || null, status: values.status,
        current_location: values.current_location.trim() || null,
      })
      onSaved(updated)
      setValues({ model: updated.model ?? '', status: updated.status, current_location: updated.current_location ?? '' })
      setSaved(true)
    } catch (failure) {
      if (failure.response?.status === 422) setErrors(failure.response.data.errors ?? {})
      setError(unitErrorMessage(failure, 'No fue posible guardar los cambios. Revisa la conexión e intenta de nuevo.'))
    } finally { setSaving(false) }
  }
  return <section className="unit-edit">
    <h2>Actualizar unidad</h2>
    <form className="unit-form" onSubmit={submit} noValidate>
      <fieldset disabled={saving}>
        <legend className="sr-only">Datos editables</legend>
        <UnitFields values={values} errors={errors} onChange={change} />
        <label htmlFor="unit-status">Estado</label>
        <select id="unit-status" value={values.status} onChange={(event) => change('status', event.target.value)}
          aria-invalid={Boolean(errors.status)} aria-describedby={errors.status ? 'status-error' : undefined}>
          {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
        </select>
        {errors.status && <p className="field-error" id="status-error">{errors.status[0]}</p>}
      </fieldset>
      {error && <p className="error" role="alert">{error}</p>}
      {saved && <p className="success-message" role="status">Cambios guardados.</p>}
      <button className="primary-button" disabled={saving}>{saving ? 'Guardando…' : 'Guardar cambios'}</button>
    </form>
  </section>
}