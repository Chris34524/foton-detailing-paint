export const unit = { id: 7, vin: 'LVBV3JBB7RA123456', model: 'S5', status: 'registered', status_label: 'Registrada', current_location: 'Recepción' }
export const type = { id: 3, name: 'Rayón de prueba', description: null, is_active: true }
export const inactiveType = { id: 4, name: 'Golpe de prueba', description: null, is_active: false }
export const priorities = [{ value: 'low', label: 'Baja' }, { value: 'medium', label: 'Media' }, { value: 'high', label: 'Alta' }, { value: 'critical', label: 'Crítica' }]
export const evidence = { id: 31, original_name: 'defecto-puerta.jpg', mime_type: 'image/jpeg', size_bytes: 1000,
  uploaded_by: { id: 1, name: 'Técnico' }, view_url: '/api/diagnoses/12/defects/45/evidences/31' }
export const defect = { id: 45, diagnosis_id: 12, defect_type: type, location: 'Puerta delantera derecha', priority: 'high', priority_label: 'Alta',
  description: 'Rayón profundo.', created_by: { id: 1, name: 'Técnico' }, created_at: '2026-11-02T12:00:00Z', evidences: [] }
export const diagnosis = { id: 12, unit: { id: unit.id, vin: unit.vin, model: unit.model }, notes: 'Daños detectados.',
  diagnosed_by: { id: 1, name: 'Técnico' }, defect_count: 1, defects: [defect], created_at: '2026-11-02T12:00:00Z', updated_at: '2026-11-02T12:00:00Z' }
export const currentUser = { id: 1, name: 'Development User', email: 'developer@example.test', roles: ['admin'],
  permissions: ['units.view', 'units.create', 'units.update', 'diagnosis.view', 'diagnosis.create', 'diagnosis.update', 'diagnosis.manage_catalog'] }
export const collection = (data = [diagnosis], meta = {}) => ({ data, links: {}, meta: { current_page: 1, last_page: 1, total: data.length, per_page: 20, ...meta } })
export const httpError = (status, data = {}) => ({ response: { status, data } })