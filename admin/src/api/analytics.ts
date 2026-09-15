import apiClient from './client'
import type { ApiResponse } from '@/types'

export const analyticsApi = {
  getAnalytics(from?: string, to?: string) {
    const params: Record<string, string> = {}
    if (from) params.from = from
    if (to) params.to = to
    return apiClient.get<ApiResponse<{
      revenue: number
      order_count: number
      avg_order_value: number
      top_products: { product_name: string; total_qty: number; total_revenue: number }[]
      orders_by_status: Record<string, number>
      daily_revenue: { date: string; revenue: number; orders: number }[]
      period: { from: string; to: string }
    }>>('/admin/analytics', { params })
  },

  getAuditLog(limit = 50, offset = 0) {
    return apiClient.get<ApiResponse<{
      id: number
      admin_name: string | null
      action: string
      entity_type: string
      entity_id: number | null
      old_values: Record<string, unknown> | null
      new_values: Record<string, unknown> | null
      ip_address: string | null
      created_at: string
    }[]>>('/admin/audit-log', { params: { limit, offset } })
  },
}
