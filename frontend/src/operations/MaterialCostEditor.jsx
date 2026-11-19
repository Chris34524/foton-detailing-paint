import { useState } from 'react'
import operationsService from './operationsService'
import { ActionError } from './Feedback'
import { formatMxn } from './formatMxn'

export default function MaterialCostEditor({ material, onSaved, onCancel }) {
  const [value, setValue] = useState(material.unit_cost_mxn ?? '')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  async function submit(event) {
    event.preventDefault()
    if (busy) return
    setBusy(true)
    setError(null)
    try {
      await operationsService.saveCatalog('materials', material.id, { unit_cost_mxn: value.trim() || null })
      onSaved()
    } catch (failure) {
      setError(failure)
    } finally {
      setBusy(false)
    }
  }

  return <form className="operations-form material-cost-editor" aria-label={'Editar costo de ' + material.name} onSubmit={submit}>
    <div className="editor-heading">
      <div><p className="eyebrow">COSTO DE REFERENCIA</p><h2>{material.code} · {material.name}</h2></div>
    </div>
    <fieldset disabled={busy}><legend>Costo del producto terminado</legend>
      <div className="form-grid">
        <div className="form-field">
          <label htmlFor="finished-material-unit-cost">Costo unitario (MXN por {material.unit})</label>
          <input id="finished-material-unit-cost" type="text" inputMode="decimal" maxLength={19}
            pattern="[0-9]{1,12}([.][0-9]{1,6})?" placeholder="Ej. 0.350000"
            value={value} onChange={(event) => setValue(event.target.value)} />
        </div>
      </div>
      <p className="field-hint">Opcional. Costo por cada {material.unit}, no precio de venta.
        Valor actual: {formatMxn(material.unit_cost_mxn, { maximumFractionDigits: 6 })}.
        No modifica la fórmula, existencias ni los movimientos ya registrados.</p>
    </fieldset>
    <ActionError error={error} />
    <div className="form-actions">
      <button type="button" disabled={busy} onClick={onCancel}>Cancelar</button>
      <button className="primary-button" disabled={busy}>{busy ? 'Guardando…' : 'Guardar costo'}</button>
    </div>
  </form>
}
