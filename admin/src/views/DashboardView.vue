<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { settingsApi } from '@/api/settings'
import { ordersApi } from '@/api/orders'
import { useAuthStore } from '@/stores/auth'
import type { DashboardStats } from '@/types'

const stats = ref<DashboardStats | null>(null)
const loading = ref(true)
const auth = useAuthStore()
const authName = computed(() => auth.user?.name?.split(' ')[0])

const totalActiveOrders = computed(() => {
  if (!stats.value) return 0
  return Object.values(stats.value.active_orders).reduce((total, count) => total + count, 0)
})

const statusEntries = computed(() => {
  if (!stats.value) return []
  return Object.entries(stats.value.active_orders)
    .map(([status, count]) => ({ status, count, width: totalActiveOrders.value ? Math.max((count / totalActiveOrders.value) * 100, 7) : 0 }))
    .sort((a, b) => b.count - a.count)
})

const maxRevenue = computed(() => {
  if (!stats.value?.weekly_revenue?.length) return 1
  return Math.max(...stats.value.weekly_revenue.map(d => Number(d.revenue)), 1)
})

const todayLabel = new Intl.DateTimeFormat('en-IN', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date())

const quickActions = [
  { label: 'Orders', hint: 'Accept & fulfil', path: '/orders', mark: 'OD', tone: 'orders' },
  { label: 'Catalog', hint: 'Products & stock', path: '/products', mark: 'CT', tone: 'catalog' },
  { label: 'Offers', hint: 'Coupons & promos', path: '/coupons', mark: '%', tone: 'offers' },
  { label: 'Delivery', hint: 'Drivers & zones', path: '/drivers', mark: 'DL', tone: 'delivery' },
]

function formatPrice(paise: number): string {
  return '₹' + (paise / 100).toFixed(2)
}

function formatPriceShort(paise: number): string {
  const rupees = paise / 100
  if (rupees >= 1000) return '₹' + (rupees / 1000).toFixed(1) + 'k'
  return '₹' + rupees.toFixed(0)
}

function formatStatus(status: string): string {
  return status.replace(/_/g, ' ')
}

function statusTone(status: string): string {
  if (status.includes('pending')) return 'status-dot--amber'
  if (status.includes('confirmed') || status.includes('preparing')) return 'status-dot--blue'
  if (status.includes('ready') || status.includes('picked')) return 'status-dot--lime'
  return 'status-dot--coral'
}

function formatDay(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-IN', { weekday: 'short' })
}

