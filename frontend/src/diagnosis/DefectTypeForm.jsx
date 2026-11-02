import { useState } from 'react'
import diagnosisService from './diagnosisService'
import { diagnosisError, validationErrors } from './diagnosisErrors'
import FieldError from './FieldError'

export default function DefectTypeForm({ type = null, onSaved, onCancel }) {
  const [name, setName] = useState(type?.name ?? '')
  const [description, setDescription] = useState(type?.description ?? '')
  const [active, setActive] = useState(type?.is_active ?? true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [errors, setErrors] = useState({})
  async function submit(event) {
    event.preventDefault()
    if (saving) return
    setSaving(true); setError(''); setErrors({})
    const payload = { name: name.trim(), description: description.trim() || null, is_active: active }
    try {
      const saved = type ? await diagnosisService.updateType(type.id, payload) : await diagnosisService.createType(payload)
      if (!type) { setName(''); setDescription(''); setActive(true) }
      onSaved(saved)
    } catch (failure) { setError(diagnosisError(failure)); setErrors(validationErrors(failure)) }
    finally { setSaving(false) }
  }
  return <form className="unit-form catalog-form" onSubmit={submit} noValidate>
    <h2>{type ? 'Editar tipo de defecto' : 'Crear tipo de defecto'}</h2>
    <fieldset disabled={saving}>
      <legend className="sr-only">Datos del tipo de defecto</legend>
      <label htmlFor="type-name">Nombre del tipo</label>
      <input id="type-name" maxLength={120} required value={name} onChange={(event) => setName(event.target.value)} aria-invalid={Boolean(errors.name)} />
      <FieldError errors={errors} name="name" id="type-name-error" />
      <label htmlFor="type-description">Descripción del tipo</label>
      <textarea id="type-description" rows={3} maxLength={2000} value={description} onChange={(event) => setDescription(event.target.value)} />
      <FieldError errors={errors} name="description" id="type-description-error" />
      <label className="checkbox-field"><input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} /> Activo</label>
    </fieldset>
    {error && <p className="error" role="alert">{error}</p>}
    <div className="form-actions"><button className="primary-button" disabled={saving}>
      {saving ? 'Guardando…' : type ? 'Guardar tipo' : 'Crear tipo'}</button>
      {type && <button type="button" onClick={onCancel} disabled={saving}>Cancelar edición</button>}</div>
  </form>
}