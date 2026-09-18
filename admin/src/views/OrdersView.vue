<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ordersApi } from '@/api/orders'
import { downloadCsv } from '@/utils/csv'
import type { Order, OrderItem } from '@/types'

const router = useRouter()

type BoardOrder = Order & { items: OrderItem[]; customer_order_count?: number }

const allOrders = ref<BoardOrder[]>([])
const counts = ref<Record<string, number>>({})
const storeLocation = ref<{ latitude: number; longitude: number } | null>(null)
const loading = ref(true)
const activeTab = ref('new')
const searchQuery = ref('')
const actionMessage = ref('')
const actionError = ref('')
let refreshTimer: ReturnType<typeof setInterval> | null = null

// Status tab definitions matching the reference design
const statusTabs = [
  { key: 'new', label: 'New', statuses: ['confirmed', 'pending_payment'] },
  { key: 'assigned', label: 'Assigned', statuses: ['accepted'] },
  { key: 'cooking', label: 'Cooking', statuses: ['preparing'] },
  { key: 'out_for_delivery', label: 'Out for delivery', statuses: ['out_for_delivery'] },
  { key: 'complete', label: 'Complete', statuses: ['delivered', 'cancelled', 'rejected', 'refunded'] },
]

function tabCount(tab: typeof statusTabs[number]): number {
  return tab.statuses.reduce((sum, s) => sum + (counts.value[s] || 0), 0)
}

function tabColor(key: string): string {
  const colors: Record<string, string> = {
    new: '#17221f',
    assigned: '#6b7280',
    cooking: '#059669',
    out_for_delivery: '#0891b2',
    complete: '#6b7280',
  }
  return colors[key] || '#6b7280'
}

function tabBadgeColor(key: string): string {
  const colors: Record<string, string> = {
    new: '#17221f',
    assigned: '#6b7280',
    cooking: '#059669',
    out_for_delivery: '#059669',
    complete: '#6b7280',
  }
  return colors[key] || '#6b7280'
}

const filteredOrders = computed(() => {
  const tab = statusTabs.find(t => t.key === activeTab.value)
  if (!tab) return []
  let orders = allOrders.value.filter(o => tab.statuses.includes(o.status))

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    orders = orders.filter(o =>
      o.order_number?.toLowerCase().includes(q) ||
      o.customer_name?.toLowerCase().includes(q) ||
      o.customer_phone?.toLowerCase().includes(q)
    )
  }

  return orders
})

// Split filtered orders into "new" (unprocessed) and "in progress" sections
const newOrders = computed(() => {
  if (activeTab.value !== 'new') return []
  return filteredOrders.value.filter(o => o.status === 'confirmed' || o.status === 'pending_payment')
})

const inProgressOrders = computed(() => {
  if (activeTab.value === 'new') return []
  return filteredOrders.value
})

// Show all in single grid for non-new tabs
const showSingleGrid = computed(() => activeTab.value !== 'new')

function formatPrice(paise: number): string {
  return '\u20B9' + (paise / 100).toFixed(2)
}

function parseSnapshot(json: string): Record<string, unknown> {
  try { return JSON.parse(json) } catch { return {} }
}

function getAddonsList(addonsJson: string | null): { name: string; price: number }[] {
  if (!addonsJson) return []
  try {
    const parsed = JSON.parse(addonsJson)
    return Array.isArray(parsed) ? parsed : []
  } catch { return [] }
}

function getElapsedMinutes(createdAt: string): string {
  const diff = Date.now() - new Date(createdAt).getTime()
  const mins = Math.floor(diff / 60000)
  const hrs = Math.floor(mins / 60)
  if (hrs > 0) return `${hrs}:${String(mins % 60).padStart(2, '0')}`
  return `0:${String(mins).padStart(2, '0')}`
}

function timerColor(createdAt: string): string {
  const diff = Date.now() - new Date(createdAt).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins > 30) return '#ef4444'
  if (mins > 15) return '#f59e0b'
  return '#10b981'
}

