import apiClient from './client'
import type { ApiResponse, Order, OrderItem, StatusHistory, DashboardStats, Driver } from '@/types'

export const ordersApi = {
  list(status?: string, page = 1) {
    const params: Record<string, string | number> = { page }
    if (status) params.status = status
    return apiClient.get<ApiResponse<Order[]>>('/admin/orders', { params })
  },

  board() {
    return apiClient.get<ApiResponse<{
      counts: Record<string, number>
      orders: (Order & { items: OrderItem[] })[]
    }>>('/admin/orders/board')
  },

  get(uuid: string) {
    return apiClient.get<ApiResponse<{
      order: Order
      items: OrderItem[]
      status_history: StatusHistory[]
      allowed_transitions: string[]
    }>>(`/admin/orders/${uuid}`)
  },

  updateStatus(uuid: string, status: string, notes?: string) {
    return apiClient.patch<ApiResponse>(`/admin/orders/${uuid}/status`, { status, notes })
  },

  assignDriver(uuid: string, driverUuid: string) {
    return apiClient.post<ApiResponse>(`/admin/orders/${uuid}/assign-driver`, { driver_uuid: driverUuid })
  },

  refund(uuid: string, reason: string) {
    return apiClient.post<ApiResponse>(`/admin/orders/${uuid}/refund`, { reason })
  },

  dashboard() {
    return apiClient.get<ApiResponse<DashboardStats>>('/admin/dashboard')
  },

  availableDrivers() {
    return apiClient.get<ApiResponse<Driver[]>>('/admin/drivers/available')
  },

  posCheckout(data: { items: { product_uuid: string; quantity: number }[]; customer_name?: string; customer_phone?: string; payment_method: 'cash' | 'upi' | 'card'; order_type: 'pickup'; notes?: string }) {
    return apiClient.post<ApiResponse<{ order: Order; items: OrderItem[] }>>('/admin/pos/checkout', data)
  },
}
