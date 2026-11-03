import { useState } from 'react'
import operationsService from './operationsService'
import { ActionError } from './Feedback'
export default function CatalogForm({ kind, record = null, materialMeta = null, onSaved, onCancel }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  async function submit(event) {
    event.preventDefault()
    if (busy) return
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
  return <form className="operations-form catalog-editor" onSubmit={submit} aria-label={record ? 'Editar registro' : 'Crear registro'}>
    <div className="editor-heading"><div><p className="eyebrow">CATÁLOGO</p><h2>{record ? 'Editar registro' : 'Crear registro'}</h2></div><span className="muted">* Obligatorio</span></div>
    <fieldset disabled={busy}><legend>Identificación</legend><div className="form-grid">
      {kind !== 'checklists' && <div className="form-field"><label htmlFor="catalog-code" data-required>Código</label><input id="catalog-code" name="code" maxLength={80} defaultValue={record?.code || ''} required /></div>}
      <div className="form-field"><label htmlFor="catalog-name" data-required>Nombre</label><input id="catalog-name" name="name" maxLength={160} defaultValue={record?.name || ''} required /></div>
      <div className="form-field form-span"><label htmlFor="catalog-description">Descripción</label><textarea id="catalog-description" name="description" rows={3} maxLength={5000} defaultValue={record?.description || ''} /></div>
    </div></fieldset>
    {kind === 'materials' && <>
      <fieldset disabled={busy}><legend>Unidad y parámetros de inventario</legend><div className="form-grid">
        <div className="form-field form-span"><label htmlFor="material-unit" data-required>Unidad de medida</label><select id="material-unit" name="unit" defaultValue={record?.unit || ''} disabled={record?.has_inventory_history} required aria-describedby="material-unit-help">
          <option value="">Selecciona una unidad</option>
          {record && !(materialMeta?.units || []).some((unit) => unit.value === record.unit) && <option value={record.unit}>{record.unit}</option>}
          {(materialMeta?.units || []).map((unit) => <option key={unit.value} value={unit.value}>{unit.label}</option>)}
        </select><p id="material-unit-help" className="field-hint">{record?.has_inventory_history ? 'La unidad se conserva porque el material ya tiene historial.' : 'Las existencias y conversiones se expresarán en esta unidad.'}</p></div>
        <div className="form-field"><label htmlFor="minimum-stock">Stock mínimo</label><input id="minimum-stock" name="minimum_stock" type="number" min="0" step="0.000001" defaultValue={record?.minimum_stock ?? ''} aria-describedby="stock-parameters-help" /></div>
        <div className="form-field"><label htmlFor="maximum-stock">Stock máximo</label><input id="maximum-stock" name="maximum_stock" type="number" min="0" step="0.000001" defaultValue={record?.maximum_stock ?? ''} aria-describedby="stock-parameters-help" /></div>
      </div><p className="field-hint" id="stock-parameters-help">Opcionales. Configura ambos para activar las alertas. El máximo debe ser mayor o igual al mínimo.</p></fieldset>
      <fieldset disabled={busy}><legend>Ubicación y abastecimiento</legend><div className="form-grid">
        <div className="form-field"><label htmlFor="material-location">Ubicación física</label><input id="material-location" name="location" maxLength={160} defaultValue={record?.location || ''} placeholder="Ej. Laboratorio de Pintura" /></div>
        <div className="form-field"><label htmlFor="material-supplier">Proveedor</label><input id="material-supplier" name="supplier" maxLength={160} defaultValue={record?.supplier || ''} /></div>
        <div className="form-field form-span"><label htmlFor="warehouse-reference">Referencia de Almacén</label><input id="warehouse-reference" name="warehouse_reference" maxLength={120} defaultValue={record?.warehouse_reference || ''} /></div>
        <div className="form-field form-span"><label htmlFor="material-notes">Observaciones del material</label><textarea id="material-notes" name="notes" rows={3} maxLength={5000} defaultValue={record?.notes || ''} /></div>
      </div></fieldset>
    </>}
    <fieldset disabled={busy}><label className="checkbox-field"><input type="checkbox" name="is_active" defaultChecked={record?.is_active ?? true} />Activo</label>
      <p className="field-hint">Los registros inactivos se conservan en el historial.</p></fieldset>
    <ActionError error={error} /><div className="form-actions">
      <button type="button" disabled={busy} onClick={onCancel}>Cancelar</button><button className="primary-button" disabled={busy}>{busy ? 'Guardando…' : 'Guardar registro'}</button>
    </div>
  </form>
}
