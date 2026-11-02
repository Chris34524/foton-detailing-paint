import api from '../services/api'
const diagnosisPath = (id) => '/diagnoses/' + encodeURIComponent(id)
const defectPath = (diagnosisId, defectId) => diagnosisPath(diagnosisId) + '/defects/' + encodeURIComponent(defectId)
const diagnosisService = {
  async list(params = {}, unitId = null, options = {}) {
    const path = unitId ? '/units/' + encodeURIComponent(unitId) + '/diagnoses' : '/diagnoses'
    return (await api.get(path, { ...options, params })).data
  },
  async get(id, options = {}) { return (await api.get(diagnosisPath(id), options)).data.data },
  async create(unitId, payload) { return (await api.post('/units/' + encodeURIComponent(unitId) + '/diagnoses', payload)).data.data },
  async update(id, payload) { return (await api.patch(diagnosisPath(id), payload)).data.data },
  async meta(options = {}) { return (await api.get('/diagnosis/meta', options)).data.priorities },
  async types(params = {}, options = {}) { return (await api.get('/defect-types', { ...options, params })).data },
  async createType(payload) { return (await api.post('/defect-types', payload)).data.data },
  async updateType(id, payload) { return (await api.patch('/defect-types/' + encodeURIComponent(id), payload)).data.data },
  async createDefect(diagnosisId, payload) { return (await api.post(diagnosisPath(diagnosisId) + '/defects', payload)).data.data },
  async updateDefect(diagnosisId, defectId, payload) { return (await api.patch(defectPath(diagnosisId, defectId), payload)).data.data },
  async upload(diagnosisId, defectId, files, options = {}) {
    const body = new FormData()
    files.forEach((file) => body.append('files[]', file))
    return (await api.post(defectPath(diagnosisId, defectId) + '/evidences', body, options)).data.data
  },
  async image(viewUrl, options = {}) {
    if (!/^\/api\/diagnoses\/\d+\/defects\/\d+\/evidences\/\d+$/.test(viewUrl)) throw new Error('Invalid evidence endpoint')
    return (await api.get(viewUrl.slice(4), { ...options, responseType: 'blob' })).data
  },
}
export default diagnosisService