onMounted(async () => {
  try {
    // Try enhanced dashboard first, fallback to basic
    const { data } = await settingsApi.dashboardEnhanced()
    if (data.success && data.data) {
      stats.value = data.data as DashboardStats
    } else {
      const basicRes = await ordersApi.dashboard()
      if (basicRes.data.success && basicRes.data.data) {
        stats.value = basicRes.data.data
      }
    }
  } catch (e) {
    try {
      const { data } = await ordersApi.dashboard()
      if (data.success && data.data) stats.value = data.data
    } catch {
      console.error('Failed to load dashboard')
    }
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="dashboard-page">
    <section class="dashboard-intro">
      <div>
        <p class="dashboard-kicker">{{ todayLabel }}</p>
        <h1>Good morning, {{ authName || 'there' }}.</h1>
        <p class="dashboard-subtitle">Here's the rhythm of your store today.</p>
      </div>
      <router-link to="/orders" class="dashboard-action">
        <span>View all orders</span>
        <span aria-hidden="true">↗</span>
      </router-link>
    </section>

    <div v-if="loading" class="dashboard-loading" aria-live="polite">
      <div v-for="item in 4" :key="item" class="loading-block"></div>
    </div>

    <template v-else-if="stats">
      <section class="touch-launcher" aria-label="Store tools">
        <div class="touch-launcher__heading">
          <div><p>Run your store</p><h2>Quick actions</h2></div>
          <router-link to="/settings">Store settings</router-link>
        </div>
        <div class="touch-launcher__grid">
          <router-link v-for="action in quickActions" :key="action.path" :to="action.path" class="touch-tile" :class="`touch-tile--${action.tone}`">
            <span class="touch-tile__mark">{{ action.mark }}</span>
            <span><strong>{{ action.label }}</strong><small>{{ action.hint }}</small></span>
            <b aria-hidden="true">›</b>
          </router-link>
        </div>
      </section>

      <section class="metric-grid">
        <article class="metric-card metric-card--dark">
          <div class="metric-card__top"><span>Today's revenue</span><span class="metric-icon">₹</span></div>
          <strong>{{ formatPrice(stats.today_revenue) }}</strong>
          <p><span class="metric-trend">↑</span> Collected across today's orders</p>
          <div class="metric-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
        </article>
        <article class="metric-card">
          <div class="metric-card__top"><span>Orders today</span><span class="metric-icon metric-icon--coral">↗</span></div>
          <strong>{{ stats.today_orders }}</strong>
          <p>Fresh orders placed today</p>
        </article>
        <article class="metric-card">
          <div class="metric-card__top"><span>Avg. order value</span><span class="metric-icon metric-icon--lime">◎</span></div>
          <strong>{{ stats.avg_order_value ? formatPrice(stats.avg_order_value) : '—' }}</strong>
          <p>Average across all orders</p>
        </article>
        <article class="metric-card">
          <div class="metric-card__top"><span>Customers</span><span class="metric-icon metric-icon--blue">✦</span></div>
          <strong>{{ stats.total_customers }}</strong>
          <p>Total customer relationships</p>
        </article>
      </section>

      <section class="dashboard-columns">
        <!-- Weekly Revenue Chart -->
        <article class="panel" v-if="stats.weekly_revenue?.length">
          <div class="panel-heading">
            <div><p class="panel-kicker">Revenue trend</p><h2>Last 7 days</h2></div>
          </div>
          <div class="chart-container">
            <div class="chart-bars">
              <div v-for="day in stats.weekly_revenue" :key="day.date" class="chart-bar-group">
                <div class="chart-bar-value">{{ formatPriceShort(Number(day.revenue)) }}</div>
                <div class="chart-bar" :style="{ height: `${Math.max((Number(day.revenue) / maxRevenue) * 140, 4)}px` }"></div>
                <div class="chart-bar-label">{{ formatDay(day.date) }}</div>
                <div class="chart-bar-orders">{{ day.orders }} ord.</div>
              </div>
            </div>
          </div>
        </article>

        <article v-else class="panel order-pulse-panel">
          <div class="panel-heading">
            <div><p class="panel-kicker">Live pulse</p><h2>Orders in motion</h2></div>
            <span class="panel-count">{{ totalActiveOrders }} active</span>
          </div>
          <div v-if="statusEntries.length" class="pulse-content">
            <div class="pulse-ring"><strong>{{ totalActiveOrders }}</strong><span>active<br>orders</span></div>
            <div class="pulse-list">
              <div v-for="entry in statusEntries" :key="entry.status" class="pulse-row">
                <div class="pulse-label"><span class="status-dot" :class="statusTone(entry.status)"></span><span>{{ formatStatus(entry.status) }}</span><strong>{{ entry.count }}</strong></div>
                <div class="pulse-bar"><i :style="{ width: `${entry.width}%` }"></i></div>
              </div>
            </div>
          </div>
          <div v-else class="empty-state"><span>✦</span><p>No active orders right now.</p><small>Your next order will appear here.</small></div>
        </article>

        <article class="panel action-panel">
          <div class="panel-heading"><div><p class="panel-kicker">{{ stats.top_products?.length ? 'Top sellers' : 'Shortcuts' }}</p><h2>{{ stats.top_products?.length ? 'Popular Products' : 'Keep momentum' }}</h2></div><span class="spark-mark">✦</span></div>

          <!-- Top Products -->
          <div v-if="stats.top_products?.length" class="top-products-list">
            <router-link v-for="(prod, i) in stats.top_products" :key="i" :to="`/products?search=${encodeURIComponent(prod.product_name)}`" class="top-product-item" style="text-decoration:none">
              <span class="top-product-rank">{{ i + 1 }}</span>
              <div class="flex-1">
                <strong>{{ prod.product_name }}</strong>
                <small>{{ prod.total_qty }} sold</small>
              </div>
              <span class="top-product-revenue">{{ formatPrice(Number(prod.total_revenue)) }}</span>
            </router-link>
          </div>

          <!-- Shortcuts fallback -->
          <div v-else class="shortcut-list">
            <router-link to="/orders" class="shortcut-item"><span class="shortcut-icon shortcut-icon--orders">↗</span><span><strong>Review orders</strong><small>See what needs attention</small></span><b>→</b></router-link>
            <router-link to="/products" class="shortcut-item"><span class="shortcut-icon shortcut-icon--products">+</span><span><strong>Manage products</strong><small>Keep your catalog fresh</small></span><b>→</b></router-link>
            <router-link to="/categories" class="shortcut-item"><span class="shortcut-icon shortcut-icon--categories">◌</span><span><strong>Organize categories</strong><small>Shape the storefront</small></span><b>→</b></router-link>
          </div>
        </article>
      </section>

      <!-- Active Orders Section -->
      <section v-if="statusEntries.length && stats.weekly_revenue?.length" class="mt-6">
        <article class="panel order-pulse-panel">
          <div class="panel-heading">
            <div><p class="panel-kicker">Live pulse</p><h2>Orders in motion</h2></div>
            <span class="panel-count">{{ totalActiveOrders }} active</span>
          </div>
          <div class="pulse-content">
            <div class="pulse-ring"><strong>{{ totalActiveOrders }}</strong><span>active<br>orders</span></div>
            <div class="pulse-list">
              <div v-for="entry in statusEntries" :key="entry.status" class="pulse-row">
                <div class="pulse-label"><span class="status-dot" :class="statusTone(entry.status)"></span><span>{{ formatStatus(entry.status) }}</span><strong>{{ entry.count }}</strong></div>
                <div class="pulse-bar"><i :style="{ width: `${entry.width}%` }"></i></div>
              </div>
            </div>
          </div>
        </article>
      </section>
      <!-- Low Stock Alerts -->
      <section v-if="stats.low_stock?.length" class="mt-6">
        <article class="panel" style="border-color:#f1c3b8">
          <div class="panel-heading">
            <div><p class="panel-kicker" style="color:#a64f3e">Stock alerts</p><h2>Low Stock Products</h2></div>
            <span class="panel-count" style="background:#fff5f2;color:#a64f3e">{{ stats.low_stock.length }} items</span>
          </div>
          <div class="top-products-list">
            <router-link v-for="item in stats.low_stock" :key="item.uuid" :to="`/products/${item.uuid}`" class="top-product-item" style="text-decoration:none">
              <span class="top-product-rank" style="background:#fce3dc;color:#a64f3e">!</span>
              <div class="flex-1">
                <strong>{{ item.name }}</strong>
                <small style="color:#a64f3e">{{ item.stock_quantity }} left</small>
              </div>
              <span class="top-product-revenue" style="color:#a64f3e">Restock</span>
            </router-link>
          </div>
        </article>
      </section>
    </template>

    <div v-else class="dashboard-error"><strong>We couldn't load the dashboard.</strong><span>Refresh the page and try again.</span></div>
  </div>
</template>

<style scoped>
.dashboard-page { animation: dashboard-enter .55s ease both; }
.dashboard-intro { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 32px; }
.dashboard-kicker, .panel-kicker { margin: 0 0 9px; color: #dc2626; font-size: 10px; font-weight: 900; letter-spacing: 1.5px; text-transform: uppercase; }
.dashboard-intro h1 { margin: 0; color: #1a1a2e; font-size: clamp(26px, 3vw, 40px); line-height: 1.06; letter-spacing: -1.8px; }
.dashboard-subtitle { margin: 10px 0 0; color: #6a6a7a; font-size: 13px; }
.dashboard-action { display: inline-flex; align-items: center; gap: 14px; padding: 12px 16px; border: 1px solid #e5e5ea; border-radius: 11px; color: #1a1a2e; background: #fff; font-size: 12px; font-weight: 800; text-decoration: none; box-shadow: 0 6px 18px rgba(20,20,30,.04); transition: .2s ease; }
.dashboard-action:hover { border-color: #dc2626; transform: translateY(-2px); }
.metric-grid { display: grid; grid-template-columns: 1.45fr repeat(3, 1fr); gap: 14px; margin-bottom: 26px; }
.metric-card { min-height: 164px; padding: 20px; border: 1px solid #e5e5ea; border-radius: 16px; background: #fff; box-shadow: 0 8px 24px rgba(20,20,30,.035); }
.metric-card--dark { position: relative; overflow: hidden; border-color: #1a1a2e; color: #eeeef2; background: #1a1a2e; }
.metric-card__top { display: flex; align-items: center; justify-content: space-between; color: #6a6a7a; font-size: 11px; font-weight: 800; }
.metric-card--dark .metric-card__top { color: #9a9aaa; }
.metric-card strong { display: block; margin-top: 20px; color: #1a1a2e; font-size: 32px; letter-spacing: -1.5px; }
.metric-card--dark strong { color: #eeeef2; }
.metric-card p { margin: 8px 0 0; color: #8a8a9a; font-size: 11px; }
.metric-card--dark p { color: #9a9aaa; }
.metric-icon { display: grid; width: 27px; height: 27px; place-items: center; border-radius: 9px; color: #991b1b; background: #fee2e2; font-size: 14px; font-weight: 900; }
.metric-icon--coral { color: #991b1b; background: #fecaca; }.metric-icon--lime { color: #991b1b; background: #fee2e2; }.metric-icon--blue { color: #4a4a5a; background: #f0f0f2; }
.metric-trend { color: #f87171; font-weight: 900; }.metric-wave { position: absolute; right: 18px; bottom: 0; display: flex; align-items: flex-end; gap: 5px; height: 48px; opacity: .7; }.metric-wave i { display: block; width: 5px; border-radius: 5px 5px 0 0; background: #f87171; }.metric-wave i:nth-child(1) { height: 18px; }.metric-wave i:nth-child(2) { height: 28px; }.metric-wave i:nth-child(3) { height: 21px; }.metric-wave i:nth-child(4) { height: 39px; }.metric-wave i:nth-child(5) { height: 30px; }.metric-wave i:nth-child(6) { height: 44px; }.metric-wave i:nth-child(7) { height: 35px; }
.dashboard-columns { display: grid; grid-template-columns: 1.25fr .75fr; gap: 14px; }
.panel { min-height: 340px; padding: 25px; border: 1px solid #e5e5ea; border-radius: 16px; background: #fff; box-shadow: 0 8px 24px rgba(20,20,30,.035); }.panel-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }.panel-kicker { margin-bottom: 6px; color: #8a8a9a; }.panel h2 { margin: 0; color: #1a1a2e; font-size: 18px; letter-spacing: -.5px; }.panel-count { padding: 7px 10px; border-radius: 20px; color: #5a5a6a; background: #f0f0f2; font-size: 10px; font-weight: 800; }.spark-mark { color: #dc2626; font-size: 22px; }
.pulse-content { display: flex; align-items: center; gap: 34px; padding: 42px 12px 16px; }.pulse-ring { display: flex; width: 150px; height: 150px; flex: 0 0 150px; flex-direction: column; align-items: center; justify-content: center; border: 13px solid #fecaca; border-right-color: #f87171; border-bottom-color: #dc2626; border-radius: 50%; }.pulse-ring strong { color: #1a1a2e; font-size: 34px; letter-spacing: -1px; }.pulse-ring span { color: #7a7a8a; font-size: 10px; line-height: 1.3; text-align: center; text-transform: uppercase; }.pulse-list { display: grid; flex: 1; gap: 18px; }.pulse-label { display: flex; align-items: center; gap: 8px; color: #5a5a6a; font-size: 11px; text-transform: capitalize; }.pulse-label strong { margin-left: auto; color: #1a1a2e; }.status-dot { width: 8px; height: 8px; border-radius: 50%; background: #bbbbc6; }.status-dot--amber { background: #e9ae57; }.status-dot--blue { background: #6eabb4; }.status-dot--lime { background: #dc2626; }.status-dot--coral { background: #f0785f; }.pulse-bar { height: 5px; margin-top: 8px; overflow: hidden; border-radius: 5px; background: #f0f0f2; }.pulse-bar i { display: block; height: 100%; border-radius: inherit; background: #f87171; }
/* Chart styles */
.chart-container { padding: 30px 0 10px; }
.chart-bars { display: flex; align-items: flex-end; justify-content: space-around; gap: 8px; min-height: 200px; }
.chart-bar-group { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; }
.chart-bar { width: 100%; max-width: 48px; background: linear-gradient(180deg, #f87171, #dc2626); border-radius: 6px 6px 0 0; transition: height .4s ease; }
.chart-bar-value { color: #1a1a2e; font-size: 10px; font-weight: 800; }
.chart-bar-label { color: #5a5a6a; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.chart-bar-orders { color: #8a8a9a; font-size: 9px; }
/* Top products */
.top-products-list { display: grid; gap: 6px; margin-top: 20px; }
.top-product-item { display: flex; align-items: center; gap: 10px; padding: 10px 8px; border-radius: 10px; transition: .2s; }
.top-product-item:hover { background: #fef8f8; }
.top-product-rank { display: grid; width: 26px; height: 26px; place-items: center; border-radius: 8px; background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 900; }
.top-product-item strong { display: block; font-size: 12px; color: #1a1a2e; }
.top-product-item small { display: block; margin-top: 2px; color: #8a8a9a; font-size: 10px; }
.top-product-revenue { color: #1a1a2e; font-size: 12px; font-weight: 800; }
.shortcut-list { display: grid; gap: 9px; margin-top: 25px; }.shortcut-item { display: flex; align-items: center; gap: 12px; padding: 12px 10px; border-radius: 12px; color: #1a1a2e; text-decoration: none; transition: .2s ease; }.shortcut-item:hover { background: #fef8f8; transform: translateX(3px); }.shortcut-icon { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 10px; font-size: 18px; font-weight: 700; }.shortcut-icon--orders { color: #991b1b; background: #fee2e2; }.shortcut-icon--products { color: #991b1b; background: #fecaca; }.shortcut-icon--categories { color: #4a4a5a; background: #f0f0f2; }.shortcut-item span:nth-child(2) { flex: 1; }.shortcut-item strong, .shortcut-item small { display: block; }.shortcut-item strong { font-size: 12px; }.shortcut-item small { margin-top: 4px; color: #8a8a9a; font-size: 10px; }.shortcut-item b { color: #9a9aaa; font-size: 16px; }.empty-state { display: flex; min-height: 230px; flex-direction: column; align-items: center; justify-content: center; color: #7a7a8a; text-align: center; }.empty-state span { color: #dc2626; font-size: 28px; }.empty-state p { margin: 10px 0 4px; color: #4a4a5a; font-size: 13px; font-weight: 800; }.empty-state small { font-size: 11px; }.dashboard-loading { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }.loading-block { height: 164px; border-radius: 16px; background: linear-gradient(100deg, #f0f0f2 30%, #fafafa 50%, #f0f0f2 70%); background-size: 200% 100%; animation: loading-shimmer 1.3s infinite; }.dashboard-error { display: grid; gap: 7px; padding: 26px; border: 1px solid #fecaca; border-radius: 16px; color: #991b1b; background: #fef2f2; font-size: 13px; }.dashboard-error span { color: #b91c1c; font-size: 11px; }
@keyframes dashboard-enter { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } } @keyframes loading-shimmer { to { background-position: -200% 0; } }
@media (max-width: 1100px) { .metric-grid { grid-template-columns: repeat(2, 1fr); }.metric-card--dark { grid-column: span 2; }.dashboard-columns { grid-template-columns: 1fr; } }
@media (max-width: 640px) { .dashboard-intro { align-items: flex-start; flex-direction: column; }.dashboard-action { width: 100%; justify-content: space-between; }.metric-grid, .dashboard-loading { grid-template-columns: 1fr; }.metric-card--dark { grid-column: auto; }.pulse-content { flex-direction: column; gap: 26px; padding-top: 30px; }.pulse-ring { width: 132px; height: 132px; flex-basis: 132px; }.pulse-list { width: 100%; }.panel { padding: 20px; } }

.touch-launcher { margin-bottom: 22px; padding: 18px; border: 1px solid #e7e9ee; border-radius: 20px; background: #fff; box-shadow: 0 10px 28px rgba(20,20,30,.05); }
.touch-launcher__heading { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }.touch-launcher__heading p { margin: 0 0 3px; color: #7a7a8a; font-size: 10px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }.touch-launcher__heading h2 { margin: 0; font-size: 17px; letter-spacing: -.4px; }.touch-launcher__heading a { color: var(--primary); font-size: 12px; font-weight: 800; text-decoration: none; }
.touch-launcher__grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }.touch-tile { position: relative; display: flex; min-height: 94px; align-items: center; gap: 10px; padding: 13px; overflow: hidden; border: 1px solid #edf0f3; border-radius: 15px; color: #1a1a2e; background: #fafbfc; text-decoration: none; transition: transform .18s ease, box-shadow .18s ease; }.touch-tile:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(20,20,30,.1); }.touch-tile__mark { display: grid; width: 34px; height: 34px; flex: 0 0 34px; place-items: center; border-radius: 11px; color: #fff; background: #1a1a2e; font-size: 11px; font-weight: 900; }.touch-tile strong, .touch-tile small { display: block; }.touch-tile strong { font-size: 13px; }.touch-tile small { margin-top: 3px; color: #6a6a7a; font-size: 10px; }.touch-tile b { position: absolute; right: 10px; bottom: 7px; color: #a0a4af; font-size: 18px; }.touch-tile--orders .touch-tile__mark { background: #2563eb; }.touch-tile--catalog .touch-tile__mark { background: #059669; }.touch-tile--offers .touch-tile__mark { background: #d97706; }.touch-tile--delivery .touch-tile__mark { background: #7c3aed; }
@media (max-width: 640px) { .touch-launcher { margin: 0 -4px 18px; padding: 15px; border-radius: 18px; }.touch-launcher__grid { grid-template-columns: repeat(2, 1fr); }.touch-tile { min-height: 108px; align-items: flex-start; flex-direction: column; gap: 8px; }.touch-tile__mark { width: 38px; height: 38px; }.touch-tile b { right: 12px; bottom: 9px; } }

/* Home-screen tiles deliberately replace the old report-first dashboard feeling. */
.dashboard-page { max-width: 1320px; }
.dashboard-intro { min-height: 220px; padding: 28px 32px; border: 0; border-radius: 28px; color: #fff; background: linear-gradient(135deg, #17172d 0%, #27234b 58%, var(--primary) 180%); box-shadow: 0 18px 38px rgba(23,23,45,.18); }
.dashboard-intro h1 { color: #fff; background: none; -webkit-text-fill-color: initial; }.dashboard-kicker { color: #f7a2a2; }.dashboard-subtitle { color: #c8c6d9; }.dashboard-action { min-height: 52px; border: 0; border-radius: 16px; color: #17172d; background: #fff; }
.touch-launcher { padding: 0; border: 0; border-radius: 0; background: transparent; box-shadow: none; }.touch-launcher__heading { margin: 0 2px 12px; }.touch-launcher__grid { gap: 14px; }.touch-tile { min-height: 132px; align-items: flex-start; flex-direction: column; gap: 13px; padding: 18px; border: 0; border-radius: 22px; background: #fff; box-shadow: 0 9px 22px rgba(20,20,30,.06); }.touch-tile__mark { width: 42px; height: 42px; flex-basis: 42px; border-radius: 14px; }.touch-tile strong { font-size: 15px; }.touch-tile small { font-size: 11px; }.touch-tile b { right: 16px; bottom: 13px; font-size: 22px; }
@media (min-width: 1024px) { .metric-grid { grid-template-columns: repeat(4, 1fr); }.metric-card { min-height: 144px; border-radius: 22px; }.metric-card--dark { grid-column: auto; }.dashboard-columns { grid-template-columns: 1.1fr .9fr; }.panel { border-radius: 22px; } }
</style>
