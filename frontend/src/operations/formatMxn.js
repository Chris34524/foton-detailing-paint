/** Currency presentation that preserves decimal strings and their integer precision. */
export function formatMxn(value, { maximumFractionDigits = 2 } = {}) {
  if (value == null || value === '') return '—'
  const text = String(value)
  if (!/^\d+(?:\.\d+)?$/.test(text)) return '—'
  const [whole, fractional = ''] = text.split('.')
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  const trimmed = fractional.slice(0, maximumFractionDigits).replace(/0+$/, '')
  const decimals = trimmed.padEnd(Math.min(2, maximumFractionDigits), '0')
  return '$' + grouped + (decimals ? '.' + decimals : '') + ' MXN'
}
