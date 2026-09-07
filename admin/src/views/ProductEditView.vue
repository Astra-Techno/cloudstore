<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { catalogApi } from '@/api/catalog'
import type { Product, Category, ProductImage, ProductVariant, AddonGroup } from '@/types'

const route = useRoute()
const router = useRouter()
const productUuid = route.params.uuid as string

const product = ref<Product | null>(null)
const categories = ref<Category[]>([])
const images = ref<ProductImage[]>([])
const allAddonGroups = ref<AddonGroup[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const activeTab = ref<'details' | 'images' | 'variants' | 'addons'>('details')

// Image upload
const uploading = ref(false)
const imageInput = ref<HTMLInputElement | null>(null)

// Variant form
const showVariantForm = ref(false)
const variantSaving = ref(false)
const editingVariant = ref<ProductVariant | null>(null)
const variantForm = ref({ name: '', price: 0, sku: '', compare_price: 0, weight_grams: 0, stock_mode: 'unlimited', stock_quantity: 0 })

// Delete
const showDeleteConfirm = ref(false)
const deleting = ref(false)

// Addon form
const showAddonForm = ref(false)
const addonGroupForm = ref({ name: '', is_required: 0, min_selections: 0, max_selections: 5 })
const addonItemForm = ref({ name: '', price: 0 })
const showItemForm = ref<number | null>(null)

const form = ref({
  name: '',
  description: '',
  short_description: '',
  category_uuid: '',
  base_price: 0,
  sale_price: 0,
  pricing_mode: 'fixed',
  unit: 'piece',
  status: 'active',
  stock_mode: 'unlimited',
  stock_quantity: 0,
  min_quantity: 1,
  max_quantity: 50,
  preparation_time_minutes: 0,
  is_featured: 0,
  sort_order: 0,
})

function formatPrice(paise: number): string {
  return '₹' + (paise / 100).toFixed(2)
}

async function loadProduct() {
  loading.value = true
  try {
    const [productRes, catRes, imgRes, addonRes] = await Promise.all([
      catalogApi.getProduct(productUuid),
      catalogApi.listCategories(),
      catalogApi.listProductImages(productUuid),
      catalogApi.listAddonGroups(),
    ])

    if (productRes.data.success && productRes.data.data) {
      product.value = productRes.data.data
      const p = productRes.data.data
      form.value = {
        name: p.name,
        description: p.description || '',
        short_description: p.short_description || '',
        category_uuid: '', // will set below
        base_price: p.base_price / 100,
        sale_price: (p.sale_price || 0) / 100,
        pricing_mode: p.pricing_mode,
        unit: p.unit,
        status: p.status,
        stock_mode: p.stock_mode,
        stock_quantity: p.stock_quantity || 0,
        min_quantity: p.min_quantity || 1,
        max_quantity: p.max_quantity || 50,
        preparation_time_minutes: p.preparation_time_minutes || 0,
        is_featured: p.is_featured || 0,
        sort_order: p.sort_order || 0,
      }
    }

    if (catRes.data.success) {
      categories.value = catRes.data.data || []
      // Find matching category UUID
      if (product.value?.category_slug) {
        const cat = categories.value.find(c => c.slug === product.value?.category_slug)
        if (cat) form.value.category_uuid = cat.uuid
      }
    }

    if (imgRes.data.success) {
      images.value = imgRes.data.data || []
    }

    if (addonRes.data.success) {
      allAddonGroups.value = addonRes.data.data || []
    }
  } catch (e) {
    error.value = 'Failed to load product'
  } finally {
    loading.value = false
  }
}

async function saveProduct() {
  saving.value = true
  error.value = ''
  success.value = ''

  // Validations
  if (form.value.sale_price > 0 && form.value.sale_price >= form.value.base_price) {
    error.value = 'Sale price must be less than base price'
    saving.value = false
    return
  }
  if (form.value.min_quantity > form.value.max_quantity) {
    error.value = 'Min quantity cannot exceed max quantity'
    saving.value = false
    return
  }

  try {
    const payload: Record<string, unknown> = {
      name: form.value.name,
      description: form.value.description || null,
      short_description: form.value.short_description || null,
      base_price: Math.round(form.value.base_price * 100),
      sale_price: form.value.sale_price > 0 ? Math.round(form.value.sale_price * 100) : null,
      pricing_mode: form.value.pricing_mode,
      unit: form.value.unit,
      status: form.value.status,
      stock_mode: form.value.stock_mode,
      stock_quantity: form.value.stock_mode === 'tracked' ? form.value.stock_quantity : null,
      min_quantity: form.value.min_quantity,
      max_quantity: form.value.max_quantity,
      preparation_time_minutes: form.value.preparation_time_minutes || null,
      is_featured: form.value.is_featured,
      sort_order: form.value.sort_order,
    }

    if (form.value.category_uuid) {
      const cat = categories.value.find(c => c.uuid === form.value.category_uuid)
      if (cat) payload.category_id = cat.id
    }

    const { data } = await catalogApi.updateProduct(productUuid, payload)
    if (data.success) {
      success.value = 'Product saved successfully'
      product.value = data.data!
      setTimeout(() => success.value = '', 3000)
    } else {
      error.value = data.error?.message || 'Failed to save'
    }
  } catch (e) {
    error.value = 'An error occurred'
  } finally {
    saving.value = false
  }
}

// Images
async function handleImageUpload(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (!files?.length) return

  uploading.value = true
  try {
    for (const file of Array.from(files)) {
      const isPrimary = images.value.length === 0
      await catalogApi.uploadProductImage(productUuid, file, isPrimary)
    }
    const { data } = await catalogApi.listProductImages(productUuid)
    if (data.success) images.value = data.data || []
  } catch (e) {
    error.value = 'Failed to upload image'
  } finally {
    uploading.value = false
    if (imageInput.value) imageInput.value.value = ''
  }
}

async function deleteImage(imageId: number) {
  try {
    await catalogApi.deleteProductImage(productUuid, imageId)
    images.value = images.value.filter(i => i.id !== imageId)
  } catch (e) {
    error.value = 'Failed to delete image'
  }
}

async function makePrimary(imageId: number) {
  try {
    await catalogApi.setPrimaryImage(productUuid, imageId)
    images.value.forEach(i => i.is_primary = i.id === imageId ? 1 : 0)
  } catch (e) {
    error.value = 'Failed to set primary image'
  }
}

function imageUrl(url: string): string {
  if (url.startsWith('http')) return url
  return url
}

// Variants
function openVariantCreate() {
  editingVariant.value = null
  variantForm.value = { name: '', price: 0, sku: '', compare_price: 0, weight_grams: 0, stock_mode: 'unlimited', stock_quantity: 0 }
  showVariantForm.value = true
}

function openVariantEdit(v: ProductVariant) {
  editingVariant.value = v
  variantForm.value = { name: v.name, price: v.price / 100, sku: v.sku || '', compare_price: (v.compare_price || 0) / 100, weight_grams: v.weight_grams || 0, stock_mode: v.stock_mode, stock_quantity: v.stock_quantity || 0 }
  showVariantForm.value = true
}

async function saveVariant() {
  variantSaving.value = true
  try {
    const payload = {
      name: variantForm.value.name,
      price: Math.round(variantForm.value.price * 100),
      sku: variantForm.value.sku || null,
      compare_price: variantForm.value.compare_price > 0 ? Math.round(variantForm.value.compare_price * 100) : null,
      weight_grams: variantForm.value.weight_grams || null,
      stock_mode: variantForm.value.stock_mode,
      stock_quantity: variantForm.value.stock_mode === 'tracked' ? variantForm.value.stock_quantity : null,
    }

    if (editingVariant.value) {
      await catalogApi.updateVariant(productUuid, editingVariant.value.id, payload)
    } else {
      await catalogApi.createVariant(productUuid, payload)
    }

    showVariantForm.value = false
    await loadProduct()
  } catch (e) {
    error.value = 'Failed to save variant'
  } finally {
    variantSaving.value = false
  }
}

async function deleteVariant(variantId: number) {
  try {
    await catalogApi.deleteVariant(productUuid, variantId)
    if (product.value?.variants) {
      product.value.variants = product.value.variants.filter(v => v.id !== variantId)
    }
  } catch (e) {
    error.value = 'Failed to delete variant'
  }
}

// Addons
const productAddonIds = computed(() => {
  return new Set((product.value?.addon_groups || []).map(g => g.id))
})

const availableAddonGroups = computed(() => {
  return allAddonGroups.value.filter(g => !productAddonIds.value.has(g.id))
})

async function attachAddon(groupId: number) {
  try {
    await catalogApi.attachAddon(productUuid, groupId)
    await loadProduct()
  } catch (e) {
    error.value = 'Failed to attach addon'
  }
}

async function detachAddon(groupId: number) {
  try {
    await catalogApi.detachAddon(productUuid, groupId)
    if (product.value?.addon_groups) {
      product.value.addon_groups = product.value.addon_groups.filter(g => g.id !== groupId)
    }
  } catch (e) {
    error.value = 'Failed to detach addon'
  }
}

async function createAddonGroup() {
  try {
    await catalogApi.createAddonGroup(addonGroupForm.value)
    showAddonForm.value = false
    const { data } = await catalogApi.listAddonGroups()
    if (data.success) allAddonGroups.value = data.data || []
  } catch (e) {
    error.value = 'Failed to create addon group'
  }
}

async function addItemToGroup(groupId: number) {
  try {
    await catalogApi.addAddonItem(groupId, {
      name: addonItemForm.value.name,
      price: Math.round(addonItemForm.value.price * 100),
    })
    showItemForm.value = null
    addonItemForm.value = { name: '', price: 0 }
    const { data } = await catalogApi.listAddonGroups()
    if (data.success) allAddonGroups.value = data.data || []
    await loadProduct()
  } catch (e) {
    error.value = 'Failed to add addon item'
  }
}

async function deleteAddonItem(groupId: number, itemId: number) {
  try {
    await catalogApi.deleteAddonItem(groupId, itemId)
    await loadProduct()
    const { data } = await catalogApi.listAddonGroups()
    if (data.success) allAddonGroups.value = data.data || []
  } catch (e) {
    error.value = 'Failed to delete addon item'
  }
}

async function deleteProduct() {
  deleting.value = true
  try {
    const { data } = await catalogApi.deleteProduct(productUuid)
    if (data.success) {
      router.push('/products')
    } else {
      error.value = data.error?.message || 'Failed to delete'
    }
  } catch (e) {
    error.value = 'Failed to delete product'
  } finally {
    deleting.value = false
    showDeleteConfirm.value = false
  }
}

onMounted(loadProduct)
</script>

<template>
  <div class="product-edit-page">
    <div class="flex items-center gap-4 mb-6">
      <button @click="router.push('/products')" class="text-sm text-blue-600 hover:text-blue-800">← Back to Products</button>
      <h1 class="text-2xl font-bold text-gray-900 flex-1">{{ product?.name || 'Edit Product' }}</h1>
      <button @click="showDeleteConfirm = true" class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100">Delete</button>
      <button @click="saveProduct" :disabled="saving" class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
        {{ saving ? 'Saving...' : 'Save Changes' }}
      </button>
    </div>

    <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ error }}</div>
    <div v-if="success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ success }}</div>

    <div v-if="loading" class="text-gray-500">Loading product...</div>

    <template v-else-if="product">
      <!-- Tabs -->
      <div class="flex gap-1 mb-6 bg-gray-100 rounded-lg p-1 w-fit">
        <button v-for="tab in (['details', 'images', 'variants', 'addons'] as const)" :key="tab" @click="activeTab = tab"
          class="px-4 py-2 text-sm font-medium rounded-md transition-colors"
          :class="activeTab === tab ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
        >
          {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
          <span v-if="tab === 'images'" class="ml-1 text-xs text-gray-400">({{ images.length }})</span>
          <span v-if="tab === 'variants'" class="ml-1 text-xs text-gray-400">({{ product.variants?.length || 0 }})</span>
          <span v-if="tab === 'addons'" class="ml-1 text-xs text-gray-400">({{ product.addon_groups?.length || 0 }})</span>
        </button>
      </div>

      <!-- Details Tab -->
      <div v-show="activeTab === 'details'" class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
              <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label>
              <input v-model="form.short_description" type="text" maxlength="160" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Full Description</label>
              <textarea v-model="form.description" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
              <select v-model="form.category_uuid" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option v-for="cat in categories" :key="cat.uuid" :value="cat.uuid">{{ cat.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select v-model="form.status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="out_of_stock">Out of Stock</option>
              </select>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Pricing</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Base Price (₹)</label>
              <input v-model.number="form.base_price" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sale Price (₹)</label>
              <input v-model.number="form.sale_price" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <p class="text-xs text-gray-400 mt-1">Leave 0 for no sale</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Pricing Mode</label>
              <select v-model="form.pricing_mode" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="fixed">Fixed Price</option>
                <option value="weight">Weight-based</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
              <select v-model="form.unit" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="piece">Piece</option>
                <option value="kg">Kg</option>
                <option value="g">Gram</option>
                <option value="l">Litre</option>
                <option value="ml">ml</option>
              </select>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Inventory & Availability</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Stock Mode</label>
              <select v-model="form.stock_mode" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="unlimited">Unlimited</option>
                <option value="tracked">Tracked</option>
              </select>
            </div>
            <div v-if="form.stock_mode === 'tracked'">
              <label class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
              <input v-model.number="form.stock_quantity" type="number" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Min Order Qty</label>
              <input v-model.number="form.min_quantity" type="number" min="1" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Max Order Qty</label>
              <input v-model.number="form.max_quantity" type="number" min="1" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Preparation Time (min)</label>
              <input v-model.number="form.preparation_time_minutes" type="number" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <p class="text-xs text-gray-400 mt-1">Estimated preparation time</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div class="flex items-center gap-3 pt-6">
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="form.is_featured === 1" @change="form.is_featured = ($event.target as HTMLInputElement).checked ? 1 : 0" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
              </label>
              <span class="text-sm font-medium text-gray-700">Featured Product</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Images Tab -->
      <div v-show="activeTab === 'images'" class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Product Images</h2>
            <button @click="imageInput?.click()" :disabled="uploading" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
              {{ uploading ? 'Uploading...' : '+ Upload Images' }}
            </button>
            <input ref="imageInput" type="file" accept="image/*" multiple class="hidden" @change="handleImageUpload" />
          </div>

          <div v-if="images.length === 0" class="text-center py-12 text-gray-400">
            <div class="text-4xl mb-2">📷</div>
            <p class="text-sm">No images yet. Upload product photos to attract customers.</p>
          </div>

          <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div v-for="img in images" :key="img.id" class="relative group rounded-lg overflow-hidden border border-gray-200">
              <img :src="imageUrl(img.url)" :alt="img.alt_text || product?.name" class="w-full h-40 object-cover" />
              <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                <button v-if="!img.is_primary" @click="makePrimary(img.id)" class="px-2 py-1 bg-white text-xs font-medium rounded" title="Set as primary">⭐</button>
                <button @click="deleteImage(img.id)" class="px-2 py-1 bg-red-500 text-white text-xs font-medium rounded" title="Delete">✕</button>
              </div>
              <div v-if="img.is_primary" class="absolute top-2 left-2 bg-blue-600 text-white text-xs px-2 py-0.5 rounded">Primary</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Variants Tab -->
      <div v-show="activeTab === 'variants'" class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Product Variants</h2>
            <button @click="openVariantCreate" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">+ Add Variant</button>
          </div>

          <div v-if="!product.variants?.length" class="text-center py-8 text-gray-400">
            <p class="text-sm">No variants. Add size or weight options for this product.</p>
          </div>

          <table v-else class="w-full">
            <thead>
              <tr class="text-left text-xs text-gray-500 uppercase border-b">
                <th class="pb-2">Name</th><th class="pb-2">SKU</th><th class="pb-2 text-right">Price</th><th class="pb-2">Stock</th><th class="pb-2">Status</th><th class="pb-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="v in product.variants" :key="v.id" class="border-b border-gray-50 hover:bg-gray-50">
                <td class="py-3 font-medium">{{ v.name }}</td>
                <td class="py-3 text-sm text-gray-500">{{ v.sku || '—' }}</td>
                <td class="py-3 text-right font-medium">{{ formatPrice(v.price) }}</td>
                <td class="py-3 text-sm">{{ v.stock_mode === 'unlimited' ? '∞' : v.stock_quantity }}</td>
                <td class="py-3"><span class="px-2 py-0.5 rounded-full text-xs" :class="v.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'">{{ v.status }}</span></td>
                <td class="py-3 text-right">
                  <button @click="openVariantEdit(v)" class="text-blue-600 text-sm mr-2 hover:underline">Edit</button>
                  <button @click="deleteVariant(v.id)" class="text-red-600 text-sm hover:underline">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Addons Tab -->
      <div v-show="activeTab === 'addons'" class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Linked Addon Groups</h2>
            <button @click="showAddonForm = true" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">+ New Group</button>
          </div>

          <!-- Linked addon groups -->
          <div v-if="!product.addon_groups?.length" class="text-center py-8 text-gray-400">
            <p class="text-sm">No addon groups linked to this product.</p>
          </div>

          <div v-else class="space-y-4">
            <div v-for="group in product.addon_groups" :key="group.id" class="border border-gray-200 rounded-lg p-4">
              <div class="flex items-center justify-between mb-3">
                <div>
                  <h3 class="font-medium text-gray-900">{{ group.name }}</h3>
                  <p class="text-xs text-gray-500">{{ group.is_required ? 'Required' : 'Optional' }} · {{ group.min_selections }}-{{ group.max_selections }} selections</p>
                </div>
                <div class="flex gap-2">
                  <button @click="showItemForm = group.id" class="text-blue-600 text-sm hover:underline">+ Item</button>
                  <button @click="detachAddon(group.id)" class="text-red-600 text-sm hover:underline">Unlink</button>
                </div>
              </div>
              <div v-if="group.items?.length" class="space-y-1">
                <div v-for="item in group.items" :key="item.id" class="flex items-center justify-between py-1.5 px-2 rounded hover:bg-gray-50 text-sm">
                  <span>{{ item.name }}</span>
                  <div class="flex items-center gap-3">
                    <span class="text-gray-600">+ {{ formatPrice(item.price) }}</span>
                    <button @click="deleteAddonItem(group.id, item.id)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                  </div>
                </div>
              </div>
              <!-- Inline add item form -->
              <div v-if="showItemForm === group.id" class="mt-3 flex gap-2">
                <input v-model="addonItemForm.name" type="text" placeholder="Item name" class="flex-1 border border-gray-300 rounded-md px-3 py-1.5 text-sm" />
                <input v-model.number="addonItemForm.price" type="number" step="0.01" min="0" placeholder="Price ₹" class="w-24 border border-gray-300 rounded-md px-3 py-1.5 text-sm" />
                <button @click="addItemToGroup(group.id)" class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-md">Add</button>
                <button @click="showItemForm = null" class="px-3 py-1.5 text-gray-600 text-sm">Cancel</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Available addon groups to link -->
        <div v-if="availableAddonGroups.length" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Available Addon Groups</h2>
          <div class="space-y-2">
            <div v-for="group in availableAddonGroups" :key="group.id" class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-gray-50 border border-gray-100">
              <div>
                <span class="font-medium text-sm">{{ group.name }}</span>
                <span class="text-xs text-gray-400 ml-2">({{ group.items?.length || 0 }} items)</span>
              </div>
              <button @click="attachAddon(group.id)" class="text-blue-600 text-sm hover:underline">Link to product</button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Variant Modal -->
    <div v-if="showVariantForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ editingVariant ? 'Edit Variant' : 'New Variant' }}</h2>
        <form @submit.prevent="saveVariant" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Variant Name</label>
            <input v-model="variantForm.name" type="text" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. 500g, Large" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Price (₹)</label>
              <input v-model.number="variantForm.price" type="number" step="0.01" min="0" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Compare Price (₹)</label>
              <input v-model.number="variantForm.compare_price" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
              <input v-model="variantForm.sku" type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Weight (grams)</label>
              <input v-model.number="variantForm.weight_grams" type="number" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Stock Mode</label>
              <select v-model="variantForm.stock_mode" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="unlimited">Unlimited</option>
                <option value="tracked">Tracked</option>
              </select>
            </div>
            <div v-if="variantForm.stock_mode === 'tracked'">
              <label class="block text-sm font-medium text-gray-700 mb-1">Stock Qty</label>
              <input v-model.number="variantForm.stock_quantity" type="number" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showVariantForm = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button type="submit" :disabled="variantSaving" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
              {{ variantSaving ? 'Saving...' : 'Save Variant' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Delete Product</h2>
        <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete <strong>{{ product?.name }}</strong>? This action cannot be undone.</p>
        <div class="flex justify-end gap-3">
          <button @click="showDeleteConfirm = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
          <button @click="deleteProduct" :disabled="deleting" class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>

    <!-- Addon Group Create Modal -->
    <div v-if="showAddonForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">New Addon Group</h2>
        <form @submit.prevent="createAddonGroup" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Group Name</label>
            <input v-model="addonGroupForm.name" type="text" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. Spice Level, Extras" />
          </div>
          <div class="flex items-center gap-3">
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" :checked="addonGroupForm.is_required === 1" @change="addonGroupForm.is_required = ($event.target as HTMLInputElement).checked ? 1 : 0" class="sr-only peer" />
              <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
            </label>
            <span class="text-sm text-gray-700">Required selection</span>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Min Selections</label>
              <input v-model.number="addonGroupForm.min_selections" type="number" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Max Selections</label>
              <input v-model.number="addonGroupForm.max_selections" type="number" min="1" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showAddonForm = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button type="submit" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700">Create Group</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
