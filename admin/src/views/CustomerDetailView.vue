<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/api/client'
import type { ApiResponse } from '@/types'

const route = useRoute()
const router = useRouter()

interface CustomerDetail {
  id: number
  uuid: string
  name: string | null
  phone: string | null
  email: string | null
  status: string
  created_at: string
  last_login_at: string | null
  order_count: number
  total_spent: number
  recent_orders: {
    uuid: string
    order_number: string
    status: string
    total: number
    payment_method: string
    payment_status: string
    created_at: string
  }[]
}

const customer = ref<CustomerDetail | null>(null)
const loading = ref(true)
const error = ref('')

function formatPrice(paise: number): string {
  return '\u20B9' + (paise / 100).toFixed(2)
}

function formatDate(d: string | null): string {
  if (!d) return 'Never'
  return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatDateTime(d: string): string {
  return new Date(d).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })
}

const statusColors: Record<string, string> = {
  delivered: 'bg-green-100 text-green-800',
  confirmed: 'bg-blue-100 text-blue-800',
  preparing: 'bg-purple-100 text-purple-800',
  cancelled: 'bg-red-100 text-red-800',
  pending_payment: 'bg-amber-100 text-amber-800',
}

async function loadCustomer() {
  loading.value = true
  try {
    const { data } = await apiClient.get<ApiResponse<CustomerDetail>>(`/admin/customers/${route.params.uuid}`)
    if (data.success && data.data) {
      customer.value = data.data
    } else {
      error.value = 'Customer not found'
    }
  } catch (e) {
    error.value = 'Failed to load customer'
  } finally {
    loading.value = false
  }
}

onMounted(loadCustomer)
</script>

<template>
  <div>
    <button @click="router.push('/customers')" class="text-sm text-blue-600 hover:text-blue-800 mb-4 inline-block">&larr; Back to Customers</button>

    <div v-if="loading" class="text-gray-500">Loading...</div>
    <div v-else-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">{{ error }}</div>

    <template v-else-if="customer">
      <!-- Header -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-2xl font-bold text-blue-600">
            {{ (customer.name || customer.phone || '?').charAt(0).toUpperCase() }}
          </div>
          <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-900">{{ customer.name || 'No Name' }}</h1>
            <p class="text-sm text-gray-500">{{ customer.phone || 'No phone' }} &middot; {{ customer.email || 'No email' }}</p>
          </div>
          <span class="px-3 py-1 rounded-full text-sm font-medium" :class="customer.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'">
            {{ customer.status }}
          </span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
          <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-xs text-gray-500">Total Orders</div>
            <div class="text-2xl font-bold text-gray-900">{{ customer.order_count }}</div>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-xs text-gray-500">Total Spent</div>
            <div class="text-2xl font-bold text-gray-900">{{ formatPrice(customer.total_spent) }}</div>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-xs text-gray-500">Last Login</div>
            <div class="text-sm font-medium text-gray-900 mt-1">{{ formatDate(customer.last_login_at) }}</div>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-xs text-gray-500">Joined</div>
            <div class="text-sm font-medium text-gray-900 mt-1">{{ formatDate(customer.created_at) }}</div>
          </div>
        </div>
      </div>

      <!-- Recent Orders -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Orders</h2>

        <div v-if="!customer.recent_orders?.length" class="text-center py-8 text-gray-400">
          <p class="text-sm">No orders yet.</p>
        </div>

        <table v-else class="w-full">
          <thead>
            <tr class="text-left text-xs text-gray-500 uppercase border-b">
              <th class="pb-2">Order</th><th class="pb-2">Status</th><th class="pb-2 text-right">Total</th><th class="pb-2">Payment</th><th class="pb-2">Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in customer.recent_orders" :key="o.uuid" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer" @click="router.push(`/orders/${o.uuid}`)">
              <td class="py-3 font-medium text-blue-600">{{ o.order_number }}</td>
              <td class="py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusColors[o.status] || 'bg-gray-100 text-gray-600'">{{ o.status.replace(/_/g, ' ') }}</span></td>
              <td class="py-3 text-right font-medium">{{ formatPrice(o.total) }}</td>
              <td class="py-3 text-sm text-gray-500 capitalize">{{ o.payment_method?.replace(/_/g, ' ') }} &middot; {{ o.payment_status }}</td>
              <td class="py-3 text-sm text-gray-500">{{ formatDateTime(o.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
