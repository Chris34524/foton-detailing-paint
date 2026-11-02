import { useCallback, useEffect, useState } from 'react'

export default function useDiagnosisData(key, loader) {
  const [revision, setRevision] = useState(0)
  const [result, setResult] = useState(null)
  const requestKey = key + ':' + revision
  useEffect(() => {
    const controller = new AbortController()
    let active = true
    loader(controller.signal).then((value) => {
      if (active) setResult({ key: requestKey, value })
    }).catch((error) => {
      if (active) setResult({ key: requestKey, error })
    })
    return () => { active = false; controller.abort() }
  }, [loader, requestKey])
  const setData = useCallback((next) => {
    setResult((current) => current?.key === requestKey
      ? { ...current, value: typeof next === 'function' ? next(current.value) : next }
      : current)
  }, [requestKey])
  return {
    data: result?.key === requestKey ? result.value : null,
    error: result?.key === requestKey ? result.error : null,
    isLoading: result?.key !== requestKey,
    retry: () => setRevision((value) => value + 1),
    setData,
  }
}