<script setup lang="ts">
import { onMounted, ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter, useRoute } from 'vue-router'
import { tenantSections, platformSections, matchesPage } from '@/navigation'
import NotificationBell from '@/components/NotificationBell.vue'
import { settingsApi } from '@/api/settings'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const capabilities = ref<Record<string, boolean>>({})
const search = ref('')
const finder = ref<HTMLDialogElement>()
const sections = computed(() => (isPlatformAdmin.value ? platformSections : tenantSections).map(s => ({ ...s, pages: s.pages.filter(p => !p.capability || capabilities.value[p.capability] === true) })).filter(s => s.pages.length))
const currentSection = computed(() => sections.value.find(s => s.pages.some(p => matchesPage(route.path, p.path))))
const searchResults = computed(() => sections.value.flatMap(s => s.pages.map(p => ({ ...p, section: s.label }))).filter(p => `${p.label} ${p.section}`.toLowerCase().includes(search.value.toLowerCase())))
watch(() => route.fullPath, () => { mobileNavOpen.value = false; finder.value?.close() })
const mobileNavOpen = ref(false)
const storeName = ref('Your store')

const isPlatformAdmin = computed(() => auth.user?.role === 'platform_admin')

function handleLogout() {
  auth.logout()
  router.push('/login')
}

function closeMobileNav() {
  mobileNavOpen.value = false
}

