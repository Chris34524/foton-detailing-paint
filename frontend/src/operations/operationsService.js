import api from '../services/api'
const catalogPaths = { processes: '/processes', materials: '/materials', checklists: '/inspection/checklists' }
const catalogPath = (kind) => { if (!catalogPaths[kind]) throw new Error('Unknown catalog'); return catalogPaths[kind] }
const id = (value) => encodeURIComponent(value)
const operationsService = {
  async catalog(kind, params = {}, options = {}) { return (await api.get(catalogPath(kind), { ...options, params })).data },
  async saveCatalog(kind, recordId, payload) { const path = catalogPath(kind); return (await (recordId ? api.patch(path + '/' + id(recordId), payload) : api.post(path, payload))).data.data },
  async checklist(recordId, options = {}) { return (await api.get('/inspection/checklists/' + id(recordId), options)).data.data },
  async saveItem(checklistId, itemId, payload) { const path = '/inspection/checklists/' + id(checklistId) + '/items'; return (await (itemId ? api.patch(path + '/' + id(itemId), payload) : api.post(path, payload))).data.data },
  async orderItems(checklistId, itemIds) { return (await api.put('/inspection/checklists/' + id(checklistId) + '/item-order', { item_ids: itemIds })).data.data },
  async sequence(options = {}) { return (await api.get('/process-sequence', options)).data.data },
  async saveSequence(processIds) { return (await api.put('/process-sequence', { process_ids: processIds })).data.data },
  async flow(workOrderId, options = {}) { return (await api.get('/work-orders/' + id(workOrderId) + '/process-flow', options)).data },
  async initialize(workOrderId) { return (await api.post('/work-orders/' + id(workOrderId) + '/process-flow/initialize')).data },
  async consumptions(params = {}, workOrderId = null, options = {}) { return (await api.get(workOrderId ? '/work-orders/' + id(workOrderId) + '/material-consumptions' : '/material-consumptions', { ...options, params })).data },
  async consume(workOrderId, payload) { return (await api.post('/work-orders/' + id(workOrderId) + '/material-consumptions', payload)).data.data },
  async inspections(params = {}, workOrderId = null, options = {}) { return (await api.get(workOrderId ? '/work-orders/' + id(workOrderId) + '/inspections' : '/inspections', { ...options, params })).data },
  async eligibleOrders(params = {}, options = {}) { return (await api.get('/inspection/work-orders', { ...options, params })).data },
  async createInspection(workOrderId, payload) { return (await api.post('/work-orders/' + id(workOrderId) + '/inspections', payload)).data.data },
  async inspection(inspectionId, options = {}) { return (await api.get('/inspections/' + id(inspectionId), options)).data.data },
  async saveInspection(inspectionId, payload) { return (await api.patch('/inspections/' + id(inspectionId), payload)).data.data },
  async complete(inspectionId, payload) { return (await api.post('/inspections/' + id(inspectionId) + '/complete', payload)).data.data },
  async release(inspectionId, payload) { return (await api.post('/inspections/' + id(inspectionId) + '/release', payload)).data.data },
}
export default operationsService
