import { useCallback, useState } from 'react'
import useData from '../diagnosis/useDiagnosisData'
import operationsService from './operationsService'
import { ActionError, LoadState } from './Feedback'
import OrderEditor from './OrderEditor'
function ItemEditor({ checklistId, item, onSaved, onCancel }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  async function submit(event) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setBusy(true); setError(null)
    try {
      await operationsService.saveItem(checklistId, item?.id, { code: form.get('code').trim(), label: form.get('label').trim(),
        description: form.get('description').trim() || null, is_active: form.has('is_active'), is_required: form.has('is_required') })
      onSaved()
    } catch (failure) { setError(failure) }
    finally { setBusy(false) }
  }
  return <form className="operations-form" onSubmit={submit}>
    <h3>{item ? 'Editar criterio' : 'Nuevo criterio'}</h3>
    <fieldset disabled={busy}>
      <label htmlFor="item-code">Código del criterio</label><input id="item-code" name="code" maxLength={80} required defaultValue={item?.code || ''} />
      <label htmlFor="item-label">Criterio</label><input id="item-label" name="label" maxLength={300} required defaultValue={item?.label || ''} />
      <label htmlFor="item-description">Descripción del criterio</label><textarea id="item-description" name="description" maxLength={5000} defaultValue={item?.description || ''} />
      <label className="checkbox-field"><input name="is_required" type="checkbox" defaultChecked={item?.is_required ?? true} />Obligatorio</label>
      <label className="checkbox-field"><input name="is_active" type="checkbox" defaultChecked={item?.is_active ?? true} />Criterio activo</label>
    </fieldset><ActionError error={error} />
    <div className="form-actions"><button disabled={busy}>Guardar criterio</button><button type="button" onClick={onCancel}>Cancelar criterio</button></div>
  </form>
}
function ItemsOrder({ checklist, onSaved }) {
  const [items, setItems] = useState(checklist.items)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  async function save() {
    setBusy(true); setError(null)
    try { await operationsService.orderItems(checklist.id, items.map((item) => item.id)); onSaved() }
    catch (failure) { setError(failure) }
    finally { setBusy(false) }
  }
  return <><OrderEditor entries={items} onChange={setItems} title="Orden de criterios" />
    <ActionError error={error} /><button type="button" disabled={busy} onClick={save}>Guardar orden de criterios</button></>
}
export default function ChecklistItems({ checklistId }) {
  const loader = useCallback((signal) => operationsService.checklist(checklistId, { signal }), [checklistId])
  const resource = useData('checklist-items:' + checklistId, loader)
  const [editor, setEditor] = useState(null)
  function saved() { setEditor(null); resource.retry() }
  return <section className="operations-section"><h2>Criterios del checklist</h2><LoadState resource={resource}>{resource.data && <>
    <h3>{resource.data.name}</h3><button onClick={() => setEditor({ item: null })}>Añadir criterio</button>
    {editor && <ItemEditor key={editor.item?.id ?? 'new'} checklistId={checklistId} item={editor.item} onSaved={saved} onCancel={() => setEditor(null)} />}
    {resource.data.items.length === 0 && <p className="empty-state">Este checklist no tiene criterios. Configura uno antes de usarlo.</p>}
    <ul>{resource.data.items.map((item) => <li key={item.id}>{item.code} · {item.label} · {item.is_active ? 'Activo' : 'Inactivo'} · {item.is_required ? 'Obligatorio' : 'Opcional'}
      <button onClick={() => setEditor({ item })} aria-label={'Editar criterio ' + item.label}>Editar criterio</button></li>)}</ul>
    <ItemsOrder key={JSON.stringify(resource.data.items)} checklist={resource.data} onSaved={saved} />
  </>}</LoadState></section>
}
