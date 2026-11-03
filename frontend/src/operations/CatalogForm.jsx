import { useState } from 'react'
import operationsService from './operationsService'
import { ActionError } from './Feedback'
export default function CatalogForm({ kind, record = null, materialMeta = null, onSaved, onCancel }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  async function submit(event) {
    event.preventDefault()
    const fields = new FormData(event.currentTarget)
    const payload = { name: fields.get('name').trim(), description: fields.get('description').trim() || null, is_active: fields.has('is_active') }
    if (kind !== 'checklists') payload.code = fields.get('code').trim()
    if (kind === 'materials') {
      payload.unit = record?.has_inventory_history ? record.unit : fields.get('unit')
      payload.warehouse_reference = fields.get('warehouse_reference').trim() || null
      for (const field of ['minimum_stock', 'maximum_stock', 'location', 'supplier', 'notes']) payload[field] = fields.get(field).trim() || null
    }
    setBusy(true); setError(null)
    try { await operationsService.saveCatalog(kind, record?.id, payload); onSaved() }
    catch (failure) { setError(failure) }
    finally { setBusy(false) }
  }
  return <form className="operations-form" onSubmit={submit}>
    <h2>{record ? 'Editar registro' : 'Crear registro'}</h2>
    <fieldset disabled={busy}>
      {kind !== 'checklists' && <><label htmlFor="catalog-code">Código</label><input id="catalog-code" name="code" maxLength={80} defaultValue={record?.code || ''} required /></>}
      <label htmlFor="catalog-name">Nombre</label><input id="catalog-name" name="name" maxLength={160} defaultValue={record?.name || ''} required />
      <label htmlFor="catalog-description">Descripción</label><textarea id="catalog-description" name="description" maxLength={5000} defaultValue={record?.description || ''} />
      {kind === 'materials' && <>
        <label htmlFor="material-unit">Unidad de medida</label><select id="material-unit" name="unit" defaultValue={record?.unit || ''} disabled={record?.has_inventory_history} required>
          <option value="">Selecciona una unidad</option>
          {record && !(materialMeta?.units || []).some((unit) => unit.value === record.unit) && <option value={record.unit}>{record.unit}</option>}
          {(materialMeta?.units || []).map((unit) => <option key={unit.value} value={unit.value}>{unit.label}</option>)}
        </select>
        {record?.has_inventory_history && <p className="muted">La unidad se conserva porque el material ya tiene historial.</p>}
        <label htmlFor="minimum-stock">Stock mínimo</label><input id="minimum-stock" name="minimum_stock" type="number" min="0" step="0.000001" defaultValue={record?.minimum_stock ?? ''} />
        <label htmlFor="maximum-stock">Stock máximo</label><input id="maximum-stock" name="maximum_stock" type="number" min="0" step="0.000001" defaultValue={record?.maximum_stock ?? ''} />
        <label htmlFor="material-location">Ubicación física</label><input id="material-location" name="location" maxLength={160} defaultValue={record?.location || ''} />
        <label htmlFor="material-supplier">Proveedor</label><input id="material-supplier" name="supplier" maxLength={160} defaultValue={record?.supplier || ''} />
        <label htmlFor="material-notes">Observaciones del material</label><textarea id="material-notes" name="notes" maxLength={5000} defaultValue={record?.notes || ''} />
        <label htmlFor="warehouse-reference">Referencia de Almacén</label><input id="warehouse-reference" name="warehouse_reference" maxLength={120} defaultValue={record?.warehouse_reference || ''} />
      </>}
      <label className="checkbox-field"><input type="checkbox" name="is_active" defaultChecked={record?.is_active ?? true} />Activo</label>
    </fieldset>
    <ActionError error={error} /><div className="form-actions">
      <button className="primary-button" disabled={busy}>Guardar registro</button><button type="button" disabled={busy} onClick={onCancel}>Cancelar</button>
    </div>
  </form>
}
