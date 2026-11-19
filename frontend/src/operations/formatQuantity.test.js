import { describe, expect, it } from 'vitest'
import { formatQuantity } from './formatQuantity'

describe('inventory quantity formatting', () => {
  it('trims redundant decimal zeros without changing meaningful precision', () => {
    expect(formatQuantity('60.000000')).toBe('60')
    expect(formatQuantity('5.500000')).toBe('5.5')
    expect(formatQuantity('0.750000')).toBe('0.75')
    expect(formatQuantity('0.000001')).toBe('0.000001')
    expect(formatQuantity('-1.000000')).toBe('-1')
    expect(formatQuantity(null)).toBe('—')
  })
})
