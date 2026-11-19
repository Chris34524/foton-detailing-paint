import { useRef, useState } from 'react'
export default function useInventoryMutation() {
  const intent = useRef(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const [uncertain, setUncertain] = useState(null)
  async function execute(payload, send) {
    if (busy) return null
    const signature = JSON.stringify(payload)
    if (intent.current?.signature !== signature) intent.current = { signature, key: crypto.randomUUID() }
    setBusy(true); setError(null)
    try {
      const result = await send({ ...payload, idempotency_key: intent.current.key })
      setUncertain(null)
      window.dispatchEvent(new Event('inventory-changed'))
      return result
    } catch (failure) {
      setError(failure)
      setUncertain(!failure.response || failure.response.status >= 500 ? signature : null)
      return null
    } finally { setBusy(false) }
  }
  return { execute, busy, error, canRetry: (payload) => uncertain === JSON.stringify(payload) }
}