function statusLabel(status: string): string {
  return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function fulfilmentLabel(order: BoardOrder): string {
  return order.order_type === 'pickup' ? 'Pickup' : 'Delivery'
}

function paymentLabel(order: BoardOrder): string {
  const m = order.payment_method || ''
  if (m === 'cod' || m === 'cash_on_delivery' || m.startsWith('pos_')) return m.startsWith('pos_') ? 'POS ' + m.replace('pos_', '').toUpperCase() : 'COD'
  if (m === 'online') return order.payment_status === 'paid' ? 'Paid online' : 'Online payment pending'
  return order.payment_status === 'paid' ? 'Paid' : m.replace(/_/g, ' ') || 'Unknown'
}

function statusLabelColor(status: string): string {
  const colors: Record<string, string> = {
    confirmed: '#059669',
    pending_payment: '#d97706',
    accepted: '#4f46e5',
    preparing: '#059669',
    out_for_delivery: '#ef4444',
    delivered: '#10b981',
    cancelled: '#ef4444',
    rejected: '#ef4444',
  }
  return colors[status] || '#6b7280'
}

function haversineKm(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const R = 6371
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLng = (lng2 - lng1) * Math.PI / 180
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

function getDeliveryDistance(order: Order): string | null {
  if (order.order_type !== 'delivery' || !storeLocation.value) return null
  try {
    const addr = JSON.parse(order.address_snapshot || '{}')
    if (!addr.latitude || !addr.longitude) return null
    const km = haversineKm(storeLocation.value.latitude, storeLocation.value.longitude, parseFloat(addr.latitude), parseFloat(addr.longitude))
    return km < 1 ? `${Math.round(km * 1000)}m` : `${km.toFixed(1)} km`
  } catch { return null }
}

function getItemCount(order: BoardOrder): number {
  return order.items.reduce((sum, i) => sum + (i.quantity || 1), 0)
}

function getItemsSummary(order: BoardOrder): string[] {
  return order.items.slice(0, 4).map(i => {
    const snap = parseSnapshot(i.product_snapshot) as any
    const qty = i.quantity > 1 ? `${i.quantity}× ` : ''
    return `${qty}${snap.name || 'Item'}`
  })
}

function customerInitial(order: Order): string {
  const name = order.customer_name || order.customer_phone || '?'
  return name.charAt(0).toUpperCase()
}

function avatarBg(order: Order): string {
  const colors = ['#fbbf24', '#f87171', '#60a5fa', '#34d399', '#a78bfa', '#fb923c', '#f472b6']
  const hash = (order.id || 0) % colors.length
  return colors[hash]
}

function parseAddress(order: Order): string {
  if (!order.address_snapshot) return ''
  try {
    const a = JSON.parse(order.address_snapshot)
    return [a.address_line_1, a.address_line_2, a.city].filter(Boolean).join(', ')
  } catch { return '' }
}

async function loadBoard() {
  try {
    const { data } = await ordersApi.board()
    if (data.success && data.data) {
      allOrders.value = data.data.orders
      counts.value = data.data.counts
      storeLocation.value = data.data.store_location || null
    }
  } catch (e) {
    console.error('Failed to load board', e)
  } finally {
    loading.value = false
  }
}

async function quickStatus(order: BoardOrder, newStatus: string) {
  actionMessage.value = ''
  actionError.value = ''
  try {
    await ordersApi.updateStatus(order.uuid, newStatus)
    await loadBoard()
    actionMessage.value = newStatus === 'accepted' ? 'Order accepted and ready for preparation.' : 'Order was declined.'
    setTimeout(() => actionMessage.value = '', 3500)
  } catch (e) {
    console.error('Failed to update status', e)
    actionError.value = 'We could not update this order. Please try again.'
  }
}

function exportOrders() {
  const headers = ['Order #', 'Customer', 'Phone', 'Status', 'Total (₹)', 'Payment', 'Date']
  const rows = allOrders.value.map(o => [
    o.order_number, o.customer_name || 'Guest', o.customer_phone || '',
    o.status, (o.total / 100).toFixed(2), o.payment_method || '', o.created_at,
  ])
  downloadCsv('orders.csv', headers, rows)
}

onMounted(() => {
  loadBoard()
  refreshTimer = setInterval(loadBoard, 30000)
})

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})
</script>

