import { useCallback, useState } from 'react'
import useData from '../diagnosis/useDiagnosisData'
import inventoryService from './inventoryService'
import operationsService from './operationsService'
import CatalogPicker from './CatalogPicker'
import { ActionError, LoadState } from './Feedback'
import QuantityEquivalence from './QuantityEquivalence'
import useQuantityPreview from './useQuantityPreview'
import useInventoryMutation from './useInventoryMutation'
function ConsumptionEditor({ order, flow, meta, onSaved, onCancel }) {
  const [material, setMaterial] = useState(null)
  const [quantity, setQuantity] = useState('')
  const [unit, setUnit] = useState('')
  const [area, setArea] = useState('')
  const [step, setStep] = useState(flow?.current?.step_id ? String(flow.current.step_id) : '')
  const [notes, setNotes] = useState('')
  const mutation = useInventoryMutation()
  const preview = useQuantityPreview(material?.id, quantity, unit)
  const canonical = meta.units.find((item) => item.value === material?.unit)
  const units = meta.units.filter((item) => item.family === canonical?.family)
  const payload = { material_id: material?.id ?? null, quantity, unit_original: unit, area_destination: area || null,
    work_order_process_step_id: step ? Number(step) : null, notes: notes.trim() || null }
  async function submit(event) {
    event.preventDefault()
    const consumption = await mutation.execute(payload, (body) => operationsService.consume(order.id, body))
    if (consumption) onSaved(consumption)
  }
  return <form className="operations-form" onSubmit={submit}>
    <h3>Registrar consumo</h3><p className="vin-value">{order.unit.vin}</p>
    <fieldset disabled={mutation.busy}>
      <CatalogPicker kind="materials" label="Material" value={material?.id} selected={material}
        onChange={(value, record) => { setMaterial(value ? record : null); setUnit(value ? record.unit : '') }} />
      {material && <p>Unidad de inventario: {material.unit} · Stock disponible: {preview.data?.stock_before ?? material.stock_actual ?? '—'}</p>}
      <label htmlFor="consumption-quantity">Cantidad</label><input id="consumption-quantity" inputMode="decimal" pattern="[0-9]+([.][0-9]{1,6})?" required value={quantity} onChange={(event) => setQuantity(event.target.value)} />
      <label htmlFor="consumption-unit">Unidad original del consumo</label><select id="consumption-unit" value={unit} required onChange={(event) => setUnit(event.target.value)}>
        <option value="">Selecciona una unidad</option>{units.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
      </select>
      <label htmlFor="consumption-process">Proceso del consumo</label><select id="consumption-process" value={step} onChange={(event) => setStep(event.target.value)}>
        <option value="">Consumo general / sin proceso</option>
        {(flow?.steps || []).map((entry) => <option key={entry.step_id} value={entry.step_id}>{entry.code} · {entry.name}</option>)}
      </select>
      <label htmlFor="consumption-area">Área / destino del consumo</label><select id="consumption-area" value={area} onChange={(event) => setArea(event.target.value)}>
        <option value="">Sin destino específico</option>{meta.areas.map((entry) => <option key={entry} value={entry}>{entry}</option>)}
      </select>
      <label htmlFor="consumption-notes">Notas del consumo</label><textarea id="consumption-notes" maxLength={5000} value={notes} onChange={(event) => setNotes(event.target.value)} />
      <QuantityEquivalence preview={preview} canonicalUnit={material?.unit} />
    </fieldset><ActionError error={mutation.error} /><div className="form-actions">
      <button className="primary-button" disabled={mutation.busy || (!preview.data?.can_confirm && !mutation.canRetry(payload))}>
        {mutation.canRetry(payload) ? 'Reintentar consumo' : 'Guardar consumo'}</button>
      <button type="button" disabled={mutation.busy} onClick={onCancel}>Cancelar consumo</button>
    </div>
  </form>
}
export default function InventoryConsumptionForm(props) {
  const loader = useCallback((signal) => inventoryService.meta({ signal }), [])
  const resource = useData('consumption-meta', loader)
  return <LoadState resource={resource}>{resource.data && <ConsumptionEditor {...props} meta={resource.data} />}</LoadState>
}
