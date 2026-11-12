import { beforeEach, describe, expect, it, vi } from 'vitest'
import api from '../services/api'
import operationsService from './operationsService'
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), put: vi.fn() } }))
beforeEach(() => { vi.resetAllMocks(); api.get.mockResolvedValue({ data: { data: [] } }); api.post.mockResolvedValue({ data: { data: { id: 1 } } }); api.patch.mockResolvedValue({ data: { data: { id: 1 } } }); api.put.mockResolvedValue({ data: { data: [] } }) })
describe('operations API contracts', () => {
  it('uses allowlisted catalog routes and forwards server pagination and cancellation', async () => {
    const controller = new AbortController()
    await operationsService.catalog('materials', { page: 2, active: 1 }, { signal: controller.signal })
    expect(api.get).toHaveBeenLastCalledWith('/materials', { params: { page: 2, active: 1 }, signal: controller.signal })
    await operationsService.saveCatalog('processes', null, { code: 'TEST', name: 'Prueba' })
    expect(api.post).toHaveBeenLastCalledWith('/processes', { code: 'TEST', name: 'Prueba' })
    await operationsService.saveCatalog('checklists', 9, { is_active: false })
    expect(api.patch).toHaveBeenLastCalledWith('/inspection/checklists/9', { is_active: false })
    await expect(operationsService.catalog('unknown')).rejects.toThrow('Unknown catalog')
  })
  it('keeps process sequence and work order snapshots on dedicated endpoints', async () => {
    await operationsService.saveSequence([2, 1])
    expect(api.put).toHaveBeenLastCalledWith('/process-sequence', { process_ids: [2, 1] })
    await operationsService.flow(15)
    expect(api.get).toHaveBeenLastCalledWith('/work-orders/15/process-flow', {})
    await operationsService.initialize(15)
    expect(api.post).toHaveBeenLastCalledWith('/work-orders/15/process-flow/initialize')
  })
  it('consumes against a Work Order and queries history without duplicating VIN in writes', async () => {
    const payload = { material_id: 5, quantity: '1.750', work_order_process_step_id: 51, notes: null }
    await operationsService.consume(15, payload)
    expect(api.post).toHaveBeenLastCalledWith('/work-orders/15/material-consumptions', payload)
    await operationsService.consumptions({ vin: 'LVBV3JBB7RA123456' })
    expect(api.get).toHaveBeenLastCalledWith('/material-consumptions', { params: { vin: 'LVBV3JBB7RA123456' } })
  })
  it('uses nested checklist item contracts and explicit reorder', async () => {
    await operationsService.saveItem(9, null, { code: 'A', label: 'Prueba' })
    expect(api.post).toHaveBeenLastCalledWith('/inspection/checklists/9/items', { code: 'A', label: 'Prueba' })
    await operationsService.saveItem(9, 91, { is_active: false })
    expect(api.patch).toHaveBeenLastCalledWith('/inspection/checklists/9/items/91', { is_active: false })
    await operationsService.orderItems(9, [92, 91])
    expect(api.put).toHaveBeenLastCalledWith('/inspection/checklists/9/item-order', { item_ids: [92, 91] })
  })
  it('separates draft answers, completion and validated release', async () => {
    await operationsService.createInspection(15, { inspection_checklist_id: 9 })
    expect(api.post).toHaveBeenLastCalledWith('/work-orders/15/inspections', { inspection_checklist_id: 9 })
    await operationsService.saveInspection(100, { items: [{ id: 191, result: 'compliant' }] })
    expect(api.patch).toHaveBeenLastCalledWith('/inspections/100', { items: [{ id: 191, result: 'compliant' }] })
    await operationsService.complete(100, { rework_cause: 'Corregir' })
    expect(api.post).toHaveBeenLastCalledWith('/inspections/100/complete', { rework_cause: 'Corregir' })
    await operationsService.release(100, { notes: null })
    expect(api.post).toHaveBeenLastCalledWith('/inspections/100/release', { notes: null })
  })
})
