<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ordersApi } from '@/api/orders'
import type { Order, OrderItem, StatusHistory, Driver } from '@/types'

const route = useRoute()
const router = useRouter()

const order = ref<Order | null>(null)
const items = ref<OrderItem[]>([])
const history = ref<StatusHistory[]>([])
const allowedTransitions = ref<string[]>([])
const loading = ref(true)
const updating = ref(false)
const error = ref('')
const success = ref('')

// Driver assignment
const showDriverModal = ref(false)
const drivers = ref<Driver[]>([])
const selectedDriver = ref('')
const loadingDrivers = ref(false)

// Refund
const showRefundModal = ref(false)
const refundReason = ref('')

// Status notes
const statusNotes = ref('')

function formatPrice(paise: number): string {
  return '₹' + (paise / 100).toFixed(2)
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })
}

function parseSnapshot(json: string): Record<string, unknown> {
  try { return JSON.parse(json) } catch { return {} }
}

const address = computed(() => {
  if (!order.value?.address_snapshot) return null
  return parseSnapshot(order.value.address_snapshot)
})

async function loadOrder() {
  loading.value = true
  try {
    const { data } = await ordersApi.get(route.params.uuid as string)
    if (data.success && data.data) {
      order.value = data.data.order
      items.value = data.data.items
      history.value = data.data.status_history
      allowedTransitions.value = data.data.allowed_transitions
    }
  } catch (e) {
    console.error('Failed to load order', e)
  } finally {
    loading.value = false
  }
}

async function updateStatus(newStatus: string) {
  if (!order.value) return
  updating.value = true
  error.value = ''

  try {
    const { data } = await ordersApi.updateStatus(order.value.uuid, newStatus, statusNotes.value || undefined)
    if (data.success) {
      statusNotes.value = ''
      await loadOrder()
    } else {
      error.value = data.error?.message || 'Failed to update status'
    }
  } catch (e) {
    error.value = 'Failed to update status'
  } finally {
    updating.value = false
  }
}

// Driver assignment
async function openDriverModal() {
  loadingDrivers.value = true
  showDriverModal.value = true
  try {
    const { data } = await ordersApi.availableDrivers()
    if (data.success) drivers.value = data.data || []
  } catch (e) {
    error.value = 'Failed to load drivers'
  } finally {
    loadingDrivers.value = false
  }
}

async function assignDriver() {
  if (!order.value || !selectedDriver.value) return
  updating.value = true
  try {
    const { data } = await ordersApi.assignDriver(order.value.uuid, selectedDriver.value)
    if (data.success) {
      showDriverModal.value = false
      success.value = 'Driver assigned successfully'
      setTimeout(() => success.value = '', 3000)
      await loadOrder()
    } else {
      error.value = data.error?.message || 'Failed to assign driver'
    }
  } catch (e) {
    error.value = 'Failed to assign driver'
  } finally {
    updating.value = false
  }
}

// Refund
async function processRefund() {
  if (!order.value || !refundReason.value) return
  updating.value = true
  try {
    const { data } = await ordersApi.refund(order.value.uuid, refundReason.value)
    if (data.success) {
      showRefundModal.value = false
      refundReason.value = ''
      success.value = 'Refund initiated successfully'
      setTimeout(() => success.value = '', 3000)
      await loadOrder()
    } else {
      error.value = data.error?.message || 'Failed to process refund'
    }
  } catch (e) {
    error.value = 'Failed to process refund'
  } finally {
    updating.value = false
  }
}

