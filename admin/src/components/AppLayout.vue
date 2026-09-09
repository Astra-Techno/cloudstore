<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import NotificationBell from '@/components/NotificationBell.vue'
import { settingsApi } from '@/api/settings'

const auth = useAuthStore()
const router = useRouter()
const mobileNavOpen = ref(false)
const storeName = ref('Your store')

function handleLogout() {
  auth.logout()
  router.push('/login')
}

function closeMobileNav() {
  mobileNavOpen.value = false
}

onMounted(async () => {
  try {
    const { data } = await settingsApi.getSettings()
    storeName.value = data.data?.store?.name || 'Your store'
    const branding = data.data?.branding
    if (branding?.primary_color) {
      document.documentElement.style.setProperty('--primary', branding.primary_color)
      document.documentElement.style.setProperty('--primary-deep', branding.primary_color)
    }
    document.title = `${storeName.value} · CloudMarket`
  } catch {
    // Branding is non-critical; retain the CloudMarket fallback.
  }
})

const navItems = [
  { label: 'Overview', path: '/', icon: 'overview' },
  { label: 'Orders', path: '/orders', icon: 'orders' },
  { label: 'Categories', path: '/categories', icon: 'categories' },
  { label: 'Products', path: '/products', icon: 'products' },
  { label: 'Coupons', path: '/coupons', icon: 'coupons' },
  { label: 'Promotions', path: '/promotions', icon: 'promotions' },
  { label: 'Bundles', path: '/bundles', icon: 'bundles' },
  { label: 'Customers', path: '/customers', icon: 'customers' },
  { label: 'Drivers', path: '/drivers', icon: 'drivers' },
  { label: 'Delivery Zones', path: '/delivery-zones', icon: 'zones' },
  { label: 'Settings', path: '/settings', icon: 'settings' },
]
</script>

<template>
  <div class="admin-shell min-h-screen">
    <div
      v-if="mobileNavOpen"
      class="mobile-nav-scrim lg:hidden"
      @click="closeMobileNav"
    ></div>

    <aside class="admin-sidebar" :class="{ 'admin-sidebar--open': mobileNavOpen }">
      <div class="sidebar-brand">
        <img src="/logo.png" alt="CloudMarket" class="brand-logo" />
        <div>
          <p class="brand-name">{{ storeName }}</p>
          <p class="brand-caption">Powered by CloudMarket</p>
        </div>
        <button class="sidebar-close lg:hidden" aria-label="Close navigation" @click="closeMobileNav">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="sidebar-section-label">Manage</div>
      <nav class="sidebar-nav">
        <router-link
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          class="sidebar-link"
          :class="(item.path === '/' ? $route.path === '/' : $route.path.startsWith(item.path)) ? 'sidebar-link--active' : ''"
          @click="closeMobileNav"
        >
          <span class="nav-icon" :class="`nav-icon--${item.icon}`" aria-hidden="true"></span>
          <span class="sidebar-link__label">{{ item.label }}</span>
        </router-link>
      </nav>

      <div class="sidebar-note">
        <span class="sidebar-note__spark">&#9670;</span>
        <div>
          <p>Sell. Manage. Deliver. Grow.</p>
          <span>Live operations workspace</span>
        </div>
      </div>

      <div class="sidebar-account">
        <div class="account-avatar">{{ auth.user?.name?.charAt(0) || 'A' }}</div>
        <div class="account-details">
          <p>{{ auth.user?.name || 'Store admin' }}</p>
          <span>{{ auth.user?.email }}</span>
        </div>
        <button class="account-logout" aria-label="Sign out" title="Sign out" @click="handleLogout">
          <span aria-hidden="true">↪</span><span>Sign out</span>
        </button>
      </div>
    </aside>

    <main class="admin-main">
      <header class="admin-header">
        <div class="header-heading">
          <button class="mobile-menu-button lg:hidden" aria-label="Open navigation" @click="mobileNavOpen = true">
            <span aria-hidden="true">&#9776;</span>
          </button>
          <div>
            <h2>{{ storeName }}</h2>
          </div>
        </div>
        <div class="header-actions">
          <span class="live-pill"><i></i> Live store</span>
          <NotificationBell />
          <button class="header-logout" @click="handleLogout" title="Sign out">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </button>
        </div>
      </header>

      <div class="admin-content">
        <router-view />
      </div>

      <nav class="mobile-dock lg:hidden" aria-label="Quick navigation">
        <router-link to="/" class="mobile-dock__item"><span>⌂</span><small>Home</small></router-link>
        <router-link to="/orders" class="mobile-dock__item"><span>▤</span><small>Orders</small></router-link>
        <router-link to="/products" class="mobile-dock__item"><span>▣</span><small>Catalog</small></router-link>
        <button class="mobile-dock__item" aria-label="Open all tools" @click="mobileNavOpen = true"><span>⋮</span><small>More</small></button>
      </nav>
    </main>
  </div>
</template>
