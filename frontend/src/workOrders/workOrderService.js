import api from '../services/api'
const path = (id) => '/work-orders/' + encodeURIComponent(id)
const workOrderService = {
  async list(params = {}, queue = false, options = {}) {
    return (await api.get(queue ? '/work-orders/queue' : '/work-orders', { ...options, params })).data
  },
  async get(id, options = {}) { return (await api.get(path(id), options)).data.data },
  async meta(options = {}) { return (await api.get('/work-orders/meta', options)).data },
  async operators(params = {}, options = {}) { return (await api.get('/work-orders/operators', { ...options, params })).data },
  async generate(diagnosisId, payload) { return (await api.post('/diagnoses/' + encodeURIComponent(diagnosisId) + '/work-order', payload)).data.data },
  async update(id, payload) { return (await api.patch(path(id), payload)).data.data },
  async assign(id, assignedTo) { return (await api.patch(path(id) + '/assignment', { assigned_to: assignedTo })).data.data },
  async status(id, status) { return (await api.patch(path(id) + '/status', { status })).data.data },
}
export default workOrderService
