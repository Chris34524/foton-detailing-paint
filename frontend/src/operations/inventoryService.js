import api from '../services/api'
const materialPath = (id) => '/materials/' + encodeURIComponent(id)
const inventoryService = {
  async meta(options = {}) { return (await api.get('/materials/meta', options)).data },
  async stock(id, options = {}) { return (await api.get(materialPath(id) + '/stock', options)).data.data },
  async preview(id, params, options = {}) { return (await api.get(materialPath(id) + '/quantity-preview', { ...options, params })).data },
  async movements(params = {}, options = {}) { return (await api.get('/inventory-movements', { ...options, params })).data },
  async confirm(payload) { return (await api.post('/inventory-movements', payload)).data.data },
  async movement(id, options = {}) { return (await api.get('/inventory-movements/' + encodeURIComponent(id), options)).data.data },
  async dashboard(params = {}, options = {}) { return (await api.get('/materials/inventory/dashboard', { ...options, params })).data },
}
export default inventoryService
