import { useCallback, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import unitService from '../units/unitService'
import UnitStatusBadge from '../units/UnitStatusBadge'
import diagnosisService from '../diagnosis/diagnosisService'
import { diagnosisError, validationErrors } from '../diagnosis/diagnosisErrors'
import useDiagnosisData from '../diagnosis/useDiagnosisData'
import DiagnosisError from '../diagnosis/DiagnosisError'
import FieldError from '../diagnosis/FieldError'

function CreateForm({ unit }) {
  const navigate = useNavigate()
  const [notes, setNotes] = useState('')
  const [error, setError] = useState('')
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  async function submit(event) {
    event.preventDefault()
    if (saving) return
    setSaving(true); setError(''); setErrors({})
    try {
      const diagnosis = await diagnosisService.create(unit.id, { notes: notes.trim() || null })
      navigate('/diagnoses/' + diagnosis.id, { replace: true })
    } catch (failure) { setError(diagnosisError(failure)); setErrors(validationErrors(failure)) }
    finally { setSaving(false) }
  }
  return <form className="unit-form" onSubmit={submit} noValidate>
    <label htmlFor="diagnosis-notes">Notas generales</label>
    <textarea id="diagnosis-notes" rows={5} maxLength={5000} value={notes} disabled={saving}
      onChange={(event) => setNotes(event.target.value)} aria-invalid={Boolean(errors.notes)} aria-describedby={errors.notes ? 'notes-error' : undefined} />
    <FieldError errors={errors} name="notes" id="notes-error" />
    <p className="muted">Puedes agregar los defectos después de guardar el diagnóstico.</p>
    {error && <p className="error" role="alert">{error}</p>}
    <button className="primary-button" disabled={saving}>{saving ? 'Creando…' : 'Crear diagnóstico'}</button>
  </form>
}
export default function DiagnosisCreatePage() {
  const { unitId } = useParams()
  const loader = useCallback((signal) => unitService.get(unitId, { signal }), [unitId])
  const { data: unit, error, isLoading, retry } = useDiagnosisData('create-diagnosis:' + unitId, loader)
  if (isLoading) return <p role="status">Cargando unidad…</p>
  if (error) return <DiagnosisError error={error} retry={retry} missingTitle="Unidad no encontrada" />
  return <section className="workspace-card">
    <Link to={'/units/' + unitId}>← Volver a la unidad</Link>
    <h1 className="page-title">Nuevo diagnóstico</h1>
    <dl className="unit-detail">
      <div><dt>VIN</dt><dd className="vin-value">{unit.vin}</dd></div><div><dt>Modelo</dt><dd>{unit.model || '—'}</dd></div>
      <div><dt>Estado actual</dt><dd><UnitStatusBadge unit={unit} /></dd></div>
      <div><dt>Ubicación actual de la unidad</dt><dd>{unit.current_location || '—'}</dd></div>
    </dl>
    <CreateForm key={unit.id} unit={unit} />
  </section>
}