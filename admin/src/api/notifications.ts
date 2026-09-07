import apiClient from './client'
import type { ApiResponse } from '@/types'

export interface Notification {
  id: number
  uuid: string
  type: string
  title: string
  body: string
  data: string | null
  read_at: string | null
  created_at: string
}

export interface NotificationsResponse {
  notifications: Notification[]
  unread_count: number
}

export const notificationsApi = {
  list(page = 1) {
    return apiClient.get<ApiResponse<NotificationsResponse>>('/admin/notifications', { params: { page } })
  },

  markRead(id: number) {
    return apiClient.patch<ApiResponse>(`/admin/notifications/${id}/read`)
  },

  markAllRead() {
    return apiClient.post<ApiResponse>('/admin/notifications/read-all')
  },
}
