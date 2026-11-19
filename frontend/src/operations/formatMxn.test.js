import { describe, expect, it } from 'vitest'
import { formatMxn } from './formatMxn'

describe('MXN cost display', () => {
  it('preserves exact decimal prices without redundant trailing zeroes', () => {
    expect(formatMxn('125.500000', { maximumFractionDigits: 6 })).toBe('$125.50 MXN')
    expect(formatMxn('0.000001', { maximumFractionDigits: 6 })).toBe('$0.000001 MXN')
    expect(formatMxn('0.125000', { maximumFractionDigits: 6 })).toBe('$0.125 MXN')
    expect(formatMxn('0.000000', { maximumFractionDigits: 6 })).toBe('$0.00 MXN')
  })
  it('formats stock value as money and handles unknown prices', () => {
    expect(formatMxn('313.75')).toBe('$313.75 MXN')
    expect(formatMxn('1234567.50')).toBe('$1,234,567.50 MXN')
    expect(formatMxn('99999999999999.99')).toBe('$99,999,999,999,999.99 MXN')
    expect(formatMxn(null)).toBe('—')
  })
})
