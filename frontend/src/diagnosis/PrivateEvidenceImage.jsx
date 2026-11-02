import { useEffect, useState } from 'react'
import diagnosisService from './diagnosisService'
import { diagnosisError } from './diagnosisErrors'

export default function PrivateEvidenceImage({ evidence }) {
  const [attempt, setAttempt] = useState(0)
  const [result, setResult] = useState(null)
  const key = evidence.view_url + ':' + attempt
  useEffect(() => {
    const controller = new AbortController()
    let active = true
    let objectUrl
    diagnosisService.image(evidence.view_url, { signal: controller.signal }).then((blob) => {
      if (!active) return
      objectUrl = URL.createObjectURL(blob)
      setResult({ key, url: objectUrl })
    }).catch((error) => { if (active) setResult({ key, error }) })
    return () => {
      active = false
      controller.abort()
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [evidence.view_url, key])
  return <figure className="evidence-image">
    {result?.key !== key ? <p role="status">Cargando evidencia…</p>
      : result.error ? <div role="alert"><p>{diagnosisError(result.error, 'No fue posible cargar la evidencia.')}</p>
        <button onClick={() => setAttempt((value) => value + 1)}>Reintentar imagen</button></div>
        : <a href={result.url} target="_blank" rel="noopener noreferrer" aria-label={'Abrir ' + evidence.original_name}>
          <img src={result.url} alt={evidence.original_name} loading="lazy" />
        </a>}
    <figcaption>{evidence.original_name} · {Math.ceil(evidence.size_bytes / 1024)} KB
      {evidence.uploaded_by && <span> · {evidence.uploaded_by.name}</span>}
    </figcaption>
  </figure>
}