/**
 * Render an exact decimal quantity without unnecessary trailing zeroes.
 * Keep the backend's six-decimal storage precision untouched.
 */
export function formatQuantity(value) {
  if (value == null || value === '') return '—'
  const text = String(value)
  if (!/^-?\d+(?:\.\d+)?$/.test(text)) return text
  if (!text.includes('.')) return text
  return text.replace(/0+$/, '').replace(/\.$/, '')
}
