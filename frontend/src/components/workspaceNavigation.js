import { unitPermissions } from '../units/permissions'
import { diagnosisPermissions } from '../diagnosis/permissions'
import { workOrderPermissions } from '../workOrders/permissions'
import { operationsPermissions } from '../operations/permissions'
export const workspaceModules = [
  { to: '/units', label: 'Unidades', icon: 'units', permission: unitPermissions.view, group: 'Operación', description: 'Consulta el VIN, la ubicación y el estado de cada unidad.', action: 'Consultar unidades' },
  { to: '/diagnoses', label: 'Diagnóstico', icon: 'diagnosis', permission: diagnosisPermissions.view, group: 'Operación', description: 'Documenta defectos, prioridades y evidencias de la unidad.', action: 'Consultar diagnósticos' },
  { to: '/work-orders', label: 'Trabajos', icon: 'work', permission: workOrderPermissions.view, group: 'Operación', description: 'Organiza la cola de trabajo, las prioridades y las asignaciones.', action: 'Consultar trabajos' },
  { to: '/processes', label: 'Procesos', icon: 'process', permission: operationsPermissions.processesView, group: 'Recursos y calidad', description: 'Consulta el catálogo y la secuencia de los trabajos.', action: 'Consultar procesos' },
  { to: '/materials', label: 'Materiales', icon: 'material', permission: operationsPermissions.materialsView, group: 'Recursos y calidad', description: 'Controla existencias, movimientos, consumos y alertas de inventario.', action: 'Consultar materiales' },
  { to: '/inspections', label: 'Inspección', icon: 'inspection', permission: operationsPermissions.inspectionView, group: 'Recursos y calidad', description: 'Revisa la calidad, los retrabajos y la liberación de unidades.', action: 'Consultar inspecciones' },
]
export const roleLabels = { super_admin: 'Superadministrador', admin: 'Administrador', supervisor: 'Supervisor', operator: 'Operador', inspector: 'Inspector' }
export function workspaceSection(pathname) {
  if (pathname.startsWith('/inspection/')) return 'Inspección'
  if (pathname.startsWith('/diagnosis/')) return 'Diagnóstico'
  return workspaceModules.find((module) => pathname === module.to || pathname.startsWith(module.to + '/'))?.label || 'Inicio'
}
