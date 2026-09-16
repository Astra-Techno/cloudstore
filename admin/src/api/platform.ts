import apiClient from './client'

export const platformApi = {
  getConfig: (tenantId = 0) => apiClient.get('/platform/config', { params: { tenant_id: tenantId } }),
  updateConfig: (tenantId: number, settings: Record<string, string>) => apiClient.post('/platform/config', { tenant_id: tenantId, settings }),
  // Dashboard
  getDashboard: () => apiClient.get('/platform/dashboard'),
  getFeeLedger: (status?: 'accrued' | 'settled') => apiClient.get('/platform/fees', { params: { status } }),
  settleFeeLedger: (uuid: string) => apiClient.post(`/platform/fees/${uuid}/settle`),

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
  regenerateToken: (uuid: string) =>
    apiClient.post(`/platform/tenants/${uuid}/regenerate-token`),

  // App builds
  getBuilds: (uuid: string) => apiClient.get(`/platform/tenants/${uuid}/builds`),
  triggerBuild: (uuid: string, data: Record<string, unknown>) =>
    apiClient.post(`/platform/tenants/${uuid}/builds`, data),
  getBuild: (buildUuid: string) => apiClient.get(`/platform/builds/${buildUuid}`),
  fetchArtifact: (buildUuid: string) =>
    apiClient.post(`/platform/builds/${buildUuid}/fetch-artifact`),
}
