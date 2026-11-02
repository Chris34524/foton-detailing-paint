import { afterEach, describe, expect, it, vi } from 'vitest'
import api from '../services/api'
import diagnosisService from './diagnosisService'

afterEach(() => vi.restoreAllMocks())
describe('diagnosis HTTP contracts', () => {
  it('uses paginated global and unit endpoints with validated query parameters', async () => {
    const get = vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [], meta: { total: 0 } } })
    await diagnosisService.list({ search: 'VIN', page: '2' })
    expect(get).toHaveBeenCalledWith('/diagnoses', { params: { search: 'VIN', page: '2' } })
    await diagnosisService.list({ per_page: 20 }, 7)
    expect(get).toHaveBeenLastCalledWith('/units/7/diagnoses', { params: { per_page: 20 } })
  })
  it('unwraps diagnosis resources for create, detail and update', async () => {
    const resource = { id: 12 }
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: resource } })
    const get = vi.spyOn(api, 'get').mockResolvedValue({ data: { data: resource } })
    const patch = vi.spyOn(api, 'patch').mockResolvedValue({ data: { data: resource } })
    expect(await diagnosisService.create(7, { notes: null })).toEqual(resource)
    expect(post).toHaveBeenCalledWith('/units/7/diagnoses', { notes: null })
    expect(await diagnosisService.get(12)).toEqual(resource)
    expect(get).toHaveBeenCalledWith('/diagnoses/12', {})
    expect(await diagnosisService.update(12, { notes: 'Updated' })).toEqual(resource)
    expect(patch).toHaveBeenCalledWith('/diagnoses/12', { notes: 'Updated' })
  })
  it('uses nested defect mutation endpoints', async () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: { id: 45 } } })
    const patch = vi.spyOn(api, 'patch').mockResolvedValue({ data: { data: { id: 45 } } })
    await diagnosisService.createDefect(12, { priority: 'high' })
    await diagnosisService.updateDefect(12, 45, { location: 'Cabina' })
    expect(post).toHaveBeenCalledWith('/diagnoses/12/defects', { priority: 'high' })
    expect(patch).toHaveBeenCalledWith('/diagnoses/12/defects/45', { location: 'Cabina' })
  })
  it('loads authoritative priority metadata and bounded catalog responses', async () => {
    const get = vi.spyOn(api, 'get').mockResolvedValueOnce({ data: { priorities: [{ value: 'high', label: 'Alta' }] } })
      .mockResolvedValueOnce({ data: { data: [], meta: { total: 0 } } })
    expect(await diagnosisService.meta()).toEqual([{ value: 'high', label: 'Alta' }])
    await diagnosisService.types({ include_inactive: 1, per_page: 20 })
    expect(get).toHaveBeenLastCalledWith('/defect-types', { params: { include_inactive: 1, per_page: 20 } })
  })
  it('creates and updates catalog entries without a delete API', async () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: { id: 3 } } })
    const patch = vi.spyOn(api, 'patch').mockResolvedValue({ data: { data: { id: 3 } } })
    await diagnosisService.createType({ name: 'Scratch' })
    await diagnosisService.updateType(3, { is_active: false })
    expect(post).toHaveBeenCalledWith('/defect-types', { name: 'Scratch' })
    expect(patch).toHaveBeenCalledWith('/defect-types/3', { is_active: false })
  })
  it('uploads multipart files through the existing credentialed client without overriding the boundary', async () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: [{ id: 31 }] } })
    const file = new File(['image'], 'image.jpg', { type: 'image/jpeg' })
    const onUploadProgress = vi.fn()
    expect(await diagnosisService.upload(12, 45, [file], { onUploadProgress })).toEqual([{ id: 31 }])
    const [path, body, options] = post.mock.calls[0]
    expect(path).toBe('/diagnoses/12/defects/45/evidences')
    expect(body).toBeInstanceOf(FormData)
    expect(body.getAll('files[]')).toEqual([file])
    expect(options).toEqual({ onUploadProgress })
    expect(api.defaults.withCredentials).toBe(true)
    expect(api.defaults.withXSRFToken).toBe(true)
  })
  it('retrieves private image blobs from the configured API origin', async () => {
    const blob = new Blob(['image'], { type: 'image/jpeg' })
    const get = vi.spyOn(api, 'get').mockResolvedValue({ data: blob })
    expect(await diagnosisService.image('/api/diagnoses/12/defects/45/evidences/31')).toBe(blob)
    expect(get).toHaveBeenCalledWith('/diagnoses/12/defects/45/evidences/31', { responseType: 'blob' })
  })
  it('rejects arbitrary evidence URLs before sending credentials', async () => {
    const get = vi.spyOn(api, 'get')
    await expect(diagnosisService.image('https://untrusted.example/evidence')).rejects.toThrow('Invalid evidence endpoint')
    expect(get).not.toHaveBeenCalled()
  })
})