import { useCallback, useState } from 'react'
import { Link, useLocation, useSearchParams } from 'react-router-dom'
import diagnosisService from '../diagnosis/diagnosisService'
import { diagnosisError } from '../diagnosis/diagnosisErrors'
import useDiagnosisData from '../diagnosis/useDiagnosisData'
import DiagnosisError from '../diagnosis/DiagnosisError'
import DefectTypeForm from '../diagnosis/DefectTypeForm'
import Pagination from '../diagnosis/Pagination'

export default function DefectTypesPage() {
  const location = useLocation()
  const returnTo = /^\/diagnoses\/\d+$/.test(location.state?.returnTo ?? '') ? location.state.returnTo : '/diagnoses'
  const [params, setParams] = useSearchParams()
  const query = params.toString()
  const [editing, setEditing] = useState(null)
  const [message, setMessage] = useState('')
  const [errorMessage, setErrorMessage] = useState('')
  const [busy, setBusy] = useState(null)
  const loader = useCallback((signal) => diagnosisService.types({
    ...Object.fromEntries(new URLSearchParams(query)), include_inactive: 1, per_page: 20,
  }, { signal }), [query])
  const { data: collection, error, isLoading, retry } = useDiagnosisData('defect-types:' + query, loader)
  function onSaved() { setEditing(null); setMessage('Tipo de defecto guardado.'); retry() }
  function search(event) {
    event.preventDefault()
    const value = new FormData(event.currentTarget).get('search').trim()
    setParams(value ? { search: value } : {}, { state: location.state })
  }
  async function toggle(type) {
    setBusy(type.id); setErrorMessage(''); setMessage('')
    try { await diagnosisService.updateType(type.id, { is_active: !type.is_active }); onSaved() }
    catch (failure) { setErrorMessage(diagnosisError(failure)) }
    finally { setBusy(null) }
  }
  if (error) return <DiagnosisError error={error} retry={retry} missingTitle="Catálogo no encontrado" />
  return <section className="workspace-card">
    <Link to={returnTo}>{returnTo === '/diagnoses' ? '← Volver a Diagnóstico' : '← Volver al diagnóstico'}</Link><h1 className="page-title">Catálogo de tipos de defecto</h1>
    <p className="muted">Desactiva tipos que ya no se utilizan. Los defectos históricos conservan su referencia.</p>
    <DefectTypeForm key={editing?.id ?? 'new'} type={editing} onSaved={onSaved} onCancel={() => setEditing(null)} />
    {message && <p role="status" className="success-message">{message}</p>}
    {errorMessage && <p role="alert" className="error">{errorMessage}</p>}
    <form key={query} className="diagnosis-search" onSubmit={search}>
      <div><label htmlFor="catalog-search">Buscar en catálogo</label>
        <input id="catalog-search" name="search" maxLength={120} defaultValue={params.get('search') ?? ''} /></div><button>Buscar tipos</button>
    </form>
    {isLoading && <p role="status">Cargando catálogo…</p>}
    {collection && <>
      {collection.data.length === 0 ? <p className="empty-state">No hay tipos de defecto configurados.</p>
        : <div className="table-scroll"><table><caption className="sr-only">Tipos de defecto</caption>
          <thead><tr><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody>{collection.data.map((type) => <tr key={type.id}>
            <td>{type.name}</td><td>{type.description || '—'}</td><td><span className={type.is_active ? 'status-badge' : 'inactive-badge'}>{type.is_active ? 'Activo' : 'Inactivo'}</span></td>
            <td><div className="form-actions"><button onClick={() => { setEditing(type); setMessage('') }} aria-label={'Editar tipo ' + type.name}>Editar</button>
              <button disabled={busy === type.id} onClick={() => toggle(type)} aria-label={(type.is_active ? 'Desactivar ' : 'Activar ') + type.name}>
                {busy === type.id ? 'Guardando…' : type.is_active ? 'Desactivar' : 'Activar'}</button></div></td>
          </tr>)}</tbody></table></div>}
      <Pagination meta={collection.meta} label="Paginación del catálogo" onPage={(page) => {
        const next = new URLSearchParams(params); next.set('page', String(page)); setParams(next, { state: location.state })
      }} />
    </>}
  </section>
}
