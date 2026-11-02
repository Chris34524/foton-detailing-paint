export function diagnosisError(error, fallback = 'No fue posible completar la operación. Revisa la conexión e intenta de nuevo.') {
  const status = error?.response?.status
  if (status === 403) return 'No tienes permiso para realizar esta acción.'
  if (status === 404) return 'Registro no encontrado.'
  if (status === 422) return 'Revisa los datos ingresados.'
  if (status === 413) return 'La carga supera el tamaño permitido. Usa como máximo cinco imágenes de 10 MB.'
  return fallback
}
export const validationErrors = (error) => error?.response?.status === 422 ? error.response.data.errors ?? {} : {}