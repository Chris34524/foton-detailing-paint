import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import unitService from '../units/unitService'
import { unitPermissions } from '../units/permissions'
import { formatUnitDate, unitErrorMessage } from '../units/unitErrors'
import UnitStatusBadge from '../units/UnitStatusBadge'
import UnitEditForm from '../units/UnitEditForm'
import { diagnosisPermissions } from '../diagnosis/permissions'

export default function UnitDetailPage() {
  const { unitId } = useParams()
  const { can } = useAuth()
  const [attempt, setAttempt] = useState(0)
  const key = unitId + ':' + attempt
  const [result, setResult] = useState(null)
  useEffect(() => {
    const controller = new AbortController()
    let active = true
    Promise.all([unitService.get(unitId, { signal: controller.signal }), unitService.meta({ signal: controller.signal })])
      .then(([unit, statuses]) => { if (active) setResult({ key, unit, statuses }) })
      .catch((error) => { if (active) setResult({ key, error }) })
    return () => { active = false; controller.abort() }
  }, [unitId, key])
  if (result?.key !== key) return <p role="status">Cargando unidad…</p>
  if (result.error) return <section className="workspace-card">
    <h1>{result.error.response?.status === 404 ? 'Unidad no encontrada' : 'No fue posible abrir la unidad'}</h1>
    <p className="error" role="alert">{unitErrorMessage(result.error, 'Revisa la conexión e intenta de nuevo.')}</p>
    <Link to="/units">Volver a Unidades</Link>
    {![403, 404].includes(result.error.response?.status) && <button onClick={() => setAttempt((value) => value + 1)}>Reintentar</button>}
  </section>
  const { unit, statuses } = result
  return <section className="workspace-card">
    <Link to="/units">← Volver a Unidades</Link>
    <div className="section-heading page-title"><h1>Detalle de unidad</h1><UnitStatusBadge unit={unit} /></div>
    <dl className="unit-detail">
      <div><dt>VIN</dt><dd className="vin-value">{unit.vin}</dd></div>
      <div><dt>Modelo</dt><dd>{unit.model || '—'}</dd></div>
      <div><dt>Estado</dt><dd>{unit.status_label}</dd></div>
      <div><dt>Ubicación actual</dt><dd>{unit.current_location || '—'}</dd></div>
      <div><dt>Fecha de registro</dt><dd>{formatUnitDate(unit.created_at)}</dd></div>
      <div><dt>Última actualización</dt><dd>{formatUnitDate(unit.updated_at)}</dd></div>
      <div><dt>Registrado por</dt><dd>{unit.registered_by?.name || 'Usuario no disponible'}</dd></div>
    </dl>
    {(can(diagnosisPermissions.view) || can(diagnosisPermissions.create)) && <section className="unit-edit">
      <h2>Diagnóstico</h2>
      <div className="form-actions diagnosis-actions">
        {can(diagnosisPermissions.view) && <Link to={'/units/' + unit.id + '/diagnoses'}>Ver diagnósticos</Link>}
        {can(diagnosisPermissions.create) && <Link className="button-link" to={'/units/' + unit.id + '/diagnoses/new'}>Nuevo diagnóstico</Link>}
      </div>
    </section>}
    {can(unitPermissions.update)
      ? <UnitEditForm key={unit.id} unit={unit} statuses={statuses} onSaved={(updated) => setResult((current) => ({ ...current, unit: updated }))} />
      : <p className="muted">Consulta de solo lectura.</p>}
  </section>
}
