import { beforeEach, expect, it, vi } from 'vitest'
import api from '../services/api'
import inventoryService from './inventoryService'
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
beforeEach(() => { vi.resetAllMocks(); api.get.mockResolvedValue({ data: { data: { id: 1 } } }); api.post.mockResolvedValue({ data: { data: { id: 10 } } }) })
it('reads central metadata, stock and exact previews with cancellation', async () => {
  const signal = new AbortController().signal
  await inventoryService.meta({ signal })
  expect(api.get).toHaveBeenLastCalledWith('/materials/meta', { signal })
  await inventoryService.stock(5, { signal })
  expect(api.get).toHaveBeenLastCalledWith('/materials/5/stock', { signal })
  const params = { quantity_original: '750.000001', unit_original: 'ml', movement_type: 'exit' }
  await inventoryService.preview(5, params, { signal })
  expect(api.get).toHaveBeenLastCalledWith('/materials/5/quantity-preview', { signal, params })
})
it('forwards movement filters and unwraps the immutable receipt', async () => {
  const params = { material_id: 5, movement_type: 'exit', area_destination: 'Lavado', page: 2 }
  await inventoryService.movements(params)
  expect(api.get).toHaveBeenLastCalledWith('/inventory-movements', { params })
  await inventoryService.movement(10)
  expect(api.get).toHaveBeenLastCalledWith('/inventory-movements/10', {})
  const payload = { material_id: 5, movement_type: 'exit', quantity_original: '750', unit_original: 'ml', idempotency_key: crypto.randomUUID() }
  expect(await inventoryService.confirm(payload)).toEqual({ id: 10 })
  expect(api.post).toHaveBeenLastCalledWith('/inventory-movements', payload)
})
it('reads paginated dashboard alerts and encodes resource IDs', async () => {
  await inventoryService.dashboard({ page: 2 })
  expect(api.get).toHaveBeenLastCalledWith('/materials/inventory/dashboard', { params: { page: 2 } })
  await inventoryService.movement('a/b')
  expect(api.get).toHaveBeenLastCalledWith('/inventory-movements/a%2Fb', {})
})
