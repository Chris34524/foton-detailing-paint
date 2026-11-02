import { useCallback } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import { unitPermissions } from '../units/permissions'
import unitService from '../units/unitService'
import { formatUnitDate } from '../units/unitErrors'
import diagnosisService from '../diagnosis/diagnosisService'
import { diagnosisPermissions } from '../diagnosis/permissions'
import useDiagnosisData from '../diagnosis/useDiagnosisData'
import DiagnosisError from '../diagnosis/DiagnosisError'
import Pagination from '../diagnosis/Pagination'

export default function DiagnosesPage() {
  const { can } = useAuth()
  const { unitId } = useParams()
  const [params, setParams] = useSearchParams()
  const query = params.toString()
  const loader = useCallback(async (signal) => {
    const [collection, unit] = await Promise.all([
      diagnosisService.list(Object.fromEntries(new URLSearchParams(query)), unitId, { signal }),
      unitId ? unitService.get(unitId, { signal }) : Promise.resolve(null),
    ])
    return { collection, unit }
  }, [query, unitId])
  const { data, error, isLoading, retry } = useDiagnosisData('diagnoses:' + (unitId ?? '') + '?' + query, loader)
  function search(event) {
    event.preventDefault()
    const term = new FormData(event.currentTarget).get('search').trim()
    setParams(term ? { search: term } : {})
  }
  function changePage(page) {
    const next = new URLSearchParams(params)
    next.set('page', String(page))
    setParams(next)
  }
  if (error) return <DiagnosisError error={error} retry={retry} />
  return <section className="workspace-card">
    {unitId && <Link to={'/units/' + unitId}>← Volver a la unidad</Link>}
    <div className="section-heading page-title">
      <div><p className="eyebrow">DIAGNÓSTICO</p><h1>{unitId ? 'Diagnósticos de la unidad' : 'Diagnósticos'}</h1>
        {data?.unit && <p className="vin-value">{data.unit.vin} · {data.unit.model || 'Sin modelo'}</p>}</div>
      <div className="form-actions">
        {can(diagnosisPermissions.manageCatalog) && <Link to="/diagnosis/defect-types">Catálogo de tipos</Link>}
        {can(diagnosisPermissions.create) && can(unitPermissions.view) && (unitId
          ? <Link className="button-link primary-link" to={'/units/' + unitId + '/diagnoses/new'}>Nuevo diagnóstico</Link>
          : <Link className="button-link" to="/units">Seleccionar unidad</Link>)}
      </div>
    </div>
    <form key={query} className="diagnosis-search" onSubmit={search}>
      <div><label htmlFor="diagnosis-search">Buscar diagnósticos</label>
        <input id="diagnosis-search" name="search" maxLength={120} defaultValue={params.get('search') ?? ''} placeholder="VIN, modelo o notas" /></div>
      <button className="primary-button">Buscar</button>
      <button type="button" onClick={() => setParams({})}>Limpiar búsqueda</button>
    </form>
    {isLoading && <p role="status">Cargando diagnósticos…</p>}
    {data && <>
      {data.collection.data.length === 0
        ? <div className="empty-state"><h2>No hay diagnósticos para mostrar</h2><p>Selecciona una unidad para registrar un diagnóstico o ajusta la búsqueda.</p></div>
        : <div className="table-scroll"><table>
          <caption className="sr-only">Listado de diagnósticos</caption>
          <thead><tr><th>VIN</th><th>Modelo</th><th>Fecha de diagnóstico</th><th>Diagnosticado por</th><th>Defectos</th><th>Detalle</th></tr></thead>
          <tbody>{data.collection.data.map((diagnosis) => <tr key={diagnosis.id}>
            <td className="vin-value">{diagnosis.unit.vin}</td><td>{diagnosis.unit.model || '—'}</td>
            <td>{formatUnitDate(diagnosis.created_at)}</td><td>{diagnosis.diagnosed_by?.name || 'Usuario no disponible'}</td>
            <td>{diagnosis.defect_count}</td>
            <td><Link to={'/diagnoses/' + diagnosis.id} aria-label={'Ver diagnóstico ' + diagnosis.id}>Ver detalle</Link></td>
          </tr>)}</tbody>
        </table></div>}
      <Pagination meta={data.collection.meta} onPage={changePage} />
    </>}
  </section>
}