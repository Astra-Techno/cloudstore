<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { catalogApi } from '@/api/catalog'
import { downloadCsv } from '@/utils/csv'
import type { Product, Category, PaginatedMeta } from '@/types'

const router = useRouter()
const products = ref<Product[]>([])
const categories = ref<Category[]>([])
const meta = ref<PaginatedMeta | null>(null)
const loading = ref(true)
const showForm = ref(false)
const saving = ref(false)
const error = ref('')
const searchQuery = ref('')
const deleteConfirm = ref<Product | null>(null)
const deleting = ref(false)

const form = ref({
  name: '',
  category_uuid: '',
  base_price: 0,
  pricing_mode: 'fixed',
  unit: 'piece',
  status: 'active',
  stock_mode: 'unlimited',
})

function formatPrice(paise: number): string {
  return '\u20B9' + (paise / 100).toFixed(2)
}

async function loadProducts(page = 1) {
  loading.value = true
  try {
    const { data } = await catalogApi.listProducts(page)
    if (data.success) {
      products.value = data.data || []
      meta.value = data.meta || null
    }
  } catch (e) {
    console.error('Failed to load products', e)
  } finally {
    loading.value = false
  }
}

async function loadCategories() {
  try {
    const { data } = await catalogApi.listCategories()
    if (data.success) {
      categories.value = data.data || []
    }
  } catch (e) {
    console.error('Failed to load categories', e)
  }
}

function openCreate() {
  form.value = {
    name: '',
    category_uuid: categories.value[0]?.uuid || '',
    base_price: 0,
    pricing_mode: 'fixed',
    unit: 'piece',
    status: 'active',
    stock_mode: 'unlimited',
  }
  error.value = ''
  showForm.value = true
}

async function saveProduct() {
  saving.value = true
  error.value = ''

  try {
    const payload = {
      name: form.value.name,
      category_uuid: form.value.category_uuid,
      base_price: Math.round(form.value.base_price * 100),
      pricing_mode: form.value.pricing_mode,
      unit: form.value.unit,
      status: form.value.status,
      stock_mode: form.value.stock_mode,
    }
    const { data } = await catalogApi.createProduct(payload)
    if (!data.success) {
      error.value = data.error?.message || 'Failed to create product'
      return
    }
    showForm.value = false
    await loadProducts()
  } catch (e) {
    error.value = 'An error occurred'
  } finally {
    saving.value = false
  }
}

const statusColors: Record<string, string> = {
  active: 'bg-green-100 text-green-800',
  inactive: 'bg-gray-100 text-gray-800',
  out_of_stock: 'bg-red-100 text-red-800',
}

async function deleteProduct() {
  if (!deleteConfirm.value) return
  deleting.value = true
  try {
    const { data } = await catalogApi.deleteProduct(deleteConfirm.value.uuid)
    if (!data.success) {
      error.value = data.error?.message || 'Failed to delete'
      return
    }
    deleteConfirm.value = null
    await loadProducts()
  } catch (e) {
    error.value = 'Failed to delete product'
  } finally {
    deleting.value = false
  }
}

function exportCsv() {
  const headers = ['Name', 'Category', 'Base Price (₹)', 'Sale Price (₹)', 'Status', 'Stock Mode', 'Stock Qty', 'Pricing Mode', 'Unit']
  const rows = products.value.map(p => [
    p.name, p.category_name || '', (p.base_price / 100).toFixed(2),
    p.sale_price ? (p.sale_price / 100).toFixed(2) : '', p.status,
    p.stock_mode, p.stock_quantity ?? '', p.pricing_mode, p.unit,
  ])
  downloadCsv('products.csv', headers, rows)
}

async function bulkToggleStatus(status: string) {
  const selected = products.value.filter(matchesSearch)
  if (!selected.length) return
  if (!confirm(`Set ${selected.length} visible products to "${status}"?`)) return
  for (const p of selected) {
    if (p.status !== status) {
      await catalogApi.updateProduct(p.uuid, { status })
    }
  }
  await loadProducts()
}

function matchesSearch(product: Product): boolean {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return true
  return [product.name, product.category_name, product.pricing_mode].some(value => value?.toLowerCase().includes(query))
}

onMounted(() => {
  loadProducts()
  loadCategories()
})
</script>

