import api from '../services/api'

export const normalizeVin = (vin) => vin.trim().toUpperCase()
export const isValidVin = (vin) => /^[A-HJ-NPR-Z0-9]{17}$/.test(normalizeVin(vin))

const unitService = {
  async list(params = {}, options = {}) {
    const response = await api.get('/units', { ...options, params })
    return response.data
  },
  async meta(options = {}) {
    const response = await api.get('/units/meta', options)
    return response.data.statuses
  },
  async get(id, options = {}) {
    const response = await api.get('/units/' + encodeURIComponent(id), options)
    return response.data.data
  },
  async findByVin(vin, options = {}) {
    const response = await api.get('/units/by-vin/' + encodeURIComponent(normalizeVin(vin)), options)
    return response.data.data
  },
  async create(payload) {
    const response = await api.post('/units', { ...payload, vin: normalizeVin(payload.vin) })
    return response.data.data
  },
  async update(id, payload) {
    const response = await api.patch('/units/' + encodeURIComponent(id), payload)
    return response.data.data
  },
}
export default unitService