function getAddonsList(addonsJson: string | null): { name: string; price: number }[] {
  if (!addonsJson) return []
  try {
    const parsed = JSON.parse(addonsJson)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

const statusColors: Record<string, string> = {
  pending_payment: 'bg-amber-600', confirmed: 'bg-red-600', accepted: 'bg-indigo-600',
  preparing: 'bg-purple-600', ready: 'bg-green-600', picked_up: 'bg-teal-600',
  out_for_delivery: 'bg-cyan-600', delivered: 'bg-emerald-600',
  cancelled: 'bg-red-600', rejected: 'bg-red-600',
}

const canAssignDriver = computed(() => {
  const s = order.value?.status
  return s === 'ready' || s === 'preparing' || s === 'accepted'
})

const canRefund = computed(() => {
  return order.value?.payment_status === 'paid' && order.value?.status !== 'cancelled'
})

function printReceipt() {
  const w = window.open('', '_blank', 'width=400,height=600')
  if (!w || !order.value) return
  const o = order.value
  const itemsHtml = items.value.map(item => {
    const snap = parseSnapshot(item.product_snapshot) as any
    const variant = item.variant_snapshot ? ` - ${(parseSnapshot(item.variant_snapshot) as any).name}` : ''
    const addons = getAddonsList(item.addons_snapshot).map(a => `<div style="font-size:11px;color:#666;margin-left:16px">+ ${a.name} ${a.price ? formatPrice(a.price) : ''}</div>`).join('')
    return `<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dotted #ddd">
      <div><strong>${snap.name}${variant}</strong><br><span style="font-size:11px;color:#666">${item.quantity} x ${formatPrice(item.unit_price)}</span>${addons}</div>
      <div style="font-weight:600">${formatPrice(item.line_total)}</div></div>`
  }).join('')

  const addr = address.value
  const addrHtml = addr ? `<p style="margin:4px 0;font-size:12px">${addr.address_line_1 || ''}${addr.address_line_2 ? ', ' + addr.address_line_2 : ''}${addr.city ? ', ' + addr.city : ''} ${addr.postal_code || ''}</p>` : ''

  w.document.write(`<!DOCTYPE html><html><head><title>Receipt - ${o.order_number}</title>
    <style>body{font-family:Arial,sans-serif;max-width:360px;margin:20px auto;font-size:13px}
    h1{font-size:18px;margin:0}h2{font-size:14px;margin:12px 0 6px}
    .total{display:flex;justify-content:space-between;font-weight:700;font-size:16px;border-top:2px solid #000;padding-top:8px;margin-top:8px}
    .line{display:flex;justify-content:space-between;font-size:12px;padding:2px 0}
    @media print{body{margin:0}}</style></head><body>
    <div style="text-align:center;margin-bottom:12px"><h1>Order Receipt</h1>
    <p style="margin:4px 0;color:#666">${o.order_number}</p>
    <p style="margin:4px 0;color:#666">${formatDate(o.created_at)}</p></div>
    <hr><h2>Customer</h2><p style="margin:4px 0">${o.customer_name || 'Guest'} ${o.customer_phone ? '- ' + o.customer_phone : ''}</p>
    ${addrHtml}<hr><h2>Items</h2>${itemsHtml}
    <div style="margin-top:8px">
    <div class="line"><span>Subtotal</span><span>${formatPrice(o.subtotal)}</span></div>
    ${o.delivery_fee > 0 ? `<div class="line"><span>Delivery</span><span>${formatPrice(o.delivery_fee)}</span></div>` : ''}
    ${o.tax_amount > 0 ? `<div class="line"><span>Tax</span><span>${formatPrice(o.tax_amount)}</span></div>` : ''}
    ${o.discount_amount > 0 ? `<div class="line" style="color:green"><span>Discount</span><span>-${formatPrice(o.discount_amount)}</span></div>` : ''}
    </div><div class="total"><span>Total</span><span>${formatPrice(o.total)}</span></div>
    <div style="text-align:center;margin-top:16px;font-size:11px;color:#888">
    <p>Payment: ${o.payment_method?.replace(/_/g, ' ')} (${o.payment_status})</p>
    <p>Thank you for your order!</p></div>
    <scr` + `ipt>window.onload=function(){window.print()}</scr` + `ipt></body></html>`)
  w.document.close()
}

onMounted(loadOrder)
</script>

<template>
  <div>
    <button @click="router.push('/orders')" class="text-sm text-red-600 hover:text-red-800 mb-4 inline-block">← Back to Orders</button>

    <div v-if="loading" class="text-gray-500">Loading...</div>

    <div v-else-if="order" class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ order.order_number }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ formatDate(order.created_at) }}</p>
          </div>
          <div class="flex items-center gap-3">
            <button v-if="!['pending_payment', 'confirmed'].includes(order.status)" @click="printReceipt" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Print Receipt</button>
            <span class="px-4 py-1.5 rounded-full text-sm font-medium text-white capitalize" :class="statusColors[order.status] || 'bg-gray-600'">
              {{ order.status.replace(/_/g, ' ') }}
            </span>
          </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-6">
          <div>
            <div class="text-xs text-gray-500">Type</div>
            <div class="text-sm font-medium capitalize">{{ order.order_type }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Payment</div>
            <div class="text-sm font-medium capitalize">{{ order.payment_method?.replace(/_/g, ' ') }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Payment Status</div>
            <div class="text-sm font-medium capitalize">{{ order.payment_status }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Customer</div>
            <div class="text-sm font-medium">{{ order.customer_name || 'N/A' }}</div>
            <div v-if="order.customer_phone" class="text-xs text-gray-400">{{ order.customer_phone }}</div>
          </div>
          <div v-if="order.notes">
            <div class="text-xs text-gray-500">Notes</div>
            <div class="text-sm text-gray-700">{{ order.notes }}</div>
          </div>
        </div>
      </div>

      <!-- Alerts -->
      <div v-if="error" class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ error }}</div>
      <div v-if="success" class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ success }}</div>

      <!-- Actions -->
      <div v-if="allowedTransitions.length > 0 || canAssignDriver || canRefund" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Actions</h2>

        <!-- Status notes -->
        <div v-if="allowedTransitions.length" class="mb-3">
          <input v-model="statusNotes" type="text" placeholder="Optional notes for status change..." class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
        </div>

        <div class="flex flex-wrap gap-2">
          <button v-for="transition in allowedTransitions" :key="transition" @click="updateStatus(transition)" :disabled="updating"
            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
            :class="transition === 'cancelled' ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-red-100 text-red-700 hover:bg-red-200'"
          >{{ transition.replace(/_/g, ' ') }}</button>

          <button v-if="canAssignDriver" @click="openDriverModal" class="px-4 py-2 bg-purple-100 text-purple-700 hover:bg-purple-200 rounded-lg text-sm font-medium">
            Assign Driver
          </button>

          <button v-if="canRefund" @click="showRefundModal = true" class="px-4 py-2 bg-orange-100 text-orange-700 hover:bg-orange-200 rounded-lg text-sm font-medium">
            Initiate Refund
          </button>
        </div>
      </div>

      <!-- Items -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Items</h2>
        <div class="divide-y divide-gray-100">
          <div v-for="item in items" :key="item.id" class="py-3">
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="font-medium text-gray-900">
                  {{ (parseSnapshot(item.product_snapshot) as any).name }}
                  <span v-if="item.variant_snapshot" class="text-gray-500 text-sm"> — {{ (parseSnapshot(item.variant_snapshot) as any).name }}</span>
                </div>
                <div class="text-sm text-gray-500">{{ item.quantity }} × {{ formatPrice(item.unit_price) }}</div>
                <!-- Addons breakdown -->
                <div v-if="item.addons_snapshot" class="mt-1">
                  <div v-for="addon in getAddonsList(item.addons_snapshot)" :key="addon.name" class="text-xs text-gray-400 ml-4">
                    + {{ addon.name }} <span v-if="addon.price">({{ formatPrice(addon.price) }})</span>
                  </div>
                </div>
                <div v-if="item.notes" class="text-xs text-gray-400 mt-1 italic">Note: {{ item.notes }}</div>
              </div>
              <div class="font-medium text-gray-900">{{ formatPrice(item.line_total) }}</div>
            </div>
          </div>
        </div>

        <div class="border-t border-gray-200 mt-4 pt-4 space-y-2">
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">Subtotal</span>
            <span>{{ formatPrice(order.subtotal) }}</span>
          </div>
          <div v-if="order.delivery_fee > 0" class="flex justify-between text-sm">
            <span class="text-gray-500">Delivery Fee</span>
            <span>{{ formatPrice(order.delivery_fee) }}</span>
          </div>
          <div v-if="order.tax_amount > 0" class="flex justify-between text-sm">
            <span class="text-gray-500">Tax</span>
            <span>{{ formatPrice(order.tax_amount) }}</span>
          </div>
          <div v-if="order.discount_amount > 0" class="flex justify-between text-sm text-green-600">
            <span>Discount</span>
            <span>-{{ formatPrice(order.discount_amount) }}</span>
          </div>
          <div class="flex justify-between text-base font-bold border-t pt-2">
            <span>Total</span>
            <span>{{ formatPrice(order.total) }}</span>
          </div>
        </div>
      </div>

      <!-- Delivery Address -->
      <div v-if="address" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Delivery Address</h2>
        <p class="text-sm text-gray-700">{{ address.address_line_1 }}</p>
        <p v-if="address.address_line_2" class="text-sm text-gray-500">{{ address.address_line_2 }}</p>
        <p class="text-sm text-gray-500">{{ address.city }} {{ address.postal_code }}</p>
        <p v-if="address.label" class="text-xs text-gray-400 mt-1">Label: {{ address.label }}</p>
      </div>

      <!-- Status History -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Status History</h2>
        <div class="space-y-3">
          <div v-for="(entry, i) in history" :key="i" class="flex items-start gap-3">
            <div class="w-2 h-2 rounded-full mt-2 shrink-0" :class="statusColors[entry.to_status] || 'bg-red-500'"></div>
            <div>
              <div class="text-sm font-medium text-gray-900 capitalize">{{ entry.to_status.replace(/_/g, ' ') }}</div>
              <div class="text-xs text-gray-500">
                {{ formatDate(entry.created_at) }}
                <span v-if="entry.actor_type"> · by {{ entry.actor_type }}</span>
              </div>
              <div v-if="entry.notes" class="text-xs text-gray-400 mt-0.5">{{ entry.notes }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Driver Assignment Modal -->
    <div v-if="showDriverModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Assign Delivery Driver</h2>
        <div v-if="loadingDrivers" class="text-gray-500 text-sm">Loading available drivers...</div>
        <div v-else-if="drivers.length === 0" class="text-gray-500 text-sm">No available drivers right now.</div>
        <div v-else class="space-y-2 mb-4">
          <label v-for="d in drivers" :key="d.uuid" class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
            :class="selectedDriver === d.uuid ? 'border-red-500 bg-red-50' : 'border-gray-200'">
            <input v-model="selectedDriver" :value="d.uuid" type="radio" class="text-red-600" />
            <div>
              <p class="text-sm font-medium">{{ d.name }}</p>
              <p class="text-xs text-gray-500">{{ d.phone }} · {{ d.vehicle_type }} {{ d.vehicle_number }}</p>
            </div>
          </label>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="showDriverModal = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
          <button @click="assignDriver" :disabled="!selectedDriver || updating" class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">Assign</button>
        </div>
      </div>
    </div>

    <!-- Refund Modal -->
    <div v-if="showRefundModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Initiate Refund</h2>
        <p class="text-sm text-gray-500 mb-4">This will initiate a refund of {{ formatPrice(order!.total) }} for order {{ order!.order_number }}.</p>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
          <textarea v-model="refundReason" rows="3" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Why is this refund being issued?" />
        </div>
        <div class="flex justify-end gap-3">
          <button @click="showRefundModal = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
          <button @click="processRefund" :disabled="!refundReason || updating" class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">Process Refund</button>
        </div>
      </div>
    </div>
  </div>
</template>
