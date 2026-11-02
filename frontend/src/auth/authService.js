import api, { backendBaseUrl } from '../services/api'

const authService = {
  async currentUser() {
    const { data } = await api.get('/auth/me')
    return data.user
  },

  async login(credentials) {
    await api.get('/sanctum/csrf-cookie', { baseURL: backendBaseUrl })
    await api.post('/auth/login', credentials)
    return authService.currentUser()
  },

  async logout() {
    await api.post('/auth/logout')
  },
}

export default authService
