import apiClient from './client'
import type { ApiResponse, Category, Product, ProductImage, ProductVariant, AddonGroup, AddonItem } from '@/types'

export const catalogApi = {
  // Categories
  listCategories() {
    return apiClient.get<ApiResponse<Category[]>>('/admin/categories')
  },
  createCategory(data: { name: string; description?: string; status?: string; image_url?: string }) {
    return apiClient.post<ApiResponse<Category>>('/admin/categories', data)
  },
  updateCategory(uuid: string, data: Partial<Category>) {
    return apiClient.put<ApiResponse<Category>>(`/admin/categories/${uuid}`, data)
  },
  deleteCategory(uuid: string) {
    return apiClient.delete<ApiResponse>(`/admin/categories/${uuid}`)
  },

  // Products
  listProducts(page = 1, search?: string, status?: string, includeVariants = false) {
    const params: Record<string, string | number> = { page }
    if (search) params.search = search
    if (status) params.status = status
    if (includeVariants) params.include_variants = 1
    return apiClient.get<ApiResponse<Product[]>>('/admin/products', { params })
  },
  getProduct(uuid: string) {
    return apiClient.get<ApiResponse<Product>>(`/admin/products/${uuid}`)
  },
  createProduct(data: Record<string, unknown>) {
    return apiClient.post<ApiResponse<Product>>('/admin/products', data)
  },
  updateProduct(uuid: string, data: Record<string, unknown>) {
    return apiClient.put<ApiResponse<Product>>(`/admin/products/${uuid}`, data)
  },
  deleteProduct(uuid: string) {
    return apiClient.delete<ApiResponse>(`/admin/products/${uuid}`)
  },

  // Product Images
  listProductImages(productUuid: string) {
    return apiClient.get<ApiResponse<ProductImage[]>>(`/admin/products/${productUuid}/images`)
  },
  uploadProductImage(productUuid: string, file: File, isPrimary = false) {
    const formData = new FormData()
    formData.append('image', file)
    if (isPrimary) formData.append('is_primary', '1')
    return apiClient.post<ApiResponse<ProductImage>>(`/admin/products/${productUuid}/images`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  deleteProductImage(productUuid: string, imageId: number) {
    return apiClient.delete<ApiResponse>(`/admin/products/${productUuid}/images/${imageId}`)
  },
  setPrimaryImage(productUuid: string, imageId: number) {
    return apiClient.patch<ApiResponse>(`/admin/products/${productUuid}/images/${imageId}/primary`)
  },

  // Variants
  createVariant(productUuid: string, data: Record<string, unknown>) {
    return apiClient.post<ApiResponse<ProductVariant>>(`/admin/products/${productUuid}/variants`, data)
  },
  updateVariant(productUuid: string, variantId: number, data: Record<string, unknown>) {
    return apiClient.put<ApiResponse<ProductVariant>>(`/admin/products/${productUuid}/variants/${variantId}`, data)
  },
  deleteVariant(productUuid: string, variantId: number) {
    return apiClient.delete<ApiResponse>(`/admin/products/${productUuid}/variants/${variantId}`)
  },

  // Product Addons
  attachAddon(productUuid: string, addonGroupId: number) {
    return apiClient.post<ApiResponse>(`/admin/products/${productUuid}/addons`, { addon_group_id: addonGroupId })
  },
  detachAddon(productUuid: string, groupId: number) {
    return apiClient.delete<ApiResponse>(`/admin/products/${productUuid}/addons/${groupId}`)
  },

  // Addon Groups
  listAddonGroups() {
    return apiClient.get<ApiResponse<AddonGroup[]>>('/admin/addon-groups')
  },
  createAddonGroup(data: Record<string, unknown>) {
    return apiClient.post<ApiResponse<AddonGroup>>('/admin/addon-groups', data)
  },
  updateAddonGroup(groupId: number, data: Record<string, unknown>) {
    return apiClient.put<ApiResponse<AddonGroup>>(`/admin/addon-groups/${groupId}`, data)
  },
  addAddonItem(groupId: number, data: { name: string; price: number }) {
    return apiClient.post<ApiResponse<AddonItem>>(`/admin/addon-groups/${groupId}/items`, data)
  },
  deleteAddonItem(groupId: number, itemId: number) {
    return apiClient.delete<ApiResponse>(`/admin/addon-groups/${groupId}/items/${itemId}`)
  },
}
