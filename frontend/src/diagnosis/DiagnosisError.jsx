import { Link } from 'react-router-dom'
import { diagnosisError } from './diagnosisErrors'

export default function DiagnosisError({ error, retry, missingTitle = 'Diagnóstico no encontrado' }) {
  return <section className="workspace-card">
    <h1>{error?.response?.status === 404 ? missingTitle : 'No fue posible cargar la información'}</h1>
    <p role="alert" className="error">{diagnosisError(error)}</p>
    <div className="form-actions"><Link to="/diagnoses">Volver a Diagnóstico</Link>
      {![403, 404].includes(error?.response?.status) && <button onClick={retry}>Reintentar</button>}
    </div>
  </section>
}