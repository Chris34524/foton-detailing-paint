import axios from 'axios'

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080/api').replace(/\/+$/, '')
const sessionExpiredListeners = new Set()

export const backendBaseUrl = apiBaseUrl.replace(/\/api$/, '')

const api = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
  timeout: 10000,
})

// Notify the provider without retrying requests or initiating another session lookup.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if ([401, 419].includes(error.response?.status)) {
      sessionExpiredListeners.forEach((listener) => listener())
    }
    return Promise.reject(error)
  },
)

export function onSessionExpired(listener) {
  sessionExpiredListeners.add(listener)
  return () => sessionExpiredListeners.delete(listener)
}

export default api
