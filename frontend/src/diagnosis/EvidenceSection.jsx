import { useRef, useState } from 'react'
import diagnosisService from './diagnosisService'
import { diagnosisError, validationErrors } from './diagnosisErrors'
import PrivateEvidenceImage from './PrivateEvidenceImage'

export default function EvidenceSection({ diagnosisId, defect, canEdit, onUploaded }) {
  const [files, setFiles] = useState([])
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [saving, setSaving] = useState(false)
  const [progress, setProgress] = useState(0)
  const fileInput = useRef(null)
  async function upload(event) {
    event.preventDefault()
    if (saving) return
    setError(''); setSuccess(false)
    if (files.length === 0 || files.length > 5) {
      setError('Selecciona entre 1 y 5 imágenes.'); return
    }
    if (files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) {
      setError('Solo se permiten imágenes JPG, PNG o WEBP.'); return
    }
    if (files.some((file) => file.size > 10 * 1024 * 1024)) {
      setError('Cada imagen debe pesar como máximo 10 MB.'); return
    }
    setSaving(true); setProgress(0)
    try {
      const evidences = await diagnosisService.upload(diagnosisId, defect.id, files, {
        onUploadProgress: (event) => { if (event.total) setProgress(Math.round(100 * event.loaded / event.total)) },
      })
      onUploaded(evidences)
      setFiles([])
      if (fileInput.current) fileInput.current.value = ''
      setSuccess(true)
    } catch (failure) {
      const details = Object.values(validationErrors(failure)).flat().join(' ')
      setError(details || diagnosisError(failure, 'No fue posible guardar las evidencias. Intenta de nuevo.'))
    } finally { setSaving(false) }
  }
  return <section className="evidence-section" aria-label={'Evidencias del defecto ' + defect.id}>
    <h3>Evidencias</h3>
    {defect.evidences?.length > 0
      ? <div className="evidence-gallery">{defect.evidences.map((evidence) => <PrivateEvidenceImage key={evidence.id} evidence={evidence} />)}</div>
      : <p className="muted">Sin evidencias registradas.</p>}
    {canEdit && <form className="evidence-upload" onSubmit={upload} noValidate>
      <label htmlFor={'evidence-' + defect.id}>Seleccionar evidencia</label>
      <input ref={fileInput} id={'evidence-' + defect.id} type="file" accept="image/jpeg,image/png,image/webp" multiple disabled={saving}
        onChange={(event) => { setFiles(Array.from(event.target.files ?? [])); setSuccess(false); setError('') }} />
      <p className="muted">JPG, PNG o WEBP. Máximo 10 MB por imagen y 5 imágenes por carga.</p>
      {files.length > 0 && <p>{files.length} imágenes seleccionadas</p>}
      {saving && <div role="status"><progress aria-label="Progreso de carga" max="100" value={progress} /> Subiendo evidencias…</div>}
      {error && <p className="error" role="alert">{error}</p>}
      {success && <p className="success-message" role="status">Evidencias guardadas.</p>}
      <button disabled={saving}>{saving ? 'Subiendo…' : 'Subir evidencias'}</button>
    </form>}
  </section>
}