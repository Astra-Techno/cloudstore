import apiClient from './client'
import type { ApiResponse, DeliveryZone, Customer, StoreSettings } from '@/types'

export const settingsApi = {
  // Delivery Zones
  listZones() {
    return apiClient.get<ApiResponse<DeliveryZone[]>>('/admin/delivery-zones')
  },
  createZone(data: Record<string, unknown>) {
    return apiClient.post<ApiResponse<DeliveryZone>>('/admin/delivery-zones', data)
  },
  updateZone(zoneId: number, data: Record<string, unknown>) {
    return apiClient.put<ApiResponse<DeliveryZone>>(`/admin/delivery-zones/${zoneId}`, data)
  },
  deleteZone(zoneId: number) {
    return apiClient.delete<ApiResponse>(`/admin/delivery-zones/${zoneId}`)
  },

  // Customers
  listCustomers(page = 1, search?: string) {
    const params: Record<string, string | number> = { page }
    if (search) params.search = search
    return apiClient.get<ApiResponse<Customer[]>>('/admin/customers', { params })
  },

  // Store Settings
  getSettings() {
    return apiClient.get<ApiResponse<StoreSettings>>('/admin/settings')
  },
  updateSettings(data: Record<string, unknown>) {
    return apiClient.put<ApiResponse<StoreSettings>>('/admin/settings', data)
  },

  // Enhanced Dashboard
  dashboardEnhanced() {
    return apiClient.get<ApiResponse>('/admin/dashboard/enhanced')
  },
}
