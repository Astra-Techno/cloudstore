<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ordersApi } from '@/api/orders'
import { useRouter } from 'vue-router'

const router = useRouter()

interface RefundableOrder {
  uuid: string
  order_number: string
  status: string
  total: number
  payment_method: string
  payment_status: string
  created_at: string
  customer_name?: string
}

const orders = ref<RefundableOrder[]>([])
const loading = ref(true)
const refundingUuid = ref<string | null>(null)
const refundReason = ref('')
const showRefundDialog = ref(false)
const selectedOrder = ref<RefundableOrder | null>(null)

function formatPrice(paise: number): string {
  return '₹' + (paise / 100).toFixed(2)
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function statusColor(status: string): string {
  if (status === 'refunded') return 'bg-green-100 text-green-800'
  if (status === 'cancelled') return 'bg-red-100 text-red-800'
  if (status === 'delivered' || status === 'picked_up') return 'bg-blue-100 text-blue-800'
  return 'bg-gray-100 text-gray-800'
}

async function loadOrders() {
  loading.value = true
  try {
    // Load orders that are eligible for refund or already refunded
    const statuses = ['delivered', 'picked_up', 'cancelled', 'refunded']
    const allOrders: RefundableOrder[] = []
    for (const status of statuses) {
      const { data } = await ordersApi.list(status)
      if (data.success && data.data) {
        allOrders.push(...(data.data as RefundableOrder[]))
      }
    }
    orders.value = allOrders.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
  } catch (e) {
    console.error('Failed to load orders', e)
  } finally {
    loading.value = false
  }
}

function openRefundDialog(order: RefundableOrder) {
  selectedOrder.value = order
  refundReason.value = ''
  showRefundDialog.value = true
}

async function processRefund() {
  if (!selectedOrder.value || !refundReason.value.trim()) return
  refundingUuid.value = selectedOrder.value.uuid
  try {
    const { data } = await ordersApi.refund(selectedOrder.value.uuid, refundReason.value.trim())
    if (data.success) {
      showRefundDialog.value = false
      await loadOrders()
    }
  } catch (e) {
    console.error('Refund failed', e)
  } finally {
    refundingUuid.value = null
  }
}

onMounted(loadOrders)
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Refund Management</h1>

    <div v-if="loading" class="text-center py-12 text-gray-500">Loading orders...</div>
    <div v-else-if="!orders.length" class="text-center py-12 text-gray-400">No orders found</div>
    <div v-else class="bg-white rounded-xl border overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 font-medium">Order</th>
            <th class="text-left px-4 py-3 font-medium">Status</th>
            <th class="text-left px-4 py-3 font-medium">Total</th>
            <th class="text-left px-4 py-3 font-medium">Payment</th>
            <th class="text-left px-4 py-3 font-medium">Date</th>
            <th class="text-right px-4 py-3 font-medium">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.uuid" class="border-b last:border-b-0 hover:bg-gray-50">
            <td class="px-4 py-3">
              <span class="font-medium cursor-pointer text-indigo-600 hover:text-indigo-800" @click="router.push(`/orders/${order.uuid}`)">
                {{ order.order_number }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusColor(order.status)">
                {{ order.status.replace(/_/g, ' ') }}
              </span>
            </td>
            <td class="px-4 py-3 font-medium">{{ formatPrice(order.total) }}</td>
            <td class="px-4 py-3 capitalize">{{ order.payment_method?.replace(/_/g, ' ') || '-' }}</td>
            <td class="px-4 py-3 text-gray-500">{{ formatDate(order.created_at) }}</td>
            <td class="px-4 py-3 text-right">
              <button
                v-if="order.status !== 'refunded'"
                @click="openRefundDialog(order)"
                class="text-red-600 hover:text-red-800 text-xs font-medium"
              >
                Refund
              </button>
              <span v-else class="text-green-600 text-xs font-medium">Refunded</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Refund dialog -->
    <div v-if="showRefundDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showRefundDialog = false">
      <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h2 class="text-lg font-bold mb-4">Process Refund</h2>
        <p class="text-sm text-gray-600 mb-2">Order: <strong>{{ selectedOrder?.order_number }}</strong></p>
        <p class="text-sm text-gray-600 mb-4">Amount: <strong>{{ formatPrice(selectedOrder?.total ?? 0) }}</strong></p>
        <textarea
          v-model="refundReason"
          rows="3"
          class="w-full border rounded-lg px-3 py-2 text-sm mb-4"
          placeholder="Reason for refund (required)"
        />
        <div class="flex justify-end gap-3">
          <button @click="showRefundDialog = false" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
          <button
            @click="processRefund"
            :disabled="!refundReason.trim() || refundingUuid !== null"
            class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50"
          >
            {{ refundingUuid ? 'Processing...' : 'Confirm Refund' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
