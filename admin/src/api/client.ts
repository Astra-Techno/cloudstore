import axios from 'axios'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('admin_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Tunnel PUT/DELETE/PATCH as POST to avoid shared hosting WAF blocks
  const method = config.method?.toUpperCase()
  if (method === 'PUT' || method === 'DELETE' || method === 'PATCH') {
    config.headers['X-HTTP-Method-Override'] = method
    config.method = 'post'
    if (method === 'DELETE' && !config.data) {
      config.data = {}
    }
  }
  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !window.location.pathname.includes('/login')) {
      localStorage.removeItem('admin_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  },
)

export default apiClient
