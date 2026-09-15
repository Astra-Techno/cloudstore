import apiClient from './client'

export type SupportTicket = {
  uuid: string
  subject: string
  category: string
  status: 'open' | 'in_progress' | 'resolved' | 'closed'
  priority: 'normal' | 'high'
  customer_name?: string
  customer_phone?: string
  order_number?: string
  created_at: string
}

export type SupportMessage = {
  uuid: string
  sender_type: 'customer' | 'admin'
  body: string
  created_at: string
}

export const supportApi = {
  list(status?: string) {
    return apiClient.get('/admin/support/tickets', { params: status ? { status } : {} })
  },
  get(uuid: string) {
    return apiClient.get(`/admin/support/tickets/${uuid}`)
  },
  reply(uuid: string, message: string) {
    return apiClient.post(`/admin/support/tickets/${uuid}/messages`, { message })
  },
  updateStatus(uuid: string, status: SupportTicket['status']) {
    return apiClient.patch(`/admin/support/tickets/${uuid}/status`, { status })
  },
}
