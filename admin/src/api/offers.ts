import apiClient from './client'

export const offersApi = {
  // Coupons
  listCoupons: (params?: Record<string, any>) => apiClient.get('/admin/coupons', { params }),
  getCoupon: (uuid: string) => apiClient.get(`/admin/coupons/${uuid}`),
  createCoupon: (data: Record<string, any>) => apiClient.post('/admin/coupons', data),
  updateCoupon: (uuid: string, data: Record<string, any>) => apiClient.put(`/admin/coupons/${uuid}`, data),
  deleteCoupon: (uuid: string) => apiClient.delete(`/admin/coupons/${uuid}`),

  // Promotions
  listPromotions: (params?: Record<string, any>) => apiClient.get('/admin/promotions', { params }),
  getPromotion: (uuid: string) => apiClient.get(`/admin/promotions/${uuid}`),
  createPromotion: (data: Record<string, any>) => apiClient.post('/admin/promotions', data),
  updatePromotion: (uuid: string, data: Record<string, any>) => apiClient.put(`/admin/promotions/${uuid}`, data),
  deletePromotion: (uuid: string) => apiClient.delete(`/admin/promotions/${uuid}`),

  // Bundles
  listBundles: (params?: Record<string, any>) => apiClient.get('/admin/bundles', { params }),
  getBundle: (uuid: string) => apiClient.get(`/admin/bundles/${uuid}`),
  createBundle: (data: Record<string, any>) => apiClient.post('/admin/bundles', data),
  updateBundle: (uuid: string, data: Record<string, any>) => apiClient.put(`/admin/bundles/${uuid}`, data),
  deleteBundle: (uuid: string) => apiClient.delete(`/admin/bundles/${uuid}`),
  addBundleItem: (uuid: string, data: Record<string, any>) => apiClient.post(`/admin/bundles/${uuid}/items`, data),
  removeBundleItem: (uuid: string, itemId: number) => apiClient.delete(`/admin/bundles/${uuid}/items/${itemId}`),
}
