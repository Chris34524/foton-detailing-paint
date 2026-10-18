import { useEffect, useState } from 'react'
import api from './services/api'

function App() {
  const [status, setStatus] = useState('Checking API...')
  const [error, setError] = useState(null)

  useEffect(() => {
    api.get('/health')
      .then((response) => {
        setStatus(
          `${response.data.status} - ${response.data.service}`
        )
      })
      .catch((err) => {
        setError(err.message)
      })
  }, [])

  return (
    <main>
      <h1>Foton Detailing & Paint</h1>

      <h2>Backend connection</h2>

      {error ? (
        <p>API Error: {error}</p>
      ) : (
        <p>API Status: {status}</p>
      )}
    </main>
  )
}

export default App
