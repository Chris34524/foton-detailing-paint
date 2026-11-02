import { Link } from 'react-router-dom'
import { diagnosisError } from '../diagnosis/diagnosisErrors'

export default function WorkOrderError({ error, retry }) {
  return <section className="workspace-card">
    <h1>{error?.response?.status === 404 ? 'Orden de trabajo no encontrada' : 'No fue posible cargar los trabajos'}</h1>
    <p role="alert" className="error">{diagnosisError(error)}</p>
    <div className="form-actions"><Link to="/work-orders">Volver a Trabajos</Link>
      {![403, 404].includes(error?.response?.status) && <button onClick={retry}>Reintentar</button>}
    </div>
  </section>
}
