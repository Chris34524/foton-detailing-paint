import { useCallback } from 'react'
import { useParams } from 'react-router-dom'
import useData from '../diagnosis/useDiagnosisData'
import inventoryService from '../operations/inventoryService'
import InventoryNavigation from '../operations/InventoryNavigation'
import InventoryReceipt from '../operations/InventoryReceipt'
import { LoadState } from '../operations/Feedback'
export default function InventoryMovementDetailPage() {
  const { movementId } = useParams()
  const loader = useCallback((signal) => inventoryService.movement(movementId, { signal }), [movementId])
  const resource = useData('movement:' + movementId, loader)
  return <section className="workspace-card"><h1>Detalle de movimiento</h1><InventoryNavigation />
    <LoadState resource={resource}>{resource.data && <><InventoryReceipt movement={resource.data} />
      <p>Tipo: {resource.data.movement_type_label} · Área / destino: {resource.data.area_destination || 'Sin destino específico'}</p>
      <p className="plain-text">{resource.data.notes}</p>
      <p className="muted">El movimiento confirmado se conserva sin edición. Las correcciones se registran con movimientos compensatorios.</p>
    </>}</LoadState>
  </section>
}
