export function unitErrorMessage(error, fallback = 'No fue posible cargar las unidades. Intenta de nuevo.') {
  const status = error?.response?.status
  if (status === 403) return 'No tienes permiso para realizar esta acción.'
  if (status === 404) return 'Unidad no encontrada.'
  if (status === 422) return 'Revisa los datos ingresados.'
  return fallback
}
export const formatUnitDate = (date) => date ? new Intl.DateTimeFormat('es-MX', {
  dateStyle: 'medium', timeStyle: 'short',
}).format(new Date(date)) : '—'