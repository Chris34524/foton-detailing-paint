import { diagnosis, unit } from '../diagnosis/diagnosisFixtures'
export { collection, httpError } from '../diagnosis/diagnosisFixtures'
export const meta = {
  priorities: [{ value: 'low', label: 'Baja', rank: 1 }, { value: 'medium', label: 'Media', rank: 2 }, { value: 'high', label: 'Alta', rank: 3 }, { value: 'critical', label: 'Crítica', rank: 4 }],
  statuses: [{ value: 'queued', label: 'En cola' }, { value: 'in_progress', label: 'En proceso' }, { value: 'blocked', label: 'Bloqueada' },
    { value: 'completed', label: 'Completada', is_terminal: true }, { value: 'cancelled', label: 'Cancelada', is_terminal: true }],
}
export const operator = { id: 24, name: 'Operador Demo' }
export const order = {
  id: 15, unit, diagnosis, status: 'queued', status_label: 'En cola', priority: 'high', priority_label: 'Alta',
  notes: 'Reparar cabina', assigned_to: null, assigned_by: null, assigned_at: null, created_by: { id: 1, name: 'Supervisor Demo' },
  created_at: '2026-11-02T12:00:00Z', updated_at: '2026-11-02T12:00:00Z', is_terminal: false, can_unassign: true,
  allowed_status_transitions: [{ value: 'blocked', label: 'Bloqueada' }, { value: 'cancelled', label: 'Cancelada' }],
}
export const user = { id: 1, name: 'Supervisor Demo', roles: ['supervisor'],
  permissions: ['work_orders.view', 'work_orders.create', 'work_orders.update', 'work_orders.assign', 'diagnosis.view'] }
