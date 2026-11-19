import { useCallback, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import useData from '../diagnosis/useDiagnosisData'
import inventoryService from '../operations/inventoryService'
import InventoryMovementForm from '../operations/InventoryMovementForm'
import InventoryNavigation from '../operations/InventoryNavigation'
import InventoryReceipt from '../operations/InventoryReceipt'
import { LoadState } from '../operations/Feedback'
export default function InventoryMovementCreatePage() {
  const [params] = useSearchParams()
  const materialId = params.get('material_id')
  const [receipt, setReceipt] = useState(null)
  const [revision, setRevision] = useState(0)
  const loader = useCallback(async (signal) => {
    const [meta, material] = await Promise.all([inventoryService.meta({ signal }), materialId ? inventoryService.stock(materialId, { signal }) : Promise.resolve(null)])
    return { meta, material }
  }, [materialId])
  const resource = useData('create-movement:' + (materialId || ''), loader)
  return <section className="workspace-card"><h1>Registrar movimiento</h1><InventoryNavigation />
    <LoadState resource={resource}>{resource.data && (receipt ? <>
      <InventoryReceipt movement={receipt} /><button type="button" onClick={() => { setReceipt(null); setRevision((value) => value + 1); resource.retry() }}>Registrar otro movimiento</button>
    </> : <InventoryMovementForm key={revision + ':' + (materialId || '')} meta={resource.data.meta} initialMaterial={resource.data.material} onConfirmed={setReceipt} />)}</LoadState>
  </section>
}
