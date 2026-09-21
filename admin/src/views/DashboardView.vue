<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { settingsApi } from '@/api/settings'
import { ordersApi } from '@/api/orders'
import { platformApi } from '@/api/platform'
import { useAuthStore } from '@/stores/auth'
import type { DashboardStats } from '@/types'

const stats = ref<DashboardStats | null>(null)
const platformStats = ref<Record<string, number> | null>(null)
const loading = ref(true)
const auth = useAuthStore()
const authName = computed(() => auth.user?.name?.split(' ')[0])
const isPlatformAdmin = computed(() => auth.user?.role === 'platform_admin')

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

const hour = new Date().getHours()
const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'
const todayShort = new Intl.DateTimeFormat('en-IN', { weekday: 'short', day: 'numeric', month: 'short' }).format(new Date())

const quickActions = [
  { label: 'Orders', hint: 'Accept & fulfil', path: '/orders', icon: 'M6 3h12v18l-3-2-3 2-3-2-3 2z M9 7h6 M9 11h6', tone: 'blue' },
  { label: 'Counter', hint: 'Quick POS sale', path: '/counter', icon: 'M4 3h16v18H4z M7 6h10v4H7z', tone: 'dark' },
  { label: 'Products', hint: 'Catalog & stock', path: '/products', icon: 'M4 7h16v14H4z M8 7V5a4 4 0 018 0v2', tone: 'green' },
  { label: 'Tables', hint: 'Dine-in QR orders', path: '/tables', icon: 'M4 5h16v10H4z M6 15v6 M18 15v6', tone: 'purple' },
  { label: 'Customers', hint: 'People & support', path: '/customers', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0 M4 21v-2a8 8 0 0116 0v2', tone: 'teal' },
  { label: 'Settings', hint: 'Store config', path: '/settings', icon: 'M12 3v3 M12 18v3 M3 12h3 M18 12h3 M17 12a5 5 0 11-10 0 5 5 0 0110 0', tone: 'gray' },
]

function formatPrice(paise: number): string {
  return '\u20B9' + (paise / 100).toFixed(2)
}

function formatPriceShort(paise: number): string {
  const rupees = paise / 100
  if (rupees >= 1000) return '\u20B9' + (rupees / 1000).toFixed(1) + 'k'
  return '\u20B9' + rupees.toFixed(0)
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
    if (isPlatformAdmin.value) {
      const { data } = await platformApi.getDashboard()
      if (data.success && data.data) platformStats.value = data.data
    } else {
      const { data } = await settingsApi.dashboardEnhanced()
      if (data.success && data.data) {
        stats.value = data.data as DashboardStats
      } else {
        const basicRes = await ordersApi.dashboard()
        if (basicRes.data.success && basicRes.data.data) {
          stats.value = basicRes.data.data
        }
      }
    }
  } catch (e) {
    if (!isPlatformAdmin.value) {
      try {
        const { data } = await ordersApi.dashboard()
        if (data.success && data.data) stats.value = data.data
      } catch {
        console.error('Failed to load dashboard')
      }
    }
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="dash">
    <!-- Platform Admin Dashboard -->
    <template v-if="isPlatformAdmin">
      <header class="dash-bar">
        <div>
          <h1>Platform Overview</h1>
          <span>{{ todayShort }}</span>
        </div>
        <router-link to="/tenants" class="dash-bar__action">Manage tenants</router-link>
      </header>

      <div v-if="loading" class="dash-skeleton" aria-live="polite">
        <div v-for="item in 4" :key="item" class="skel"></div>
      </div>

      <template v-else-if="platformStats">
        <div class="dash-stats">
          <div class="dash-stat dash-stat--hero"><span>Tenants</span><strong>{{ platformStats.total_tenants }}</strong><small>{{ platformStats.active_tenants }} active</small></div>
          <div class="dash-stat"><span>Orders</span><strong>{{ platformStats.total_orders }}</strong><small>All tenants</small></div>
          <div class="dash-stat"><span>Revenue</span><strong>{{ formatPrice(platformStats.total_revenue || 0) }}</strong><small>Platform-wide</small></div>
          <div class="dash-stat"><span>Customers</span><strong>{{ platformStats.total_customers }}</strong><small>{{ platformStats.total_tenant_admins }} admins</small></div>
        </div>

        <div class="dash-actions">
          <router-link to="/tenants" class="dash-tile dash-tile--blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0 M4 21v-2a8 8 0 0116 0v2"/></svg><strong>Tenants</strong><small>Create & manage</small></router-link>
          <router-link to="/marketplace-fees" class="dash-tile dash-tile--green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z M9 7h6 M9 11h6"/></svg><strong>Fee Ledger</strong><small>Marketplace fees</small></router-link>
          <router-link to="/platform-config" class="dash-tile dash-tile--gray"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3 M12 18v3 M3 12h3 M18 12h3 M17 12a5 5 0 11-10 0 5 5 0 0110 0"/></svg><strong>Settings</strong><small>Platform config</small></router-link>
        </div>

        <div class="dash-stats" v-if="platformStats.marketplace_tenants">
          <div class="dash-stat"><span>Marketplace stores</span><strong>{{ platformStats.marketplace_active_tenants || 0 }}</strong><small>{{ platformStats.marketplace_tenants || 0 }} enrolled</small></div>
          <div class="dash-stat"><span>Fees accrued</span><strong>{{ formatPrice(platformStats.platform_fee_accrued || 0) }}</strong><small>{{ formatPrice(platformStats.platform_fee_settled || 0) }} settled</small></div>
        </div>
      </template>

      <div v-else class="dash-error"><strong>Dashboard unavailable.</strong> Refresh the page and try again.</div>
    </template>

    <!-- Tenant Dashboard -->
    <template v-else>
      <header class="dash-bar">
        <div>
          <h1>{{ greeting }}, {{ authName || 'there' }}</h1>
          <span>{{ todayShort }}</span>
        </div>
        <router-link to="/orders" class="dash-bar__action">All orders</router-link>
      </header>

      <div v-if="loading" class="dash-skeleton" aria-live="polite">
        <div v-for="item in 4" :key="item" class="skel"></div>
      </div>

      <template v-else-if="stats">
        <!-- Inline stat strip -->
        <div class="dash-stats">
          <div class="dash-stat dash-stat--hero">
            <span>Today's revenue</span>
            <strong>{{ formatPrice(stats.today_revenue) }}</strong>
            <small>{{ stats.today_orders }} orders today</small>
          </div>
          <div class="dash-stat">
            <span>Avg. order</span>
            <strong>{{ stats.avg_order_value ? formatPrice(stats.avg_order_value) : '—' }}</strong>
            <small>Per order value</small>
          </div>
          <div class="dash-stat">
            <span>Active</span>
            <strong>{{ totalActiveOrders }}</strong>
            <small>In progress</small>
          </div>
          <div class="dash-stat">
            <span>Customers</span>
            <strong>{{ stats.total_customers }}</strong>
            <small>Total registered</small>
          </div>
        </div>

        <!-- Quick action grid -->
        <section class="dash-actions" aria-label="Quick actions">
          <router-link v-for="a in quickActions" :key="a.path" :to="a.path" class="dash-tile" :class="`dash-tile--${a.tone}`">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path :d="a.icon"/></svg>
            <strong>{{ a.label }}</strong>
            <small>{{ a.hint }}</small>
          </router-link>
        </section>

        <!-- Two-column panels -->
        <div class="dash-panels">
          <!-- Revenue chart OR order pulse -->
          <article class="dash-panel" v-if="stats.weekly_revenue?.length">
            <div class="dash-panel__head"><h2>Revenue · 7 days</h2></div>
            <div class="dash-chart">
              <div v-for="day in stats.weekly_revenue" :key="day.date" class="dash-chart__col">
                <span class="dash-chart__val">{{ formatPriceShort(Number(day.revenue)) }}</span>
                <div class="dash-chart__bar" :style="{ height: `${Math.max((Number(day.revenue) / maxRevenue) * 120, 4)}px` }"></div>
                <span class="dash-chart__day">{{ formatDay(day.date) }}</span>
              </div>
            </div>
          </article>

          <article v-else class="dash-panel">
            <div class="dash-panel__head"><h2>Active orders</h2><span class="dash-panel__badge">{{ totalActiveOrders }}</span></div>
            <div v-if="statusEntries.length" class="dash-pulse">
              <div class="dash-pulse__ring"><strong>{{ totalActiveOrders }}</strong><small>active</small></div>
              <div class="dash-pulse__list">
                <div v-for="entry in statusEntries" :key="entry.status" class="dash-pulse__row">
                  <span class="status-dot" :class="statusTone(entry.status)"></span>
                  <span>{{ formatStatus(entry.status) }}</span>
                  <strong>{{ entry.count }}</strong>
                  <div class="dash-pulse__bar"><i :style="{ width: `${entry.width}%` }"></i></div>
                </div>
              </div>
            </div>
            <div v-else class="dash-empty">No active orders right now.</div>
          </article>

          <!-- Top products OR shortcuts -->
          <article class="dash-panel">
            <div class="dash-panel__head"><h2>{{ stats.top_products?.length ? 'Top sellers' : 'Quick links' }}</h2></div>
            <div v-if="stats.top_products?.length" class="dash-top-list">
              <router-link v-for="(prod, i) in stats.top_products" :key="i" :to="`/products?search=${encodeURIComponent(prod.product_name)}`" class="dash-top-item">
                <span class="dash-top-rank">{{ i + 1 }}</span>
                <div><strong>{{ prod.product_name }}</strong><small>{{ prod.total_qty }} sold</small></div>
                <span class="dash-top-rev">{{ formatPrice(Number(prod.total_revenue)) }}</span>
              </router-link>
            </div>
            <div v-else class="dash-shortcuts">
              <router-link to="/orders">Review orders</router-link>
              <router-link to="/products">Manage products</router-link>
              <router-link to="/categories">Organize categories</router-link>
            </div>
          </article>
        </div>

        <!-- Active orders (when chart is shown above) -->
        <article v-if="statusEntries.length && stats.weekly_revenue?.length" class="dash-panel dash-panel--wide">
          <div class="dash-panel__head"><h2>Orders in motion</h2><span class="dash-panel__badge">{{ totalActiveOrders }} active</span></div>
          <div class="dash-pulse">
            <div class="dash-pulse__ring"><strong>{{ totalActiveOrders }}</strong><small>active</small></div>
            <div class="dash-pulse__list">
              <div v-for="entry in statusEntries" :key="entry.status" class="dash-pulse__row">
                <span class="status-dot" :class="statusTone(entry.status)"></span>
                <span>{{ formatStatus(entry.status) }}</span>
                <strong>{{ entry.count }}</strong>
                <div class="dash-pulse__bar"><i :style="{ width: `${entry.width}%` }"></i></div>
              </div>
            </div>
          </div>
        </article>

        <!-- Low stock alerts -->
        <article v-if="stats.low_stock?.length" class="dash-panel dash-panel--wide dash-panel--warn">
          <div class="dash-panel__head"><h2>Low stock</h2><span class="dash-panel__badge dash-panel__badge--warn">{{ stats.low_stock.length }} items</span></div>
          <div class="dash-top-list">
            <router-link v-for="item in stats.low_stock" :key="item.uuid" :to="`/products/${item.uuid}`" class="dash-top-item">
              <span class="dash-top-rank dash-top-rank--warn">!</span>
              <div><strong>{{ item.name }}</strong><small class="text-warn">{{ item.stock_quantity }} left</small></div>
              <span class="dash-top-rev text-warn">Restock</span>
            </router-link>
          </div>
        </article>
      </template>

      <div v-else class="dash-error"><strong>Dashboard unavailable.</strong> Refresh the page and try again.</div>
    </template>
  </div>
</template>

<style scoped>
.dash{max-width:1400px;animation:dash-in .4s ease both}

/* --- Top bar --- */
.dash-bar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px}
.dash-bar h1{margin:0;font-size:clamp(20px,2.5vw,28px);font-weight:800;letter-spacing:-.8px;color:var(--ink)}
.dash-bar span{display:block;margin-top:2px;color:#8a8e9a;font-size:12px;font-weight:600}
.dash-bar__action{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid var(--line);border-radius:10px;color:var(--ink);background:#fff;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;transition:.15s ease}
.dash-bar__action:hover{border-color:var(--primary);color:var(--primary)}

/* --- Stat strip --- */
.dash-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
.dash-stat{padding:14px 16px;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 4px 12px rgba(20,20,30,.03)}
.dash-stat span{display:block;color:#8a8e9a;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px}
.dash-stat strong{display:block;margin:6px 0 2px;color:var(--ink);font-size:22px;font-weight:800;letter-spacing:-.5px}
.dash-stat small{color:#9a9eaa;font-size:11px}
.dash-stat--hero{border-color:color-mix(in srgb,var(--primary) 15%,var(--line));background:linear-gradient(135deg,#fff,color-mix(in srgb,var(--primary) 3%,white))}
.dash-stat--hero strong{color:var(--primary)}

/* --- Action tiles --- */
.dash-actions{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-bottom:18px}
.dash-tile{display:flex;flex-direction:column;gap:6px;padding:16px;border:1px solid var(--line);border-radius:16px;background:#fff;text-decoration:none;color:var(--ink);transition:transform .18s ease,box-shadow .18s ease}
.dash-tile:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(20,20,30,.08)}
.dash-tile:active{transform:scale(.97)}
.dash-tile svg{width:28px;height:28px;padding:5px;border-radius:10px}
.dash-tile strong{font-size:14px;font-weight:700}
.dash-tile small{color:#8a8e9a;font-size:11px}
.dash-tile--blue svg{color:#2563eb;background:#dbeafe}
.dash-tile--dark svg{color:#1e1e2e;background:#e5e5ea}
.dash-tile--green svg{color:#059669;background:#d1fae5}
.dash-tile--purple svg{color:#7c3aed;background:#ede9fe}
.dash-tile--teal svg{color:#0d9488;background:#ccfbf1}
.dash-tile--gray svg{color:#6b7280;background:#f3f4f6}

/* --- Panels --- */
.dash-panels{display:grid;grid-template-columns:1.2fr .8fr;gap:12px;margin-bottom:12px}
.dash-panel{padding:18px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 4px 14px rgba(20,20,30,.03)}
.dash-panel--wide{margin-bottom:12px}
.dash-panel--warn{border-color:#fed7aa}
.dash-panel__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.dash-panel__head h2{margin:0;font-size:16px;font-weight:750;letter-spacing:-.3px}
.dash-panel__badge{padding:4px 10px;border-radius:8px;background:#f0f0f2;color:#5a5a6a;font-size:11px;font-weight:800}
.dash-panel__badge--warn{background:#fff7ed;color:#c2410c}

/* --- Chart --- */
.dash-chart{display:flex;align-items:flex-end;justify-content:space-around;gap:6px;min-height:160px;padding-top:16px}
.dash-chart__col{display:flex;flex-direction:column;align-items:center;gap:4px;flex:1}
.dash-chart__val{color:var(--ink);font-size:10px;font-weight:800}
.dash-chart__bar{width:100%;max-width:42px;background:linear-gradient(180deg,color-mix(in srgb,var(--primary) 70%,#f87171),var(--primary));border-radius:5px 5px 0 0;transition:height .4s ease}
.dash-chart__day{color:#8a8e9a;font-size:10px;font-weight:700;text-transform:uppercase}

/* --- Pulse --- */
.dash-pulse{display:flex;align-items:center;gap:24px;padding:8px 0}
.dash-pulse__ring{display:flex;width:100px;height:100px;flex:0 0 100px;flex-direction:column;align-items:center;justify-content:center;border:9px solid color-mix(in srgb,var(--primary) 15%,#f0f0f2);border-right-color:color-mix(in srgb,var(--primary) 40%,#f0f0f2);border-bottom-color:var(--primary);border-radius:50%}
.dash-pulse__ring strong{color:var(--ink);font-size:24px;letter-spacing:-.5px}
.dash-pulse__ring small{color:#8a8e9a;font-size:9px;text-transform:uppercase}
.dash-pulse__list{flex:1;display:grid;gap:10px}
.dash-pulse__row{display:grid;grid-template-columns:8px 1fr auto;align-items:center;gap:8px;font-size:12px;color:#5a5a6a;text-transform:capitalize}
.dash-pulse__row strong{color:var(--ink)}
.dash-pulse__bar{grid-column:1/-1;height:4px;border-radius:4px;background:#f0f0f2;overflow:hidden}
.dash-pulse__bar i{display:block;height:100%;border-radius:inherit;background:var(--primary)}
.status-dot{width:8px;height:8px;border-radius:50%;background:#bbbbc6}
.status-dot--amber{background:#e9ae57}.status-dot--blue{background:#6eabb4}.status-dot--lime{background:var(--primary)}.status-dot--coral{background:#f0785f}

/* --- Top products --- */
.dash-top-list{display:grid;gap:4px}
.dash-top-item{display:flex;align-items:center;gap:10px;padding:8px 6px;border-radius:8px;text-decoration:none;color:var(--ink);transition:.15s ease}
.dash-top-item:hover{background:var(--paper)}
.dash-top-rank{display:grid;width:24px;height:24px;place-items:center;border-radius:7px;background:color-mix(in srgb,var(--primary) 10%,white);color:var(--primary);font-size:11px;font-weight:900;flex-shrink:0}
.dash-top-rank--warn{background:#fff7ed;color:#c2410c}
.dash-top-item div{flex:1;min-width:0}
.dash-top-item strong{display:block;font-size:12px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dash-top-item small{display:block;margin-top:1px;color:#8a8e9a;font-size:10px}
.dash-top-rev{font-size:12px;font-weight:800;white-space:nowrap}
.text-warn{color:#c2410c!important}

/* --- Shortcuts --- */
.dash-shortcuts{display:grid;gap:6px}
.dash-shortcuts a{display:block;padding:10px 8px;border-radius:8px;color:var(--primary);font-size:13px;font-weight:700;text-decoration:none;transition:.12s ease}
.dash-shortcuts a:hover{background:color-mix(in srgb,var(--primary) 5%,white)}

/* --- Empty / Error / Loading --- */
.dash-empty{padding:28px;color:#8a8e9a;font-size:13px;text-align:center}
.dash-error{padding:20px;border:1px solid #fecaca;border-radius:12px;color:#991b1b;background:#fef2f2;font-size:13px}
.dash-skeleton{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
.skel{height:90px;border-radius:14px;background:linear-gradient(100deg,#f0f0f2 30%,#fafafa 50%,#f0f0f2 70%);background-size:200% 100%;animation:shimmer 1.3s infinite}

@keyframes dash-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
@keyframes shimmer{to{background-position:-200% 0}}

/* --- Responsive --- */
@media(max-width:900px){
  .dash-stats{grid-template-columns:repeat(2,1fr)}
  .dash-panels{grid-template-columns:1fr}
  .dash-actions{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:640px){
  .dash-bar{flex-direction:column;align-items:flex-start;gap:10px}
  .dash-bar__action{width:100%;justify-content:center}
  .dash-stats{grid-template-columns:1fr 1fr}
  .dash-stat strong{font-size:18px}
  .dash-actions{grid-template-columns:repeat(2,1fr)}
  .dash-skeleton{grid-template-columns:1fr 1fr}
  .dash-pulse{flex-direction:column;gap:16px}
  .dash-pulse__ring{width:90px;height:90px;flex-basis:90px}
  .dash-pulse__list{width:100%}
  .dash-panel{padding:14px}
}
</style>
