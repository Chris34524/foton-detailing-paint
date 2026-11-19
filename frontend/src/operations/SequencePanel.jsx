import { useCallback, useState } from 'react'
import useAuth from '../auth/useAuth'
import useData from '../diagnosis/useDiagnosisData'
import operationsService from './operationsService'
import { operationsPermissions as permissions } from './permissions'
import { ActionError, LoadState } from './Feedback'
import OrderEditor from './OrderEditor'
import CatalogPicker from './CatalogPicker'
function SequenceEditor({ sequence, onSaved }) {
  const [entries, setEntries] = useState(sequence.map((step) => step.process))
  const [selected, setSelected] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  async function save() {
    setBusy(true); setError(null)
    try { await operationsService.saveSequence(entries.map((entry) => entry.id)); onSaved() }
    catch (failure) { setError(failure) }
    finally { setBusy(false) }
  }
  return <div>
    <OrderEditor title="Configurar secuencia" entries={entries} onChange={setEntries} removable />
    <CatalogPicker kind="processes" label="Proceso" value={selected?.id} selected={selected} onChange={(value, record) => setSelected(value ? record : null)} />
    <button type="button" disabled={!selected || entries.some((entry) => entry.id === selected.id) || entries.length >= 100 || busy} onClick={() => { setEntries([...entries, selected]); setSelected(null) }}>Añadir proceso</button>
    <ActionError error={error} /><button className="primary-button" type="button" disabled={busy} onClick={save}>Guardar secuencia</button>
  </div>
}
export default function SequencePanel() {
  const { can } = useAuth()
  const loader = useCallback((signal) => operationsService.sequence({ signal }), [])
  const resource = useData('process-sequence', loader)
  return <section className="operations-section"><h2>Secuencia global de procesos</h2>
    <p className="muted">Los cambios se aplican a nuevas inicializaciones. Las órdenes existentes conservan su secuencia.</p>
    <LoadState resource={resource}>{resource.data && <>
      {resource.data.length === 0 && <p className="empty-state">No hay una secuencia configurada.</p>}
      {can(permissions.processesManage) ? <SequenceEditor key={JSON.stringify(resource.data)} sequence={resource.data} onSaved={resource.retry} />
        : <ol>{resource.data.map((entry) => <li key={entry.id}>{entry.process.code} · {entry.process.name}</li>)}</ol>}
    </>}</LoadState>
  </section>
}
