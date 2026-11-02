import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import diagnosisService from './diagnosisService'
import { diagnosisError, validationErrors } from './diagnosisErrors'
import FieldError from './FieldError'

export default function DefectForm({ diagnosisId, defect = null, priorities, typeCollection, canManageCatalog, onSaved, onCancel }) {
  const [values, setValues] = useState({
    defect_type_id: String(defect?.defect_type.id ?? ''), location: defect?.location ?? '',
    priority: defect?.priority ?? '', description: defect?.description ?? '',
  })
  const [errors, setErrors] = useState({})
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const [typeQuery, setTypeQuery] = useState('')
  const [typeResult, setTypeResult] = useState(null)
  const [typeError, setTypeError] = useState('')
  const [searching, setSearching] = useState(false)
  const typeController = useRef(null)
  useEffect(() => () => typeController.current?.abort(), [])
  const options = [...(typeResult ?? typeCollection).data]
  if (defect && !options.some((type) => type.id === defect.defect_type.id)) options.push(defect.defect_type)
  const prefix = 'defect-' + (defect?.id ?? 'new')
  function change(name, value) { setValues((current) => ({ ...current, [name]: value })) }
  async function searchTypes() {
    typeController.current?.abort()
    const controller = new AbortController()
    typeController.current = controller
    setSearching(true); setTypeError('')
    try {
      const result = await diagnosisService.types({ search: typeQuery.trim(), per_page: 100 }, { signal: controller.signal })
      if (!controller.signal.aborted) setTypeResult(result)
    } catch (failure) { if (!controller.signal.aborted) setTypeError(diagnosisError(failure)) }
    finally { if (!controller.signal.aborted) setSearching(false) }
  }
  async function submit(event) {
    event.preventDefault()
    if (saving) return
    setSaving(true); setError(''); setErrors({})
    const payload = { defect_type_id: values.defect_type_id ? Number(values.defect_type_id) : null,
      location: values.location.trim(), priority: values.priority, description: values.description.trim() || null }
    try {
      const saved = defect ? await diagnosisService.updateDefect(diagnosisId, defect.id, payload)
        : await diagnosisService.createDefect(diagnosisId, payload)
      onSaved(saved)
    } catch (failure) { setError(diagnosisError(failure)); setErrors(validationErrors(failure)) }
    finally { setSaving(false) }
  }
  return <form className="unit-form defect-form" onSubmit={submit} noValidate>
    <fieldset disabled={saving}>
      <legend>{defect ? 'Editar defecto' : 'Registrar defecto'}</legend>
      {typeCollection.meta.total === 0 && !defect && <div className="empty-state">
        <p>No hay tipos de defecto configurados.</p>
        <p>Antes de registrar un defecto, crea al menos un tipo activo en el catálogo.</p>
        {canManageCatalog
          ? <Link className="button-link" to="/diagnosis/defect-types" state={{ returnTo: '/diagnoses/' + diagnosisId }}>Crear un tipo de defecto</Link>
          : <p>Solicita un tipo activo al responsable del catálogo.</p>}
      </div>}
      {(typeCollection.meta.total > 0 || defect) && <>
      <div className="catalog-search">
        <label htmlFor={prefix + '-type-search'}>Buscar tipo por nombre</label>
        <input id={prefix + '-type-search'} maxLength={120} value={typeQuery} onChange={(event) => setTypeQuery(event.target.value)} />
        <button type="button" disabled={searching} onClick={searchTypes}>{searching ? 'Buscando…' : 'Buscar tipos'}</button>
        <button type="button" disabled={searching} onClick={() => { setTypeQuery(''); setTypeResult(null); setTypeError('') }}>Limpiar búsqueda de tipos</button>
      </div>
      {typeError && <p className="error" role="alert">{typeError}</p>}
      {options.length === 0 && <p className="muted">No hay tipos activos que coincidan con la búsqueda. Limpia la búsqueda o usa otro nombre.</p>}
      </>}
      <label htmlFor={prefix + '-type'}>Tipo de defecto</label>
      <select id={prefix + '-type'} value={values.defect_type_id} required aria-invalid={Boolean(errors.defect_type_id)}
        aria-describedby={errors.defect_type_id ? prefix + '-type-error' : undefined} onChange={(event) => change('defect_type_id', event.target.value)}>
        <option value="">Selecciona un tipo</option>
        {options.map((type) => <option key={type.id} value={type.id}>{type.name}{!type.is_active ? ' (inactivo actual)' : ''}</option>)}
      </select>
      <FieldError errors={errors} name="defect_type_id" id={prefix + '-type-error'} />
      {(typeResult ?? typeCollection).meta.total > options.length && <p className="muted">Usa la búsqueda para encontrar otros tipos del catálogo.</p>}
      <label htmlFor={prefix + '-location'}>Ubicación del defecto</label>
      <input id={prefix + '-location'} maxLength={160} required value={values.location} onChange={(event) => change('location', event.target.value)}
        aria-invalid={Boolean(errors.location)} aria-describedby={prefix + '-location-hint' + (errors.location ? ' ' + prefix + '-location-error' : '')} />
      <p id={prefix + '-location-hint'} className="muted">Zona física del vehículo, por ejemplo: puerta delantera derecha.</p>
      <FieldError errors={errors} name="location" id={prefix + '-location-error'} />
      <label htmlFor={prefix + '-priority'}>Prioridad</label>
      <select id={prefix + '-priority'} required value={values.priority} onChange={(event) => change('priority', event.target.value)}
        aria-invalid={Boolean(errors.priority)} aria-describedby={errors.priority ? prefix + '-priority-error' : undefined}>
        <option value="">Selecciona una prioridad</option>
        {priorities.map((priority) => <option key={priority.value} value={priority.value}>{priority.label}</option>)}
      </select>
      <FieldError errors={errors} name="priority" id={prefix + '-priority-error'} />
      <label htmlFor={prefix + '-description'}>Descripción del defecto</label>
      <textarea id={prefix + '-description'} rows={3} maxLength={5000} value={values.description}
        onChange={(event) => change('description', event.target.value)} aria-invalid={Boolean(errors.description)} />
      <FieldError errors={errors} name="description" id={prefix + '-description-error'} />
    </fieldset>
    {error && <p className="error" role="alert">{error}</p>}
    <div className="form-actions"><button className="primary-button" disabled={saving || options.length === 0}>
      {saving ? 'Guardando…' : defect ? 'Guardar defecto' : 'Registrar defecto'}</button>
      <button type="button" onClick={onCancel} disabled={saving}>Cancelar</button></div>
  </form>
}
