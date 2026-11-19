import api from '../services/api'

const id = (value) => encodeURIComponent(value)
const formulaPath = (formulaId) => '/paint-formulas/' + id(formulaId)

const paintService = {
  async list(params = {}, options = {}) {
    return (await api.get('/paint-formulas', { ...options, params })).data
  },
  async get(formulaId, options = {}) {
    return (await api.get(formulaPath(formulaId), options)).data.data
  },
  async versions(formulaId, options = {}) {
    return (await api.get(formulaPath(formulaId) + '/versions', options)).data.data
  },
  async create(payload) {
    return (await api.post('/paint-formulas', payload)).data.data
  },
  async revise(formulaId, payload) {
    return (await api.post(formulaPath(formulaId) + '/versions', payload)).data.data
  },
  async preview(formulaId, quantityGrams, options = {}) {
    return (await api.post(formulaPath(formulaId) + '/preview', { quantity_grams: quantityGrams }, options)).data
  },
  async prepare(formulaId, payload) {
    return (await api.post(formulaPath(formulaId) + '/prepare', payload)).data.data
  },
  async preparations(params = {}, options = {}) {
    return (await api.get('/paint-preparations', { ...options, params })).data
  },
}

export default paintService
