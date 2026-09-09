import apiClient from './client'

export const platformApi = {
  // Dashboard
  getDashboard: () => apiClient.get('/platform/dashboard'),

  // Tenants
  getTenants: (status?: string) => apiClient.get('/platform/tenants', { params: { status } }),
  getTenant: (uuid: string) => apiClient.get(`/platform/tenants/${uuid}`),
  createTenant: (data: Record<string, unknown>) => apiClient.post('/platform/tenants', data),
  updateTenant: (uuid: string, data: Record<string, unknown>) => apiClient.put(`/platform/tenants/${uuid}`, data),

  // Capabilities
  updateCapabilities: (uuid: string, capabilities: Record<string, boolean>) =>
    apiClient.put(`/platform/tenants/${uuid}/capabilities`, { capabilities }),

  // Tenant admins
  getTenantAdmins: (uuid: string) => apiClient.get(`/platform/tenants/${uuid}/admins`),
  createTenantAdmin: (uuid: string, data: Record<string, unknown>) =>
    apiClient.post(`/platform/tenants/${uuid}/admins`, data),

  // App builds
  getBuilds: (uuid: string) => apiClient.get(`/platform/tenants/${uuid}/builds`),
  triggerBuild: (uuid: string, data: Record<string, unknown>) =>
    apiClient.post(`/platform/tenants/${uuid}/builds`, data),
  getBuild: (buildUuid: string) => apiClient.get(`/platform/builds/${buildUuid}`),
}
