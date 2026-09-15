<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { analyticsApi } from '@/api/analytics'

const loading = ref(true)
const fromDate = ref(new Date(new Date().setDate(1)).toISOString().slice(0, 10))
const toDate = ref(new Date().toISOString().slice(0, 10))

const data = ref<{
  revenue: number
  order_count: number
  avg_order_value: number
  top_products: { product_name: string; total_qty: number; total_revenue: number }[]
  orders_by_status: Record<string, number>
  daily_revenue: { date: string; revenue: number; orders: number }[]
} | null>(null)

const maxDailyRevenue = computed(() => {
  if (!data.value?.daily_revenue?.length) return 1
  return Math.max(...data.value.daily_revenue.map(d => d.revenue), 1)
})

function formatPrice(paise: number): string {
  return '₹' + (paise / 100).toFixed(2)
}

function formatPriceShort(paise: number): string {
  const rupees = paise / 100
  if (rupees >= 100000) return '₹' + (rupees / 100000).toFixed(1) + 'L'
  if (rupees >= 1000) return '₹' + (rupees / 1000).toFixed(1) + 'k'
  return '₹' + rupees.toFixed(0)
}

function formatDay(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })
}

function formatStatus(status: string): string {
  return status.replace(/_/g, ' ')
}

async function loadData() {
  loading.value = true
  try {
    const { data: res } = await analyticsApi.getAnalytics(fromDate.value, toDate.value)
    if (res.success && res.data) data.value = res.data as typeof data.value
  } catch (e) {
    console.error('Failed to load analytics', e)
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Sales Reports</h1>
      <div class="flex items-center gap-3">
        <input type="date" v-model="fromDate" class="border rounded-lg px-3 py-2 text-sm" />
        <span class="text-gray-500">to</span>
        <input type="date" v-model="toDate" class="border rounded-lg px-3 py-2 text-sm" />
        <button @click="loadData" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">
          Apply
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-500">Loading...</div>
    <template v-else-if="data">
      <!-- Summary cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-xl border p-5">
          <div class="text-sm text-gray-500 mb-1">Total Revenue</div>
          <div class="text-2xl font-bold text-green-600">{{ formatPrice(data.revenue) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
          <div class="text-sm text-gray-500 mb-1">Orders</div>
          <div class="text-2xl font-bold">{{ data.order_count }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
          <div class="text-sm text-gray-500 mb-1">Avg Order Value</div>
          <div class="text-2xl font-bold">{{ formatPrice(data.avg_order_value) }}</div>
        </div>
      </div>

      <!-- Revenue chart -->
      <div class="bg-white rounded-xl border p-5 mb-8">
        <h2 class="font-semibold mb-4">Daily Revenue</h2>
        <div v-if="data.daily_revenue.length" class="flex items-end gap-1 h-48">
          <div v-for="day in data.daily_revenue" :key="day.date" class="flex-1 flex flex-col items-center gap-1">
            <div class="text-xs text-gray-500">{{ formatPriceShort(day.revenue) }}</div>
            <div
              class="w-full bg-indigo-500 rounded-t-md min-h-[4px] transition-all"
              :style="{ height: Math.max(day.revenue / maxDailyRevenue * 160, 4) + 'px' }"
              :title="`${formatDay(day.date)}: ${formatPrice(day.revenue)} (${day.orders} orders)`"
            />
            <div class="text-xs text-gray-400">{{ formatDay(day.date) }}</div>
          </div>
        </div>
        <div v-else class="text-gray-400 text-center py-8">No data for this period</div>
      </div>

      <!-- Top products & Status breakdown side by side -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border p-5">
          <h2 class="font-semibold mb-4">Top Products</h2>
          <div v-if="data.top_products.length" class="space-y-3">
            <div v-for="(p, i) in data.top_products" :key="i" class="flex items-center justify-between">
              <div>
                <div class="font-medium text-sm">{{ p.product_name }}</div>
                <div class="text-xs text-gray-400">{{ p.total_qty }} sold</div>
              </div>
              <div class="font-semibold text-sm text-green-600">{{ formatPrice(p.total_revenue) }}</div>
            </div>
          </div>
          <div v-else class="text-gray-400 text-sm">No products sold in this period</div>
        </div>

        <div class="bg-white rounded-xl border p-5">
          <h2 class="font-semibold mb-4">Orders by Status</h2>
          <div v-if="Object.keys(data.orders_by_status).length" class="space-y-2">
            <div v-for="(count, status) in data.orders_by_status" :key="status" class="flex items-center justify-between">
              <span class="capitalize text-sm">{{ formatStatus(status as string) }}</span>
              <span class="font-semibold text-sm bg-gray-100 px-3 py-1 rounded-full">{{ count }}</span>
            </div>
          </div>
          <div v-else class="text-gray-400 text-sm">No orders in this period</div>
        </div>
      </div>
    </template>
  </div>
</template>
