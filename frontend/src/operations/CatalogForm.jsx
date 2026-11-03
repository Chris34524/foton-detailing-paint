import { useState } from 'react'
import operationsService from './operationsService'
import { ActionError } from './Feedback'
export default function CatalogForm({ kind, record = null, onSaved, onCancel }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  async function submit(event) {
    event.preventDefault()
    const fields = new FormData(event.currentTarget)
    const payload = { name: fields.get('name').trim(), description: fields.get('description').trim() || null, is_active: fields.has('is_active') }
    if (kind !== 'checklists') payload.code = fields.get('code').trim()
    if (kind === 'materials') { payload.unit = fields.get('unit').trim(); payload.warehouse_reference = fields.get('warehouse_reference').trim() || null }
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
        <label htmlFor="material-unit">Unidad de medida</label><input id="material-unit" name="unit" maxLength={24} defaultValue={record?.unit || ''} placeholder="kg, L, pza…" required />
        <label htmlFor="warehouse-reference">Referencia de Almacén</label><input id="warehouse-reference" name="warehouse_reference" maxLength={120} defaultValue={record?.warehouse_reference || ''} />
      </>}
      <label className="checkbox-field"><input type="checkbox" name="is_active" defaultChecked={record?.is_active ?? true} />Activo</label>
    </fieldset>
    <ActionError error={error} /><div className="form-actions">
      <button className="primary-button" disabled={busy}>Guardar registro</button><button type="button" disabled={busy} onClick={onCancel}>Cancelar</button>
    </div>
  </form>
}
