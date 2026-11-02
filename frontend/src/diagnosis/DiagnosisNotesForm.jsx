import { useState } from 'react'
import diagnosisService from './diagnosisService'
import { diagnosisError, validationErrors } from './diagnosisErrors'
import FieldError from './FieldError'

export default function DiagnosisNotesForm({ diagnosis, onSaved }) {
  const [notes, setNotes] = useState(diagnosis.notes ?? '')
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [error, setError] = useState('')
  const [errors, setErrors] = useState({})
  async function submit(event) {
    event.preventDefault()
    if (saving) return
    setSaving(true); setSaved(false); setError(''); setErrors({})
    try {
      const updated = await diagnosisService.update(diagnosis.id, { notes: notes.trim() || null })
      onSaved(updated)
      setNotes(updated.notes ?? ''); setSaved(true)
    } catch (failure) { setError(diagnosisError(failure)); setErrors(validationErrors(failure)) }
    finally { setSaving(false) }
  }
  return <form className="unit-form diagnosis-notes" onSubmit={submit} noValidate>
    <label htmlFor="edit-diagnosis-notes">Editar notas generales</label>
    <textarea id="edit-diagnosis-notes" rows={4} maxLength={5000} value={notes} disabled={saving}
      onChange={(event) => { setNotes(event.target.value); setSaved(false) }} aria-invalid={Boolean(errors.notes)} />
    <FieldError errors={errors} name="notes" id="edit-notes-error" />
    {error && <p role="alert" className="error">{error}</p>}
    {saved && <p role="status" className="success-message">Notas guardadas.</p>}
    <button disabled={saving}>{saving ? 'Guardando…' : 'Guardar notas'}</button>
  </form>
}