onMounted(async () => {
  if (isPlatformAdmin.value) {
    storeName.value = 'CloudMarket'
    document.title = 'CloudMarket Platform'
    return
  }
  try {
    const { data } = await settingsApi.getSettings()
    storeName.value = data.data?.store?.name || 'Your store'
    capabilities.value = data.data?.capabilities || {}
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


const navItems = computed(() => sections.value.map(s => ({ label: s.label, path: s.pages[0].path, icon: s.icon })))
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

      <div class="sidebar-section-label">{{ isPlatformAdmin ? 'Platform' : 'Manage' }}</div>
      <nav class="sidebar-nav">
        <router-link
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          class="sidebar-link"
          :class="currentSection?.label === item.label ? 'sidebar-link--active' : ''"
          @click="closeMobileNav"
        >
          <span class="nav-icon" :class="`nav-icon--${item.icon}`" aria-hidden="true"></span>
          <span class="sidebar-link__label">{{ item.label }}</span>
        </router-link>
      </nav>

      <div v-if="mobileNavOpen" class="mobile-tool-grid"><section v-for="s in sections" :key="s.label"><h3>{{ s.label }}</h3><router-link v-for="p in s.pages" :key="p.path" :to="p.path" @click="closeMobileNav">{{ p.label }}</router-link></section></div>

      <div class="sidebar-note">
        <span class="sidebar-note__spark">&#9670;</span>
        <div>
          <p>Sell. Manage. Deliver. Grow.</p>
          <span>Live operations workspace</span>
        </div>
      </div>

      <button class="page-finder-button" @click="search = ''; finder?.showModal()">Find a page</button>

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
          <button class="page-finder-button" @click="search = ''; finder?.showModal()" aria-label="Find a page">⌕</button>
          <span class="live-pill"><i></i> Live store</span>
          <NotificationBell />
          <button class="header-logout" @click="handleLogout" title="Sign out">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </button>
        </div>
      </header>

      <nav v-if="currentSection" class="section-tabs" :aria-label="`${currentSection.label} pages`"><router-link v-for="page in currentSection.pages" :key="page.path" :to="page.path" :class="{ selected: matchesPage(route.path, page.path) }" :aria-current="matchesPage(route.path, page.path) ? 'page' : undefined">{{ page.label }}</router-link></nav>
      <div class="admin-content">
        <router-view />
      </div>

      <nav class="mobile-dock" aria-label="Quick navigation">
        <router-link to="/" class="mobile-dock__item"><span>⌂</span><small>Home</small></router-link>
        <router-link :to="isPlatformAdmin ? '/tenants' : '/orders'" class="mobile-dock__item"><span>☷</span><small>{{ isPlatformAdmin ? 'Tenants' : 'Orders' }}</small></router-link>
        <router-link :to="isPlatformAdmin ? '/platform-config' : '/counter'" class="mobile-dock__item"><span>＋</span><small>{{ isPlatformAdmin ? 'Settings' : 'Counter' }}</small></router-link>
        <button class="mobile-dock__item" aria-label="More" @click="mobileNavOpen = true"><span aria-hidden="true">•••</span><small>More</small></button>
      </nav>
    </main>
    <dialog ref="finder" class="page-finder" @click="($event.target === finder) && finder?.close()"><header><h2>Find a page</h2><button aria-label="Close search" @click="finder?.close()">×</button></header><input v-model="search" autofocus placeholder="Search pages…" aria-label="Search pages"><nav><router-link v-for="p in searchResults" :key="p.path" :to="p.path">{{ p.label }}<small>{{ p.section }}</small></router-link><p v-if="!searchResults.length">No matching pages.</p></nav></dialog>
  </div>
</template>

<style scoped>
.admin-sidebar{width:168px;align-items:stretch;padding:16px 12px}.admin-main{margin-left:168px}.sidebar-brand{padding:0 0 20px}.sidebar-brand>div,.sidebar-section-label,.sidebar-note{display:none}.sidebar-nav{gap:6px;overflow:visible}.sidebar-link{justify-content:flex-start;min-height:50px;padding:12px;gap:12px;border-radius:12px}.sidebar-link__label{position:static;display:block!important;padding:0;background:none;color:inherit;box-shadow:none;font-size:13px;min-width:0;pointer-events:auto}.sidebar-account{margin-top:auto}.page-finder-button{min-height:44px;padding:8px;font-size:13px}.section-tabs{position:sticky;top:64px;z-index:19;background:white;border-bottom:1px solid var(--line);display:flex;gap:8px;overflow-x:auto;padding:10px 24px}.section-tabs a{padding:12px 16px;white-space:nowrap;border-radius:10px;font-size:14px;font-weight:600}.section-tabs .selected{background:color-mix(in srgb,var(--primary) 10%,white);color:var(--primary)}.page-finder{width:min(580px,calc(100% - 24px));max-height:85dvh;padding:24px;border-radius:20px;margin:auto;border:1px solid #eee}.page-finder::backdrop{background:#17203380}.page-finder header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}.page-finder h2{font-weight:700;font-size:22px}.page-finder button{min-width:44px;min-height:44px;font-size:24px}.page-finder input{width:100%;border:1px solid #ddd;border-radius:12px;padding:14px}.page-finder nav a{display:flex;justify-content:space-between;padding:14px 8px;border-bottom:1px solid #eee}.page-finder small{color:#64748b}.mobile-tool-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.mobile-tool-grid h3{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px}.mobile-tool-grid a{display:block;padding:12px 6px;font-size:14px}.mobile-dock{display:none}a:focus-visible,button:focus-visible{outline:2px solid var(--primary);outline-offset:3px}
@media(min-width:768px){.admin-sidebar{transform:none;box-shadow:none}.sidebar-close,.mobile-menu-button,.mobile-nav-scrim,.mobile-tool-grid{display:none!important}.admin-header{height:64px}}
@media(max-width:767px){.admin-main{margin-left:0}.admin-sidebar{width:min(430px,100%);overflow-y:auto;z-index:40;align-items:stretch}.admin-sidebar .sidebar-nav{display:none}.sidebar-brand{justify-content:space-between}.sidebar-close{display:block!important}.admin-header{height:56px;padding:0 14px}.section-tabs{top:56px;padding:8px 12px}.admin-content{padding:18px 14px calc(104px + env(safe-area-inset-bottom))}.mobile-dock{display:flex;bottom:0;left:0;right:0;border-radius:0;padding-bottom:calc(8px + env(safe-area-inset-bottom))}.mobile-dock__item{width:23%}.mobile-dock__item.router-link-active{background:transparent;color:#626270}.mobile-dock__item.router-link-exact-active{background:var(--primary);color:white}.sidebar-account{margin-top:24px}.mobile-nav-scrim{z-index:35}}
@media print{.admin-sidebar,.admin-header,.section-tabs,.mobile-dock{display:none!important}.admin-main{margin-left:0}.admin-content{padding:0}}
</style>
