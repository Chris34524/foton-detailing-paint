import { useState } from 'react'
import inventoryService from './inventoryService'
import CatalogPicker from './CatalogPicker'
import { ActionError } from './Feedback'
import QuantityEquivalence from './QuantityEquivalence'
import useQuantityPreview from './useQuantityPreview'
import useInventoryMutation from './useInventoryMutation'
export default function InventoryMovementForm({ meta, initialMaterial = null, onConfirmed }) {
  const [material, setMaterial] = useState(initialMaterial)
  const [type, setType] = useState(initialMaterial?.has_inventory_history && meta.allowed_movement_types.some((item) => item.value === 'entry') ? 'entry' : meta.allowed_movement_types[0]?.value || '')
  const [quantity, setQuantity] = useState('')
  const [unit, setUnit] = useState(initialMaterial?.unit || '')
  const [area, setArea] = useState('')
  const [notes, setNotes] = useState('')
  const mutation = useInventoryMutation()
  const preview = useQuantityPreview(material?.id, quantity, unit, type)
  const canonical = meta.units.find((item) => item.value === material?.unit)
  const units = meta.units.filter((item) => item.family === canonical?.family)
  const movementType = meta.allowed_movement_types.find((item) => item.value === type)
  const payload = { material_id: material?.id ?? null, movement_type: type, quantity_original: quantity, unit_original: unit, area_destination: area || null, notes: notes.trim() || null }
  async function submit(event) {
    event.preventDefault()
    const movement = await mutation.execute(payload, inventoryService.confirm)
    if (movement) onConfirmed(movement)
  }
  if (!meta.allowed_movement_types.length) return <p role="alert">No tienes permiso para registrar movimientos.</p>
  return <form className="operations-form" onSubmit={submit}>
    <fieldset disabled={mutation.busy}>
      <CatalogPicker kind="materials" label="Material" value={material?.id} selected={material} onChange={(value, record) => { setMaterial(value ? record : null); setUnit(value ? record.unit : '') }} />
      {material && <p>Componente: {material.code} · {material.name} · Unidad de inventario: {material.unit}</p>}
      <label htmlFor="movement-type">Tipo de movimiento</label><select id="movement-type" value={type} onChange={(event) => setType(event.target.value)}>
        {meta.allowed_movement_types.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
      </select>
      <label htmlFor="movement-quantity">Cantidad original</label><input id="movement-quantity" type="text" inputMode="decimal" pattern="[0-9]+([.][0-9]{1,6})?" value={quantity} required onChange={(event) => setQuantity(event.target.value)} />
      <label htmlFor="movement-unit">Unidad del movimiento</label><select id="movement-unit" value={unit} required onChange={(event) => setUnit(event.target.value)}>
        <option value="">Selecciona una unidad</option>{units.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
      </select>
      <label htmlFor="movement-area">Área / destino</label><select id="movement-area" value={area} onChange={(event) => setArea(event.target.value)}>
        <option value="">Sin destino específico</option>{meta.areas.map((item) => <option key={item} value={item}>{item}</option>)}
      </select>
      <label htmlFor="movement-notes">{movementType?.requires_justification ? 'Justificación del ajuste' : 'Observaciones del movimiento'}</label>
      <textarea id="movement-notes" maxLength={5000} required={movementType?.requires_justification} value={notes} onChange={(event) => setNotes(event.target.value)} />
      <QuantityEquivalence preview={preview} canonicalUnit={material?.unit} />
    </fieldset>
    <ActionError error={mutation.error} />
    <p className="muted">El folio, responsable, fecha y saldo definitivo se asignan al confirmar.</p>
    <button className="primary-button" disabled={mutation.busy || (!preview.data?.can_confirm && !mutation.canRetry(payload)) || (movementType?.requires_justification && !notes.trim())}>
      {mutation.busy ? 'Confirmando…' : mutation.canRetry(payload) ? 'Reintentar confirmación' : 'Confirmar movimiento'}
    </button>
  </form>
}
