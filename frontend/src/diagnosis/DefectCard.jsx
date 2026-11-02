import { useState } from 'react'
import { formatUnitDate } from '../units/unitErrors'
import DefectForm from './DefectForm'
import EvidenceSection from './EvidenceSection'

export default function DefectCard({ diagnosisId, defect, canEdit, priorities, typeCollection, canManageCatalog, onSaved }) {
  const [editing, setEditing] = useState(false)
  return <article className="defect-card" aria-label={'Defecto ' + defect.id}>
    <div className="section-heading"><h3>{defect.defect_type.name}</h3>
      <span className="priority-badge" data-priority={defect.priority}>{defect.priority_label}</span></div>
    {!defect.defect_type.is_active && <p className="muted">Tipo inactivo · conservado en el historial</p>}
    <dl className="unit-detail">
      <div><dt>Ubicación del defecto</dt><dd>{defect.location}</dd></div>
      <div><dt>Registrado por</dt><dd>{defect.created_by?.name || 'Usuario no disponible'}</dd></div>
      <div><dt>Fecha</dt><dd>{formatUnitDate(defect.created_at)}</dd></div>
    </dl>
    <p className="plain-text">{defect.description || 'Sin descripción.'}</p>
    {canEdit && !editing && <button onClick={() => setEditing(true)}>Editar defecto</button>}
    {editing && <DefectForm diagnosisId={diagnosisId} defect={defect} priorities={priorities} typeCollection={typeCollection}
      canManageCatalog={canManageCatalog} onCancel={() => setEditing(false)}
      onSaved={(updated) => { onSaved(updated); setEditing(false) }} />}
    <EvidenceSection diagnosisId={diagnosisId} defect={defect} canEdit={canEdit}
      onUploaded={(evidences) => onSaved({ ...defect, evidences: [...(defect.evidences ?? []), ...evidences] })} />
  </article>
}