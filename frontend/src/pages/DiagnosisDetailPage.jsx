import { useCallback, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import { formatUnitDate } from '../units/unitErrors'
import diagnosisService from '../diagnosis/diagnosisService'
import { diagnosisPermissions } from '../diagnosis/permissions'
import useDiagnosisData from '../diagnosis/useDiagnosisData'
import DiagnosisError from '../diagnosis/DiagnosisError'
import DiagnosisNotesForm from '../diagnosis/DiagnosisNotesForm'
import DefectForm from '../diagnosis/DefectForm'
import DefectCard from '../diagnosis/DefectCard'

export default function DiagnosisDetailPage() {
  const { diagnosisId } = useParams()
  const { can } = useAuth()
  const canEdit = can(diagnosisPermissions.update)
  const canManageCatalog = can(diagnosisPermissions.manageCatalog)
  const [adding, setAdding] = useState(false)
  const loader = useCallback(async (signal) => {
    const [diagnosis, priorities, typeCollection] = await Promise.all([
      diagnosisService.get(diagnosisId, { signal }),
      canEdit ? diagnosisService.meta({ signal }) : Promise.resolve([]),
      canEdit ? diagnosisService.types({ per_page: 100 }, { signal }) : Promise.resolve({ data: [], meta: { total: 0 } }),
    ])
    return { diagnosis, priorities, typeCollection }
  }, [diagnosisId, canEdit])
  const { data, error, isLoading, retry, setData } = useDiagnosisData('diagnosis:' + diagnosisId + ':' + canEdit, loader)
  if (isLoading) return <p role="status">Cargando diagnóstico…</p>
  if (error) return <DiagnosisError error={error} retry={retry} />
  const { diagnosis, priorities, typeCollection } = data
  function saveDefect(defect) {
    setData((current) => {
      const exists = current.diagnosis.defects.some((item) => item.id === defect.id)
      const defects = exists ? current.diagnosis.defects.map((item) => item.id === defect.id ? defect : item)
        : [...current.diagnosis.defects, defect]
      return { ...current, diagnosis: { ...current.diagnosis, defects, defect_count: defects.length } }
    })
  }
  return <section className="workspace-card">
    <Link to="/diagnoses">← Volver a Diagnóstico</Link>
    <div className="section-heading page-title"><h1>Detalle de diagnóstico</h1>
      {canManageCatalog && <Link to="/diagnosis/defect-types" state={{ returnTo: '/diagnoses/' + diagnosis.id }}>Catálogo de tipos</Link>}</div>
    <dl className="unit-detail">
      <div><dt>VIN</dt><dd className="vin-value">{diagnosis.unit.vin}</dd></div>
      <div><dt>Modelo</dt><dd>{diagnosis.unit.model || '—'}</dd></div>
      <div><dt>Fecha de diagnóstico</dt><dd>{formatUnitDate(diagnosis.created_at)}</dd></div>
      <div><dt>Diagnosticado por</dt><dd>{diagnosis.diagnosed_by?.name || 'Usuario no disponible'}</dd></div>
    </dl>
    <h2>Notas generales</h2><p className="plain-text">{diagnosis.notes || 'Sin notas generales.'}</p>
    {canEdit && <DiagnosisNotesForm key={diagnosis.id} diagnosis={diagnosis} onSaved={(updated) => setData((current) => ({ ...current, diagnosis: updated }))} />}
    {!canEdit && <p className="muted">Consulta de solo lectura.</p>}
    <div className="section-heading page-title"><h2>Defectos ({diagnosis.defect_count})</h2>
      {canEdit && !adding && <button className="primary-button" onClick={() => setAdding(true)}>Agregar defecto</button>}</div>
    {canEdit && adding && <DefectForm key={'new-' + diagnosis.id} diagnosisId={diagnosis.id} priorities={priorities}
      typeCollection={typeCollection} canManageCatalog={canManageCatalog} onCancel={() => setAdding(false)}
      onSaved={(defect) => { saveDefect(defect); setAdding(false) }} />}
    {diagnosis.defects.length === 0 && <p className="empty-state">No hay defectos registrados.</p>}
    <div className="defect-list">{diagnosis.defects.map((defect) => <DefectCard key={defect.id} diagnosisId={diagnosis.id} defect={defect}
      canEdit={canEdit} priorities={priorities} typeCollection={typeCollection} canManageCatalog={canManageCatalog} onSaved={saveDefect} />)}</div>
  </section>
}
