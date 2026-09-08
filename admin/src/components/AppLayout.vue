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
  { label: 'Coupons', path: '/coupons', icon: 'categories' },
  { label: 'Promotions', path: '/promotions', icon: 'categories' },
  { label: 'Bundles', path: '/bundles', icon: 'categories' },
  { label: 'Customers', path: '/customers', icon: 'customers' },
  { label: 'Drivers', path: '/drivers', icon: 'delivery' },
  { label: 'Delivery Zones', path: '/delivery-zones', icon: 'delivery' },
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
          {{ item.label }}
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
            <p class="header-eyebrow">Control center</p>
            <h2><slot name="header">Admin Panel</slot></h2>
          </div>
        </div>
        <div class="header-actions">
          <span class="live-pill"><i></i> Live store</span>
          <NotificationBell />
        </div>
      </header>

      <div class="admin-content">
        <router-view />
      </div>
    </main>
  </div>
</template>