<template>
  <div class="ob">
    <div v-if="actionMessage" class="ob-feedback ob-feedback--success" role="status">{{ actionMessage }}</div>
    <div v-if="actionError" class="ob-feedback ob-feedback--error" role="alert">{{ actionError }}</div>
    <!-- Status tabs bar -->
    <div class="ob-tabs" style="position:relative">
      <button
        v-for="tab in statusTabs"
        :key="tab.key"
        class="ob-tab"
        :class="{ 'ob-tab--active': activeTab === tab.key }"
        @click="activeTab = tab.key"
      >
        <span class="ob-tab__count" :style="{ background: activeTab === tab.key ? '#fff' : tabBadgeColor(tab.key) + '18', color: activeTab === tab.key ? tabColor(tab.key) : tabBadgeColor(tab.key) }">
          {{ tabCount(tab) }}
        </span>
        {{ tab.label }}
      </button>
      <button @click="exportOrders" class="ob-export-btn" style="margin-left:auto;padding:6px 12px;font-size:11px;font-weight:700;color:#617069;background:#f0f4ef;border:none;border-radius:8px;cursor:pointer">Export CSV</button>
    </div>

    <div v-if="loading" class="ob-loading">Loading orders...</div>

    <template v-else>
      <!-- NEW tab: two sections -->
      <template v-if="activeTab === 'new'">
        <div class="ob-section-header">
          <h2>New</h2>
        </div>

        <div v-if="newOrders.length === 0" class="ob-empty">No new orders right now.</div>

        <div class="ob-card-grid" v-else>
          <div
            v-for="order in newOrders"
            :key="order.uuid"
            class="ob-card ob-card--new"
            @click="router.push(`/orders/${order.uuid}`)"
          >
            <div class="ob-card__head">
              <div class="ob-card__customer">
                <div class="ob-timer" :style="{ '--timer-color': timerColor(order.created_at) }">
                  <svg class="ob-timer__ring" viewBox="0 0 36 36">
                    <circle cx="18" cy="18" r="16" fill="none" stroke-width="2.5" stroke="currentColor" opacity="0.15"/>
                    <circle cx="18" cy="18" r="16" fill="none" stroke-width="2.5" stroke="currentColor" stroke-dasharray="100" stroke-dashoffset="20" stroke-linecap="round" transform="rotate(-90 18 18)"/>
                  </svg>
                  <span class="ob-timer__val">{{ getElapsedMinutes(order.created_at) }}</span>
                </div>
                <div>
                  <strong>{{ order.customer_name || 'Guest' }}</strong>
                  <span>{{ parseAddress(order) || order.customer_phone }}</span>
                </div>
              </div>
              <span class="ob-card__order-num">#{{ order.order_number?.replace('ORD-', '') }}</span>
            </div>

            <!-- Customer info row: phone + returning badge -->
            <div class="ob-card__customer-row">
              <a v-if="order.customer_phone" :href="`tel:${order.customer_phone}`" class="ob-phone-link" @click.stop title="Call customer">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                {{ order.customer_phone }}
              </a>
              <span v-if="(order.customer_order_count || 0) > 1" class="ob-returning-badge" :title="`${order.customer_order_count} total orders`">
                {{ order.customer_order_count }}{{ order.customer_order_count! >= 10 ? '+' : '' }} orders
              </span>
            </div>

            <!-- Items summary -->
            <div class="ob-card__items-summary">
              <div v-for="(name, idx) in getItemsSummary(order)" :key="idx" class="ob-item-line">{{ name }}</div>
              <div v-if="order.items.length > 4" class="ob-item-line ob-item-line--more">+{{ order.items.length - 4 }} more</div>
              <div class="ob-items-count">{{ getItemCount(order) }} {{ getItemCount(order) === 1 ? 'item' : 'items' }}</div>
            </div>

            <!-- Delivery distance -->
            <div v-if="order.order_type === 'delivery' && getDeliveryDistance(order)" class="ob-card__distance">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              {{ getDeliveryDistance(order) }} away
            </div>

            <div class="ob-card__total-row">
              <span>Total</span>
              <strong>{{ formatPrice(order.total) }}</strong>
            </div>

            <div class="ob-card__meta-row">
              <span class="ob-meta-pill" :class="order.order_type === 'pickup' ? 'ob-meta-pill--pickup' : 'ob-meta-pill--delivery'">{{ fulfilmentLabel(order) }}</span>
              <span class="ob-meta-pill" :class="order.payment_status === 'paid' ? 'ob-meta-pill--paid' : 'ob-meta-pill--pending'">{{ paymentLabel(order) }}</span>
              <span v-if="order.delivery_fee > 0" class="ob-meta-pill ob-meta-pill--fee">Fee {{ formatPrice(order.delivery_fee) }}</span>
            </div>

            <!-- Customer note -->
            <div v-if="order.notes" class="ob-card__note">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
              <span>{{ order.notes }}</span>
            </div>

            <!-- Quick actions for new orders -->
            <div class="ob-card__actions" @click.stop>
              <button class="ob-action-btn ob-action-btn--accept" @click="quickStatus(order, 'accepted')">Accept</button>
              <button class="ob-action-btn ob-action-btn--reject" @click="quickStatus(order, 'cancelled')">Reject</button>
            </div>
          </div>
        </div>

        <div class="ob-section-header">
          <h2>In progress</h2>
        </div>

        <div v-if="inProgressOrders.length === 0" class="ob-empty">No orders in progress yet.</div>

        <div class="ob-card-grid" v-else>
          <div
            v-for="order in inProgressOrders"
            :key="order.uuid"
            class="ob-card"
            @click="router.push(`/orders/${order.uuid}`)"
          >
            <div class="ob-card__head">
              <div class="ob-card__customer">
                <div class="ob-timer" :style="{ '--timer-color': timerColor(order.created_at) }">
                  <svg class="ob-timer__ring" viewBox="0 0 36 36">
                    <circle cx="18" cy="18" r="16" fill="none" stroke-width="2.5" stroke="currentColor" opacity="0.15"/>
                    <circle cx="18" cy="18" r="16" fill="none" stroke-width="2.5" stroke="currentColor" stroke-dasharray="100" stroke-dashoffset="20" stroke-linecap="round" transform="rotate(-90 18 18)"/>
                  </svg>
                  <span class="ob-timer__val">{{ getElapsedMinutes(order.created_at) }}</span>
                </div>
                <div>
                  <strong>{{ order.customer_name || 'Guest' }}</strong>
                  <span>{{ parseAddress(order) || order.customer_phone }}</span>
                </div>
              </div>
              <div class="ob-card__meta">
                <span class="ob-card__order-num">#{{ order.order_number?.replace('ORD-', '') }}</span>
                <span class="ob-card__status-label" :style="{ color: statusLabelColor(order.status) }">{{ statusLabel(order.status) }}</span>
              </div>
            </div>

            <!-- Items list -->
            <div class="ob-card__items">
              <div v-for="item in order.items" :key="item.id" class="ob-item">
                <div class="ob-item__check">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" :stroke="timerColor(order.created_at)" stroke-width="2" :fill="timerColor(order.created_at) + '15'"/>
                    <path d="M8 12l2.5 2.5L16 9.5" :stroke="timerColor(order.created_at)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
                <span class="ob-item__name">{{ (parseSnapshot(item.product_snapshot) as any).name }}
                  <template v-if="item.variant_snapshot"> - {{ (parseSnapshot(item.variant_snapshot) as any).name }}</template>
                </span>
                <span class="ob-item__price">{{ formatPrice(item.line_total) }}</span>
              </div>
              <!-- Addons below items -->
              <template v-for="item in order.items" :key="'addon-' + item.id">
                <div v-for="addon in getAddonsList(item.addons_snapshot)" :key="addon.name" class="ob-item ob-item--addon">
                  <div class="ob-item__check"></div>
                  <span class="ob-item__name">{{ addon.name }}</span>
                  <span class="ob-item__price">{{ formatPrice(addon.price) }}</span>
                </div>
              </template>
            </div>

            <div class="ob-card__total-row">
              <span>Total</span>
              <strong>{{ formatPrice(order.total) }}</strong>
            </div>
          </div>
        </div>
      </template>

      <!-- Other tabs: single grid with search -->
      <template v-else>
        <div class="ob-section-header">
          <h2>{{ statusTabs.find(t => t.key === activeTab)?.label }}</h2>
          <label class="ob-search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input v-model="searchQuery" type="search" placeholder="Search orders" />
          </label>
        </div>

        <div v-if="filteredOrders.length === 0" class="ob-empty">No orders in this status.</div>

        <div class="ob-card-grid" v-else>
          <div
            v-for="order in filteredOrders"
            :key="order.uuid"
            class="ob-card"
            @click="router.push(`/orders/${order.uuid}`)"
          >
            <div class="ob-card__head">
              <div class="ob-card__customer">
                <div class="ob-timer" :style="{ '--timer-color': timerColor(order.created_at) }">
                  <svg class="ob-timer__ring" viewBox="0 0 36 36">
                    <circle cx="18" cy="18" r="16" fill="none" stroke-width="2.5" stroke="currentColor" opacity="0.15"/>
                    <circle cx="18" cy="18" r="16" fill="none" stroke-width="2.5" stroke="currentColor" stroke-dasharray="100" stroke-dashoffset="20" stroke-linecap="round" transform="rotate(-90 18 18)"/>
                  </svg>
                  <span class="ob-timer__val">{{ getElapsedMinutes(order.created_at) }}</span>
                </div>
                <div>
                  <strong>{{ order.customer_name || 'Guest' }}</strong>
                  <span>{{ parseAddress(order) || order.customer_phone }}</span>
                </div>
              </div>
              <div class="ob-card__meta">
                <span class="ob-card__order-num">#{{ order.order_number?.replace('ORD-', '') }}</span>
                <span class="ob-card__status-label" :style="{ color: statusLabelColor(order.status) }">{{ statusLabel(order.status) }}</span>
              </div>
            </div>

            <!-- Items list -->
            <div class="ob-card__items">
              <div v-for="item in order.items" :key="item.id" class="ob-item">
                <div class="ob-item__check">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" :stroke="timerColor(order.created_at)" stroke-width="2" :fill="timerColor(order.created_at) + '15'"/>
                    <path d="M8 12l2.5 2.5L16 9.5" :stroke="timerColor(order.created_at)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
                <span class="ob-item__name">{{ (parseSnapshot(item.product_snapshot) as any).name }}
                  <template v-if="item.variant_snapshot"> - {{ (parseSnapshot(item.variant_snapshot) as any).name }}</template>
                </span>
                <span class="ob-item__price">{{ formatPrice(item.line_total) }}</span>
              </div>
              <!-- Addons below items -->
              <template v-for="item in order.items" :key="'addon-' + item.id">
                <div v-for="addon in getAddonsList(item.addons_snapshot)" :key="addon.name" class="ob-item ob-item--addon">
                  <div class="ob-item__check"></div>
                  <span class="ob-item__name">{{ addon.name }}</span>
                  <span class="ob-item__price">{{ formatPrice(addon.price) }}</span>
                </div>
              </template>
            </div>

            <div class="ob-card__total-row">
              <span>Total</span>
              <strong>{{ formatPrice(order.total) }}</strong>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>
