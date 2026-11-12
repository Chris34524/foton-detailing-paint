import { unit } from '../diagnosis/diagnosisFixtures'
export { collection, httpError } from '../diagnosis/diagnosisFixtures'
export const processA = { id: 1, code: 'DETAIL', name: 'Detallado', description: null, is_active: true }
export const processB = { id: 2, code: 'PAINT', name: 'Pintura', description: null, is_active: true }
export const material = { id: 5, code: 'MAT-TEST', name: 'Material de prueba', unit: 'Kg', is_active: true }
export const checklist = { id: 9, name: 'Checklist de salida', is_active: true, description: null, items: [
  { id: 91, code: 'SURFACE', label: 'Superficie sin daño', description: null, sort_order: 1, is_active: true, is_required: true },
  { id: 92, code: 'CLEAN', label: 'Limpieza', description: null, sort_order: 2, is_active: true, is_required: false },
] }
export const flow = { initialized: true, current: { step_id: 51, process_id: 1, code: 'DETAIL', name: 'Detallado', position: 1 },
  next: { step_id: 52, process_id: 2, code: 'PAINT', name: 'Pintura', position: 2 },
  steps: [{ step_id: 51, process_id: 1, code: 'DETAIL', name: 'Detallado', position: 1 }, { step_id: 52, process_id: 2, code: 'PAINT', name: 'Pintura', position: 2 }] }
export const releaseChecks = { inspection_completed: true, inspection_passed: true, latest_completed_inspection: true, work_order_completed: true, no_open_rework: true, not_released: true }
export const inspection = { id: 100, work_order_id: 15, work_order_status: 'completed', unit: { ...unit, status: 'registered', status_label: 'Registrada' },
  checklist: { id: 9, name: checklist.name }, status: 'draft', status_label: 'Borrador', result: null, result_label: null, notes: null,
  inspected_by: { id: 7, name: 'Inspector de prueba' }, created_at: '2026-11-02T12:00:00Z', updated_at: '2026-11-02T12:00:00Z',
  rework: null, release: null, release_checks: { ...releaseChecks, inspection_completed: false, inspection_passed: false, latest_completed_inspection: false },
  item_result_options: [{ value: 'compliant', label: 'Cumple' }, { value: 'non_compliant', label: 'No cumple' }],
  items: checklist.items.map((item) => ({ id: item.id + 100, checklist_item_id: item.id, item_code_snapshot: item.code, item_label_snapshot: item.label,
    item_description_snapshot: item.description, sort_order_snapshot: item.sort_order, is_required_snapshot: item.is_required, result: null, result_label: null, comment: null })),
}
export const consumption = { id: 1, work_order_id: 15, unit, material, process_step: flow.current, quantity: '1.750', consumed_by: { id: 7, name: 'Operador de prueba' }, consumed_at: '2026-11-02T12:00:00Z' }
export const user = { id: 7, name: 'Admin de prueba', roles: ['admin'], permissions: ['work_orders.view','processes.view','processes.manage','materials.view','materials.consume','materials.manage','inspection.view','inspection.execute','inspection.manage','release.execute'] }
