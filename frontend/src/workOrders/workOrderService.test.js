import { beforeEach, describe, expect, it, vi } from 'vitest'
import api from '../services/api'
import workOrderService from './workOrderService'
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }))
beforeEach(() => { vi.resetAllMocks() })
describe('work order API contracts', () => {
  it('keeps server pagination and queue selection', async () => {
    const collection = { data: [], meta: { total: 0 } }
    api.get.mockResolvedValue({ data: collection })
    const controller = new AbortController()
    expect(await workOrderService.list({ priority: 'high', page: 2 }, true, { signal: controller.signal })).toEqual(collection)
    expect(api.get).toHaveBeenCalledWith('/work-orders/queue', { params: { priority: 'high', page: 2 }, signal: controller.signal })
    await workOrderService.list()
    expect(api.get).toHaveBeenLastCalledWith('/work-orders', { params: {} })
  })
  it('loads detail, metadata and bounded operator search', async () => {
    api.get.mockResolvedValue({ data: { data: { id: 1 } } })
    expect(await workOrderService.get(1)).toEqual({ id: 1 })
    expect(api.get).toHaveBeenLastCalledWith('/work-orders/1', {})
    await workOrderService.meta()
    expect(api.get).toHaveBeenLastCalledWith('/work-orders/meta', {})
    await workOrderService.operators({ search: 'Demo', page: 2 })
    expect(api.get).toHaveBeenLastCalledWith('/work-orders/operators', { params: { search: 'Demo', page: 2 } })
  })
  it('generates against the source diagnosis with only optional notes', async () => {
    api.post.mockResolvedValue({ data: { data: { id: 9 } } })
    expect(await workOrderService.generate(12, { notes: 'Trabajo' })).toEqual({ id: 9 })
    expect(api.post).toHaveBeenCalledWith('/diagnoses/12/work-order', { notes: 'Trabajo' })
  })
  it('keeps general updates, assignment and status on dedicated endpoints', async () => {
    api.patch.mockResolvedValue({ data: { data: { id: 9 } } })
    await workOrderService.update(9, { priority: 'critical', notes: null })
    expect(api.patch).toHaveBeenLastCalledWith('/work-orders/9', { priority: 'critical', notes: null })
    await workOrderService.assign(9, 24)
    expect(api.patch).toHaveBeenLastCalledWith('/work-orders/9/assignment', { assigned_to: 24 })
    await workOrderService.assign(9, null)
    expect(api.patch).toHaveBeenLastCalledWith('/work-orders/9/assignment', { assigned_to: null })
    await workOrderService.status(9, 'blocked')
    expect(api.patch).toHaveBeenLastCalledWith('/work-orders/9/status', { status: 'blocked' })
  })
  it('propagates validation and authorization failures to the UI', async () => {
    const error = { response: { status: 422, data: { errors: { status: ['Transición no permitida.'] } } } }
    api.patch.mockRejectedValue(error)
    await expect(workOrderService.status(9, 'completed')).rejects.toBe(error)
  })
})
