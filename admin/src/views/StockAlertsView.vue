<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { catalogApi } from '@/api/catalog'

interface ProductStock {
  uuid: string
  name: string
  status: string
  stock_mode: string
  stock_quantity: number | null
  base_price: number
  category_name?: string
}

const products = ref<ProductStock[]>([])
const loading = ref(true)
const filter = ref<'all' | 'low' | 'out'>('low')

const filtered = computed(() => {
  if (filter.value === 'low') {
    return products.value.filter(p => p.stock_mode === 'limited_stock' && (p.stock_quantity ?? 0) <= 10 && (p.stock_quantity ?? 0) > 0)
  }
  if (filter.value === 'out') {
    return products.value.filter(p => p.stock_mode === 'limited_stock' && (p.stock_quantity ?? 0) <= 0)
  }
  return products.value.filter(p => p.stock_mode === 'limited_stock')
})

function formatPrice(paise: number): string {
  return '₹' + (paise / 100).toFixed(2)
}

function stockBadge(qty: number | null): { text: string; cls: string } {
  const q = qty ?? 0
  if (q <= 0) return { text: 'Out of stock', cls: 'bg-red-100 text-red-800' }
  if (q <= 5) return { text: `${q} left`, cls: 'bg-red-100 text-red-800' }
  if (q <= 10) return { text: `${q} left`, cls: 'bg-yellow-100 text-yellow-800' }
  return { text: `${q} in stock`, cls: 'bg-green-100 text-green-800' }
}

async function loadProducts() {
  loading.value = true
  try {
    const { data } = await catalogApi.listProducts()
    if (data.success && data.data) {
      products.value = (data.data as ProductStock[]).filter(p => p.stock_mode === 'limited_stock')
    }
  } catch (e) {
    console.error('Failed to load products', e)
  } finally {
    loading.value = false
  }
}

async function updateStock(uuid: string, newQty: number) {
  try {
    await catalogApi.updateProduct(uuid, { stock_quantity: newQty })
    const product = products.value.find(p => p.uuid === uuid)
    if (product) product.stock_quantity = newQty
  } catch (e) {
    console.error('Failed to update stock', e)
  }
}

onMounted(loadProducts)
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Stock Alerts</h1>
      <div class="flex gap-2">
        <button
          v-for="f in (['low', 'out', 'all'] as const)" :key="f"
          @click="filter = f"
          class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
          :class="filter === f ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
        >
          {{ f === 'low' ? 'Low Stock' : f === 'out' ? 'Out of Stock' : 'All Tracked' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-500">Loading...</div>
    <div v-else-if="!filtered.length" class="text-center py-12 text-gray-400">
      {{ filter === 'low' ? 'No low stock items' : filter === 'out' ? 'No out-of-stock items' : 'No stock-tracked products' }}
    </div>
    <div v-else class="space-y-3">
      <div v-for="product in filtered" :key="product.uuid" class="bg-white border rounded-lg p-4 flex items-center gap-4">
        <div class="flex-1">
          <div class="font-medium">{{ product.name }}</div>
          <div class="text-xs text-gray-400">{{ formatPrice(product.base_price) }}</div>
        </div>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium" :class="stockBadge(product.stock_quantity).cls">
          {{ stockBadge(product.stock_quantity).text }}
        </span>
        <div class="flex items-center gap-2">
          <button
            @click="updateStock(product.uuid, Math.max(0, (product.stock_quantity ?? 0) - 1))"
            class="w-8 h-8 rounded-full border flex items-center justify-center text-gray-600 hover:bg-gray-100"
          >−</button>
          <span class="w-10 text-center font-semibold">{{ product.stock_quantity ?? 0 }}</span>
          <button
            @click="updateStock(product.uuid, (product.stock_quantity ?? 0) + 1)"
            class="w-8 h-8 rounded-full border flex items-center justify-center text-gray-600 hover:bg-gray-100"
          >+</button>
          <button
            @click="updateStock(product.uuid, (product.stock_quantity ?? 0) + 25)"
            class="px-3 py-1 text-xs bg-indigo-50 text-indigo-600 rounded-full font-medium hover:bg-indigo-100"
          >+25</button>
        </div>
      </div>
    </div>
  </div>
</template>