<template>
  <div class="list-page products-list-page">
    <section class="list-page-intro"><div><p class="list-kicker">Catalog studio</p><h1>Products <span>/ your assortment</span></h1><p>Shape what customers discover, compare, and come back for.</p></div><button @click="openCreate" class="list-primary-action"><span>＋</span> Add product</button></section>
    <section class="list-stat-rail"><div><span>Assortment</span><strong>{{ meta?.total ?? products.length }}</strong><small>products in catalog</small></div><div><span>Live now</span><strong>{{ products.filter(product => product.status === 'active').length }}</strong><small>active products</small></div><div><span>Tracked stock</span><strong class="list-stat-accent">{{ products.filter(product => product.stock_mode !== 'unlimited').length }}</strong><small>inventory signals</small></div><div class="list-stat-rail__signal"><span>Catalog health</span><strong>Fresh</strong><small><i></i> Ready for customers</small></div></section>
    <section class="list-toolbar"><label class="list-search"><span aria-hidden="true">⌕</span><input v-model="searchQuery" type="search" placeholder="Search product, category or pricing" /></label><div class="flex items-center gap-2"><button @click="exportCsv" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">Export CSV</button><button @click="bulkToggleStatus('active')" class="px-3 py-1.5 text-xs font-medium text-green-600 bg-green-50 rounded-lg hover:bg-green-100">Activate All</button><button @click="bulkToggleStatus('inactive')" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-50 rounded-lg hover:bg-gray-100">Deactivate All</button></div></section>

    <div v-if="loading" class="list-loading">Loading your catalog<span></span></div>

    <section v-else class="catalog-tile-grid" aria-label="Products">
      <article v-for="product in products" v-show="matchesSearch(product)" :key="`tile-${product.uuid}`" class="catalog-tile" @click="router.push(`/products/${product.uuid}`)">
        <div class="catalog-tile__top">
          <span class="catalog-tile__avatar">{{ product.name.charAt(0).toUpperCase() }}</span>
          <span class="list-status" :class="product.status === 'active' ? 'list-status--lime' : 'list-status--coral'"><i></i>{{ product.status.replace(/_/g, ' ') }}</span>
        </div>
        <div class="catalog-tile__body">
          <p>{{ product.category_name || 'Uncategorized' }}</p>
          <h2>{{ product.name }}</h2>
          <small>{{ product.pricing_mode === 'weight' ? `By ${product.unit}` : `${product.unit} · fixed price` }}</small>
        </div>
        <div class="catalog-tile__bottom">
          <div><strong>{{ formatPrice(product.sale_price ?? product.base_price) }}</strong><small v-if="product.sale_price">was {{ formatPrice(product.base_price) }}</small></div>
          <span class="catalog-tile__stock">{{ product.stock_mode === 'unlimited' ? 'Unlimited' : `${product.stock_quantity ?? 0} in stock` }}</span>
        </div>
        <div class="catalog-tile__actions" @click.stop>
          <button @click="router.push(`/products/${product.uuid}`)">Edit product</button>
          <button class="catalog-tile__delete" @click="deleteConfirm = product" aria-label="Delete product">×</button>
        </div>
      </article>
      <div v-if="products.length === 0 || !products.some(matchesSearch)" class="catalog-tile-empty">No products match this search. Try another word or add a new product.</div>
    </section>

    <div v-if="false" class="list-table-card"><table class="list-table"><thead>
          <tr>
            <th>Product</th><th>Category</th><th>Pricing model</th><th class="text-right">Price</th><th>Status</th><th>Stock</th><th></th>
          </tr>
        </thead><tbody><tr v-for="product in products" v-show="matchesSearch(product)" :key="product.uuid" class="list-row">
            <td class="list-order-id"><span class="product-avatar">{{ product.name.charAt(0).toUpperCase() }}</span><div><strong>{{ product.name }}</strong><small>{{ product.unit }} · {{ product.pricing_mode }}</small></div></td><td class="list-muted">{{ product.category_name || 'Uncategorized' }}</td><td class="list-muted capitalize">{{ product.pricing_mode }}<span v-if="product.pricing_mode === 'weight'"> / {{ product.unit }}</span></td><td class="list-total">
              {{ formatPrice(product.base_price) }}
              <span v-if="product.sale_price" class="product-sale-price">
                {{ formatPrice(product.sale_price ?? 0) }}
              </span>
            </td><td><span class="list-status" :class="product.status === 'active' ? 'list-status--lime' : 'list-status--coral'"><i></i>{{ product.status.replace(/_/g, ' ') }}</span></td><td class="list-muted">{{ product.stock_mode === 'unlimited' ? '∞ Unlimited' : `${product.stock_quantity ?? 0} units` }}</td><td class="list-action-cell"><button @click="router.push(`/products/${product.uuid}`)" class="list-edit-button">Edit <span>→</span></button><button @click.stop="deleteConfirm = product" class="list-edit-button" style="color:#ef4444;margin-left:8px">Delete</button></td>
          </tr><tr v-if="products.length === 0 || !products.some(matchesSearch)"><td colspan="7" class="list-empty">No products found.</td></tr></tbody></table>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="list-pagination">
      <button
        v-for="page in meta.last_page"
        :key="page"
        @click="loadProducts(page)"
        class="list-page-button"
        :class="page === meta.current_page
          ? 'list-page-button--active'
          : ''"
      >
        {{ page }}
      </button>
    </div>

    <!-- Delete Confirm Modal -->
    <div v-if="deleteConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Delete Product</h2>
        <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete <strong>{{ deleteConfirm.name }}</strong>? This action cannot be undone.</p>
        <div v-if="error" class="mb-3 p-2 bg-red-50 border border-red-200 text-red-700 rounded text-sm">{{ error }}</div>
        <div class="flex justify-end gap-3">
          <button @click="deleteConfirm = null; error = ''" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
          <button @click="deleteProduct" :disabled="deleting" class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>

    <!-- Create Product Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">New Product</h2>

        <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          {{ error }}
        </div>

        <form @submit.prevent="saveProduct">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
            />
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select
              v-model="form.category_uuid"
              required
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
            >
              <option v-for="cat in categories" :key="cat.uuid" :value="cat.uuid">{{ cat.name }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Base Price (rupees)</label>
              <input
                v-model.number="form.base_price"
                type="number"
                step="0.01"
                min="0"
                required
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Pricing Mode</label>
              <select
                v-model="form.pricing_mode"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
              >
                <option value="fixed">Fixed</option>
                <option value="weight">Weight</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
              <select
                v-model="form.unit"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
              >
                <option value="piece">Piece</option>
                <option value="kg">Kg</option>
                <option value="g">Gram</option>
                <option value="l">Litre</option>
                <option value="ml">ml</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Stock Mode</label>
              <select
                v-model="form.stock_mode"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
              >
                <option value="unlimited">Unlimited</option>
                <option value="tracked">Tracked</option>
              </select>
            </div>
          </div>

          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="form.status"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          <div class="flex justify-end gap-3">
            <button
              type="button"
              @click="showForm = false"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Create Product' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
