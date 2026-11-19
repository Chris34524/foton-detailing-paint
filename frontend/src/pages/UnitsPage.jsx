import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import unitService, { isValidVin, normalizeVin } from '../units/unitService'
import { unitPermissions } from '../units/permissions'
import { formatUnitDate, unitErrorMessage } from '../units/unitErrors'
import UnitStatusBadge from '../units/UnitStatusBadge'

export default function UnitsPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const query = searchParams.toString()
  const [attempt, setAttempt] = useState(0)
  const loadKey = query + ':' + attempt
  const [result, setResult] = useState(null)
  const [lookupVin, setLookupVin] = useState('')
  const [lookupError, setLookupError] = useState('')
  const [lookingUp, setLookingUp] = useState(false)

  useEffect(() => {
    const controller = new AbortController()
    let active = true
    Promise.all([
      unitService.list(Object.fromEntries(new URLSearchParams(query)), { signal: controller.signal }),
      unitService.meta({ signal: controller.signal }),
    ]).then(([collection, statuses]) => {
      if (active) setResult({ key: loadKey, collection, statuses })
    }).catch((error) => {
      if (active) setResult({ key: loadKey, error })
    })
    return () => { active = false; controller.abort() }
  }, [query, loadKey])

  const ready = result?.key === loadKey
  const collection = ready ? result.collection : null
  const statuses = ready ? result.statuses ?? [] : []

  function applyFilters(event) {
    event.preventDefault()
    const params = new URLSearchParams()
    for (const [key, raw] of new FormData(event.currentTarget)) {
      const value = raw.trim()
      if (value) params.set(key, value)
    }
    setSearchParams(params)
  }
  function changePage(page) {
    const params = new URLSearchParams(searchParams)
    params.set('page', String(page))
    setSearchParams(params)
  }
  async function lookup(event) {
    event.preventDefault()
    if (lookingUp) return
    if (!isValidVin(lookupVin)) {
      setLookupError('El VIN debe tener 17 letras o números, sin I, O ni Q.')
      return
    }
    setLookingUp(true)
    setLookupError('')
    try {
      const unit = await unitService.findByVin(normalizeVin(lookupVin))
      navigate('/units/' + unit.id)
    } catch (error) {
      setLookupError(unitErrorMessage(error, 'No fue posible consultar el VIN. Revisa la conexión e intenta de nuevo.'))
    } finally { setLookingUp(false) }
  }

  return <section className="workspace-card">
    <div className="section-heading">
      <div><p className="eyebrow">ACTIVOS</p><h1>Unidades</h1></div>
      {can(unitPermissions.create) && <Link className="button-link primary-link" to="/units/new">Registrar unidad</Link>}
    </div>
    <form className="vin-lookup" onSubmit={lookup} noValidate>
      <div><label htmlFor="lookup-vin">Consultar por VIN</label>
        <input id="lookup-vin" value={lookupVin} maxLength={17} autoComplete="off" disabled={lookingUp}
          onChange={(event) => setLookupVin(event.target.value.toUpperCase())} aria-describedby={lookupError ? 'lookup-error' : undefined} /></div>
      <button disabled={lookingUp}>{lookingUp ? 'Consultando…' : 'Consultar VIN'}</button>
    </form>
    {lookupError && <p className="error" id="lookup-error" role="alert">{lookupError}</p>}

    <form key={query} className="unit-filters" onSubmit={applyFilters}>
      <div><label htmlFor="filter-search">Buscar</label>
        <input id="filter-search" name="search" maxLength={120} defaultValue={searchParams.get('search') ?? ''} placeholder="VIN, modelo o ubicación" /></div>
      <div><label htmlFor="filter-model">Filtrar por modelo</label>
        <input id="filter-model" name="model" maxLength={100} defaultValue={searchParams.get('model') ?? ''} /></div>
      <div><label htmlFor="filter-status">Filtrar por estado</label>
        <select key={statuses.map((status) => status.value).join(',')} id="filter-status" name="status" defaultValue={searchParams.get('status') ?? ''}>
          <option value="">Todos los estados</option>
          {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
        </select></div>
      <div><label htmlFor="filter-location">Filtrar por ubicación</label>
        <input id="filter-location" name="location" maxLength={120} defaultValue={searchParams.get('location') ?? ''} /></div>
      <div><label htmlFor="filter-sort">Ordenar por</label>
        <select id="filter-sort" name="sort" defaultValue={searchParams.get('sort') ?? 'created_at'}>
          <option value="created_at">Fecha de registro</option><option value="updated_at">Última actualización</option>
          <option value="vin">VIN</option><option value="model">Modelo</option><option value="status">Estado</option>
          <option value="current_location">Ubicación</option>
        </select></div>
      <div><label htmlFor="filter-direction">Dirección</label>
        <select id="filter-direction" name="direction" defaultValue={searchParams.get('direction') ?? 'desc'}>
          <option value="desc">Descendente</option><option value="asc">Ascendente</option>
        </select></div>
      <div><label htmlFor="filter-size">Unidades por página</label>
        <select id="filter-size" name="per_page" defaultValue={searchParams.get('per_page') ?? '20'}>
          <option value="20">20</option><option value="50">50</option><option value="100">100</option>
        </select></div>
      <div className="filter-actions"><button className="primary-button">Aplicar filtros</button>
        <button type="button" onClick={() => setSearchParams({})}>Limpiar filtros</button></div>
    </form>

    {!ready && <p role="status">Cargando unidades…</p>}
    {ready && result.error && <div className="error" role="alert">
      <p>{unitErrorMessage(result.error)}</p>
      <button onClick={() => setAttempt((value) => value + 1)}>Reintentar</button>
    </div>}
    {collection && <>
      {collection.data.length === 0
        ? <div className="empty-state"><h2>No hay unidades para mostrar</h2><p>Registra una unidad o ajusta los filtros.</p></div>
        : <div className="table-scroll" tabIndex={0} role="region" aria-label="Tabla de registros"><table>
          <caption className="sr-only">Listado de unidades</caption>
          <thead><tr><th scope="col">VIN</th><th scope="col">Modelo</th><th scope="col">Estado</th>
            <th scope="col">Ubicación actual</th><th scope="col">Última actualización</th><th scope="col">Detalle</th></tr></thead>
          <tbody>{collection.data.map((unit) => <tr key={unit.id}>
            <td className="vin-value">{unit.vin}</td><td>{unit.model || '—'}</td>
            <td><UnitStatusBadge unit={unit} /></td><td>{unit.current_location || '—'}</td>
            <td>{formatUnitDate(unit.updated_at)}</td>
            <td><Link to={'/units/' + unit.id} aria-label={'Ver detalle de ' + unit.vin}>Ver detalle</Link></td>
          </tr>)}</tbody>
        </table></div>}
      <nav className="pagination" aria-label="Paginación de unidades">
        <button disabled={collection.meta.current_page <= 1} onClick={() => changePage(collection.meta.current_page - 1)}>Anterior</button>
        <span>Página {collection.meta.current_page} de {collection.meta.last_page} · {collection.meta.total} unidades</span>
        <button disabled={collection.meta.current_page >= collection.meta.last_page} onClick={() => changePage(collection.meta.current_page + 1)}>Siguiente</button>
      </nav>
    </>}
  </section>
}
