import { useEffect, useState } from 'react'
import inventoryService from './inventoryService'
export default function useQuantityPreview(materialId, quantity, unit, movementType = 'exit') {
  const [result, setResult] = useState(null)
  const ready = Boolean(materialId && quantity && unit)
  const key = JSON.stringify([materialId, quantity, unit, movementType])
  useEffect(() => {
    if (!ready) return
    const controller = new AbortController()
    let active = true
    const timer = setTimeout(() => {
      inventoryService.preview(materialId, { quantity_original: quantity, unit_original: unit, movement_type: movementType }, { signal: controller.signal })
        .then((data) => { if (active) setResult({ key, data }) })
        .catch((error) => { if (active) setResult({ key, error }) })
    }, 250)
    return () => { active = false; clearTimeout(timer); controller.abort() }
  }, [key, ready, materialId, quantity, unit, movementType])
  return { data: ready && result?.key === key ? result.data : null, error: ready && result?.key === key ? result.error : null, isLoading: ready && result?.key !== key }
}
