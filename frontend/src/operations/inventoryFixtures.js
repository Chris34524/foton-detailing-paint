import { material as baseMaterial } from './operationsFixtures'
export const inventoryMeta = {
  units: [{ value: 'Kg', label: 'Kg', family: 'mass', factor: '1000' }, { value: 'gr', label: 'gr', family: 'mass', factor: '1' },
    { value: 'L', label: 'L', family: 'volume', factor: '1000' }, { value: 'ml', label: 'ml', family: 'volume', factor: '1' },
    { value: 'mts', label: 'mts', family: 'length', factor: '100' }, { value: 'cm', label: 'cm', family: 'length', factor: '1' },
    { value: 'Pza', label: 'Pza', family: 'pieces', factor: '1' }, { value: 'hojas', label: 'hojas', family: 'sheets', factor: '1' }],
  movement_types: [{ value: 'opening_balance', label: 'APERTURA', increases: true }, { value: 'entry', label: 'ENTRADA', increases: true },
    { value: 'exit', label: 'SALIDA', increases: false }, { value: 'adjustment_in', label: 'AJUSTE +', increases: true, requires_justification: true },
    { value: 'adjustment_out', label: 'AJUSTE -', increases: false, requires_justification: true }],
  areas: ['Pintura 1', 'Pintura 2', 'Lavado'],
  stock_statuses: [{ value: 'critical', label: 'CRÍTICO' }, { value: 'reorder', label: 'REORDEN' }, { value: 'ok', label: 'OK' }, { value: 'overstock', label: 'SOBRESTOCK' }, { value: 'no_parameters', label: 'SIN PARÁMETROS' }],
}
inventoryMeta.allowed_movement_types = inventoryMeta.movement_types
export const inventoryMaterial = { ...baseMaterial, unit: 'L', minimum_stock: '6.000000', maximum_stock: '10.000000', stock_actual: '5.000000', initial_stock: '5.000000',
  entries: '0.000000', exits: '0.000000', stock_status: 'critical', stock_status_label: 'CRÍTICO', stock_percentage: '50.00', recommended_action: 'COMPRAR / REABASTECER',
  location: 'Laboratorio de Pintura', supplier: 'Proveedor de prueba', has_inventory_history: true, unit_supported: true }
export const preview = { quantity_equivalent: '0.750000', unit_inventory: 'L', stock_before: '5.000000', stock_after: '4.250000', sufficient_stock: true, opening_allowed: true, can_confirm: true }
export const movement = { id: 10, folio: 'LDR-DP-0001', material: { id: 5, code: inventoryMaterial.code, name: inventoryMaterial.name, unit: 'L' },
  movement_type: 'exit', movement_type_label: 'SALIDA', quantity_original: '750.000000', unit_original: 'ml', quantity_equivalent: '0.750000',
  signed_quantity: '-0.750000', stock_before: '5.000000', stock_after: '4.250000', performed_by: { id: 7, name: 'Admin de prueba' },
  area_destination: 'Pintura 1', notes: 'Aplicado en cabina', occurred_at: '2026-11-03T16:00:00Z', source_kind: 'manual' }
