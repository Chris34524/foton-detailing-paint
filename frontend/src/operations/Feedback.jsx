import { diagnosisError, validationErrors } from '../diagnosis/diagnosisErrors'
export function ActionError({ error }) {
  if (!error) return null
  return <div role="alert" className="error"><p>{diagnosisError(error)}</p>
    {Object.entries(validationErrors(error)).map(([name, messages]) => <p key={name}>{messages[0]}</p>)}</div>
}
export function LoadState({ resource, children }) {
  if (resource.isLoading) return <p role="status">Cargando información…</p>
  if (resource.error) return <div><ActionError error={resource.error} />
    {![403, 404].includes(resource.error?.response?.status) && <button type="button" onClick={resource.retry}>Reintentar</button>}</div>
  return children
}
