import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import unitService, { isValidVin, normalizeVin } from '../units/unitService'
import UnitFields from '../units/UnitFields'
import { unitErrorMessage } from '../units/unitErrors'

export default function UnitCreatePage() {
  const navigate = useNavigate()
  const [values, setValues] = useState({ vin: '', model: '', current_location: '' })
  const [errors, setErrors] = useState({})
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  async function submit(event) {
    event.preventDefault()
    if (saving) return
    setErrors({})
    setError('')
    if (!isValidVin(values.vin)) {
      setErrors({ vin: ['El VIN debe tener 17 letras o números, sin I, O ni Q.'] })
      return
    }
    setSaving(true)
    try {
      const unit = await unitService.create({
        vin: normalizeVin(values.vin), model: values.model.trim() || null,
        current_location: values.current_location.trim() || null,
      })
      navigate('/units/' + unit.id, { replace: true })
    } catch (failure) {
      if (failure.response?.status === 422) setErrors(failure.response.data.errors ?? {})
      setError(unitErrorMessage(failure, 'No fue posible registrar la unidad. Revisa la conexión e intenta de nuevo.'))
    } finally { setSaving(false) }
  }
  return <section className="workspace-card">
    <Link to="/units">← Volver a Unidades</Link>
    <h1 className="page-title">Registrar unidad</h1>
    <p className="muted">El VIN queda fijo después del registro. La unidad inicia como registrada.</p>
    <form className="unit-form" onSubmit={submit} noValidate>
      <fieldset disabled={saving}>
        <legend className="sr-only">Datos de registro</legend>
        <UnitFields values={values} errors={errors} includeVin onChange={(name, value) => setValues((current) => ({ ...current, [name]: value }))} />
      </fieldset>
      {error && <p className="error" role="alert">{error}</p>}
      <div className="form-actions"><button className="primary-button" disabled={saving}>{saving ? 'Registrando…' : 'Registrar unidad'}</button>
        <Link to="/units">Cancelar</Link></div>
    </form>
  </section>
}