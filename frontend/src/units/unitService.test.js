import { afterEach, describe, expect, it, vi } from 'vitest'
import api from '../services/api'
import unitService from './unitService'

afterEach(() => vi.restoreAllMocks())
describe('unit HTTP contracts', () => {
  it('normalizes VIN for both registration and lookup through the existing credentialed client', async () => {
    const unit = { id: 1, vin: 'LVBV3JBB7RA123456' }
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: unit } })
    const get = vi.spyOn(api, 'get').mockResolvedValue({ data: { data: unit } })
    expect(await unitService.create({ vin: ' lvbv3jbb7ra123456 ', model: 'S5' })).toEqual(unit)
    expect(post).toHaveBeenCalledWith('/units', { vin: unit.vin, model: 'S5' })
    expect(await unitService.findByVin(' lvbv3jbb7ra123456 ')).toEqual(unit)
    expect(get).toHaveBeenCalledWith('/units/by-vin/' + unit.vin, {})
  })
  it('passes list filters and cancellation without client pagination', async () => {
    const response = { data: [{ id: 1 }], meta: { total: 200 } }
    const get = vi.spyOn(api, 'get').mockResolvedValue({ data: response })
    const signal = new AbortController().signal
    expect(await unitService.list({ status: 'registered', page: '2' }, { signal })).toEqual(response)
    expect(get).toHaveBeenCalledWith('/units', { params: { status: 'registered', page: '2' }, signal })
  })
  it('reads status options from the meta API and unwraps detail resources', async () => {
    vi.spyOn(api, 'get').mockResolvedValueOnce({ data: { statuses: [{ value: 'registered', label: 'Registrada' }] } })
      .mockResolvedValueOnce({ data: { data: { id: 1 } } })
    expect(await unitService.meta()).toEqual([{ value: 'registered', label: 'Registrada' }])
    expect(await unitService.get(1)).toEqual({ id: 1 })
  })
  it('uses PATCH and unwraps the updated resource', async () => {
    const patch = vi.spyOn(api, 'patch').mockResolvedValue({ data: { data: { id: 1, status: 'in_process' } } })
    expect(await unitService.update(1, { status: 'in_process' })).toEqual({ id: 1, status: 'in_process' })
    expect(patch).toHaveBeenCalledWith('/units/1', { status: 'in_process' })
  })
})