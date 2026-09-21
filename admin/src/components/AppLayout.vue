<script setup lang="ts">
import { onMounted, onUnmounted, nextTick, ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter, useRoute } from 'vue-router'
import { tenantSections, platformSections, matchesPage } from '@/navigation'
import NotificationBell from '@/components/NotificationBell.vue'
import AppTileIcon from '@/components/AppTileIcon.vue'
import { settingsApi } from '@/api/settings'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const capabilities = ref<Record<string, boolean>>({})
const search = ref('')
const finder = ref<HTMLDialogElement>()
const settingsError = ref(false)
const storeStatus = ref('')
const sidebar = ref<HTMLElement>()
const launcher = ref<string | null>(null)
const launcherPanel = ref<HTMLElement>()
let leaveTimer: ReturnType<typeof setTimeout> | undefined
const launcherSections = computed(() => sections.value.filter(s => launcher.value === 'All apps' || s.label === launcher.value))
function keepLauncher() { clearTimeout(leaveTimer) }
function openLauncher(label: string) { keepLauncher(); launcher.value = label }
function hoverLauncher(event: PointerEvent, label: string) { if (event.pointerType === 'mouse') openLauncher(label) }
function leaveLauncher() { keepLauncher(); leaveTimer = setTimeout(() => { if (!launcherPanel.value?.contains(document.activeElement)) launcher.value = null }, 220) }
function outsideLauncher(e: PointerEvent) { if (!sidebar.value?.contains(e.target as Node) && !launcherPanel.value?.contains(e.target as Node)) launcher.value = null }
function closeLauncher() { const label = launcher.value; launcher.value = null; sidebar.value?.querySelector<HTMLElement>(`button[data-section="${label}"]`)?.focus() }
let previousFocus: HTMLElement | null = null
function keyboard(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault(); mobileNavOpen.value = false; search.value = ''; finder.value?.showModal()
  }
  if (e.key === 'Escape') { mobileNavOpen.value = false; closeLauncher() }
  if (e.key === 'Tab' && mobileNavOpen.value && !finder.value?.open) {
    const elements = Array.from(sidebar.value?.querySelectorAll<HTMLElement>('a,button,input') || []).filter(el => el.getClientRects().length)
    const first = elements[0], last = elements[elements.length - 1]
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last?.focus() }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first?.focus() }
  }
}
const sections = computed(() => (isPlatformAdmin.value ? platformSections : tenantSections).map(s => ({ ...s, pages: s.pages.filter(p => !p.capability || capabilities.value[p.capability] === true) })).filter(s => s.pages.length))
const currentSection = computed(() => sections.value.find(s => s.pages.some(p => matchesPage(route.path, p.path))))
const searchResults = computed(() => sections.value.flatMap(s => s.pages.map(p => ({ ...p, section: s.label }))).filter(p => `${p.label} ${p.section}`.toLowerCase().includes(search.value.toLowerCase())))
watch(() => route.fullPath, () => { mobileNavOpen.value = false; launcher.value = null; finder.value?.close() })
const mobileNavOpen = ref(false)
watch(mobileNavOpen, async open => {
  if (open) { previousFocus = document.activeElement as HTMLElement; await nextTick(); sidebar.value?.querySelector<HTMLElement>('.sidebar-close')?.focus() }
  else previousFocus?.focus()
})
const storeName = ref('Your store')

const isPlatformAdmin = computed(() => auth.user?.role === 'platform_admin')

function handleLogout() {
  auth.logout()
  router.push('/login')
}

function closeMobileNav() {
  mobileNavOpen.value = false
}

async function loadSettings() {
  if (isPlatformAdmin.value) {
    storeName.value = 'CloudMarket'
    document.title = 'CloudMarket Platform'
    return
  }
  try {
    const { data } = await settingsApi.getSettings()
    storeName.value = data.data?.store?.name || 'Your store'
    capabilities.value = data.data?.capabilities || {}
    storeStatus.value = data.data?.store?.status || ''
    settingsError.value = false
    const branding = data.data?.branding
    if (branding?.primary_color) {
      document.documentElement.style.setProperty('--primary', branding.primary_color)
      document.documentElement.style.setProperty('--primary-deep', branding.primary_color)
    }
    document.title = `${storeName.value} · CloudMarket`
  } catch {
    settingsError.value = true
  }
}
onMounted(() => { document.addEventListener('keydown', keyboard); document.addEventListener('pointerdown', outsideLauncher); loadSettings() })
onUnmounted(() => { document.removeEventListener('keydown', keyboard); document.removeEventListener('pointerdown', outsideLauncher); keepLauncher() })


const navItems = computed(() => sections.value.map(s => ({ label: s.label, path: s.pages[0].path, icon: s.icon })))
</script>

<template>
  <div class="admin-shell min-h-screen">
    <div
      v-if="mobileNavOpen"
      class="mobile-nav-scrim lg:hidden"
      @click="closeMobileNav"
    ></div>

    <aside ref="sidebar" class="admin-sidebar" :class="{ 'admin-sidebar--open': mobileNavOpen }" aria-label="Workspace navigation">
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

      <nav class="sidebar-nav">
        <button
          v-for="item in navItems"
          :key="item.path"
          :data-section="item.label"
          :aria-label="item.label"
          :aria-expanded="launcher === item.label"
          aria-controls="app-launcher"
          class="sidebar-link"
          :class="currentSection?.label === item.label ? 'sidebar-link--active' : ''"
          @pointerenter="hoverLauncher($event, item.label)"
          @pointerleave="leaveLauncher"
          @click="openLauncher(item.label)"
          @keydown.arrow-right.prevent="openLauncher(item.label); nextTick(() => launcherPanel?.querySelector('a')?.focus())"
        >
          <AppTileIcon :name="item.label"/>
          <span class="sidebar-link__label">{{ item.label }}</span>
        </button>
      </nav>

      <button class="sidebar-link all-apps-trigger" data-section="All apps" aria-label="All apps" :aria-expanded="launcher === 'All apps'" aria-controls="app-launcher" @click="openLauncher('All apps')" @pointerenter="hoverLauncher($event, 'All apps')" @pointerleave="leaveLauncher"><AppTileIcon name="All apps"/><span class="sidebar-link__label">All apps</span></button>

      <div v-if="mobileNavOpen" class="mobile-tool-grid"><section v-for="s in sections" :key="s.label"><h3>{{ s.label }}</h3><router-link v-for="p in s.pages" :key="p.path" :to="p.path" @click="closeMobileNav"><AppTileIcon :name="p.label" class="mobile-tile-icon"/><span>{{ p.label }}</span></router-link></section></div>

      <button class="sidebar-finder" @click="search = ''; finder?.showModal()" aria-label="Search pages">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
      </button>

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

    <section v-if="launcher" id="app-launcher" ref="launcherPanel" class="app-launcher" aria-label="Application launcher" @pointerenter="keepLauncher" @pointerleave="leaveLauncher">
      <header><div><small>WORKSPACE</small><h2>{{ launcher }}</h2></div><button aria-label="Close apps" @click="closeLauncher">×</button></header>
      <section v-for="s in launcherSections" :key="s.label"><h3 v-if="launcher === 'All apps'">{{ s.label }}</h3><nav class="app-tiles" :aria-label="`${s.label} tools`"><router-link v-for="(p, i) in s.pages" :key="p.path" :to="p.path" :class="{ 'app-tile-active': matchesPage(route.path, p.path) }" :aria-current="matchesPage(route.path,p.path) ? 'page' : undefined" @click="launcher = null"><span class="app-tile-icon" :class="`tint-${i % 4}`"><AppTileIcon :name="p.label"/></span><span>{{ p.label }}</span></router-link></nav></section>
      <button class="launcher-all" v-if="launcher !== 'All apps'" @click="openLauncher('All apps')">Browse all apps</button>
    </section>
    <main class="admin-main">
      <header class="admin-header">
        <div class="header-heading">
          <button class="mobile-menu-button lg:hidden" aria-label="Open navigation" @click="mobileNavOpen = true">
            <span aria-hidden="true">&#9776;</span>
          </button>
          <h2>{{ storeName }}</h2>
        </div>
        <div class="header-actions">
          <button class="header-search-btn" @click="search = ''; finder?.showModal()" aria-label="Search pages">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <kbd>Ctrl K</kbd>
          </button>
          <span v-if="!isPlatformAdmin && storeStatus" class="live-pill"><i v-if="storeStatus === 'active'"></i>{{ storeStatus === 'active' ? 'Live' : 'Offline' }}</span>
          <NotificationBell />
          <button class="header-logout" @click="handleLogout" title="Sign out">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </button>
        </div>
      </header>

      <nav v-if="currentSection" class="section-tabs" :aria-label="`${currentSection.label} pages`"><router-link v-for="page in currentSection.pages" :key="page.path" :to="page.path" :class="{ selected: matchesPage(route.path, page.path) }" :aria-current="matchesPage(route.path, page.path) ? 'page' : undefined">{{ page.label }}</router-link></nav>
      <div class="admin-content">
        <p v-if="settingsError" class="navigation-error" role="alert">Some menu options could not be loaded. <button @click="loadSettings">Retry</button></p>
        <router-view />
      </div>

      <nav class="mobile-dock" aria-label="Quick navigation">
        <router-link to="/" class="mobile-dock__item" exact-active-class="mobile-dock__item--active"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg><small>Home</small></router-link>
        <router-link :to="isPlatformAdmin ? '/tenants' : '/orders'" class="mobile-dock__item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6"/></svg><small>{{ isPlatformAdmin ? 'Tenants' : 'Orders' }}</small></router-link>
        <router-link :to="isPlatformAdmin ? '/platform-config' : '/counter'" class="mobile-dock__item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M7 6h10v4H7z"/><circle cx="8" cy="14" r=".5" fill="currentColor"/><circle cx="12" cy="14" r=".5" fill="currentColor"/><circle cx="16" cy="14" r=".5" fill="currentColor"/></svg><small>{{ isPlatformAdmin ? 'Settings' : 'Counter' }}</small></router-link>
        <button class="mobile-dock__item" aria-label="More" @click="mobileNavOpen = true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="5" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="19" r="1.5" fill="currentColor"/></svg><small>More</small></button>
      </nav>
    </main>
    <dialog ref="finder" class="page-finder" @click="($event.target === finder) && finder?.close()"><header><h2>Find a page</h2><button aria-label="Close search" @click="finder?.close()">×</button></header><input v-model="search" autofocus placeholder="Search pages…" aria-label="Search pages"><nav><router-link v-for="p in searchResults" :key="p.path" :to="p.path"><AppTileIcon :name="p.label" class="finder-icon"/><span>{{ p.label }}</span><small>{{ p.section }}</small></router-link><p v-if="!searchResults.length">No matching pages.</p></nav></dialog>
  </div>
</template>

<style scoped>
/* === Icon Rail Sidebar === */
.admin-sidebar{width:64px;align-items:center;padding:12px 8px}
.admin-main{margin-left:64px}
.sidebar-brand{width:100%;justify-content:center;padding:4px 0 16px}
.sidebar-brand>div,.sidebar-brand .sidebar-close{display:none}
.brand-logo{width:38px;height:38px;border-radius:10px}
.sidebar-nav{width:100%;gap:4px;overflow:visible}
.sidebar-link{width:100%;justify-content:center;min-height:44px;padding:0;border-radius:12px;position:relative}
.sidebar-link svg{width:22px;height:22px;flex-shrink:0}
.sidebar-link__label{position:fixed;left:68px;display:none;min-width:max-content;padding:7px 11px;border-radius:8px;color:#fff;background:var(--ink);box-shadow:0 8px 20px rgba(0,0,0,.2);font-size:12px;font-weight:700;z-index:9999;pointer-events:none}
.sidebar-link:hover .sidebar-link__label{display:block}
.all-apps-trigger{margin-top:8px;border-top:1px solid var(--line);padding-top:8px;border-radius:12px}
.sidebar-finder{width:40px;height:40px;margin-top:auto;display:grid;place-items:center;border:1px solid var(--line);border-radius:12px;color:#8a8e9a;background:transparent;cursor:pointer;transition:.15s ease}
.sidebar-finder:hover{color:var(--primary);border-color:color-mix(in srgb,var(--primary) 30%,var(--line))}
.sidebar-account{margin-top:10px;width:100%;justify-content:center;padding:10px 0 0}
.account-details{display:none}
.account-logout{position:absolute;bottom:14px;left:70px;display:none;min-width:max-content;color:var(--ink);border-color:var(--line);background:#fff;box-shadow:0 8px 20px rgba(0,0,0,.12)}
.sidebar-account:hover .account-logout{display:inline-flex}
.account-avatar{width:34px;height:34px;flex-basis:34px;font-size:13px}

/* === Header === */
.admin-header{height:52px;padding:0 24px}
.header-heading{display:flex;align-items:center;gap:10px}
.header-actions{display:flex;align-items:center;gap:8px}
.admin-header h2{margin:0;font-size:14px;font-weight:700;color:var(--ink)}
.header-search-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--line);border-radius:10px;color:#8a8e9a;background:#fff;cursor:pointer;font:inherit;font-size:12px;transition:.15s ease}
.header-search-btn:hover{border-color:color-mix(in srgb,var(--primary) 30%,var(--line));color:var(--primary)}
.header-search-btn kbd{display:none;padding:2px 5px;border:1px solid #e2e5ea;border-radius:4px;font-size:10px;font-family:inherit;color:#9a9eaa}
.live-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:8px;background:color-mix(in srgb,var(--primary) 6%,white);color:var(--primary);font-size:11px;font-weight:800}
.live-pill i{display:block;width:6px;height:6px;border-radius:50%;background:var(--primary);box-shadow:0 0 0 3px rgba(220,38,38,.12)}

/* === Section Tabs === */
.section-tabs{position:sticky;top:52px;z-index:19;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border-bottom:1px solid var(--line);display:flex;gap:4px;overflow-x:auto;padding:6px 24px}
.section-tabs a{padding:8px 14px;white-space:nowrap;border-radius:8px;font-size:13px;font-weight:600;color:#6a6a7a;transition:.15s ease}
.section-tabs a:hover{color:var(--ink);background:var(--paper)}
.section-tabs .selected{background:color-mix(in srgb,var(--primary) 10%,white);color:var(--primary);font-weight:700}

/* === Page Finder === */
.page-finder{width:min(540px,calc(100% - 24px));max-height:80dvh;padding:20px;border-radius:18px;margin:auto;border:1px solid #eee;box-shadow:0 24px 60px rgba(20,20,30,.2)}
.page-finder::backdrop{background:#17203360}
.page-finder header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.page-finder h2{font-weight:700;font-size:20px}
.page-finder header button{width:40px;height:40px;font-size:22px;border:0;border-radius:10px;background:#f4f6f9;cursor:pointer}
.page-finder input{width:100%;border:1px solid #ddd;border-radius:12px;padding:12px 14px;font-size:14px}
.page-finder nav{margin-top:8px;max-height:50vh;overflow-y:auto}
.page-finder nav a{display:flex;align-items:center;gap:10px;padding:10px 8px;border-bottom:1px solid #f3f3f6;text-decoration:none;color:var(--ink);font-size:14px;font-weight:600;transition:.15s ease}
.page-finder nav a:hover{background:#f8f9fc;border-radius:10px}
.page-finder nav a span{flex:1}
.page-finder small{color:#8a8e9a;font-size:12px}
.finder-icon{width:20px;height:20px;color:var(--primary);flex-shrink:0}

/* === App Launcher Panel === */
.app-launcher{position:fixed;left:72px;top:8px;bottom:8px;width:340px;z-index:45;background:white;border:1px solid #e7e9f0;border-radius:20px;box-shadow:18px 12px 60px #17203320;padding:20px;overflow:auto}
.app-launcher header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.app-launcher header small{font-size:10px;letter-spacing:2px;color:#8590a3}
.app-launcher h2{font-size:22px;font-weight:750}
.app-launcher h3{font-size:11px;color:#8590a3;text-transform:uppercase;letter-spacing:1px;margin:18px 0 10px}
.app-launcher header button{width:40px;height:40px;border:0;border-radius:10px;background:#f4f6f9;font-size:22px;cursor:pointer}
.app-tiles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.app-tiles a{display:flex;flex-direction:column;align-items:center;gap:8px;text-align:center;padding:14px 4px;border-radius:14px;font-size:11px;font-weight:650;line-height:1.35;color:#4b5568;text-decoration:none;transition:.15s ease}
.app-tiles a:hover,.app-tiles .app-tile-active{background:#f6f7fa;color:var(--primary)}
.app-tile-icon{width:48px;height:48px;border-radius:15px;background:#eaf1ff;color:#4b6abd;display:grid;place-items:center}
.app-tile-icon svg{width:24px;height:24px}
.tint-0{background:#eaf1ff;color:#4b6abd}
.tint-1{background:#e8f5ef;color:#328569}
.tint-2{background:#fff1e5;color:#a97135}
.tint-3{background:#f1ecfc;color:#8360bb}
.app-tile-active .app-tile-icon{background:color-mix(in srgb,var(--primary) 10%,white);color:var(--primary)}
.launcher-all{margin-top:20px;width:100%;padding:12px;border:1px solid #e7e9f0;border-radius:10px;font-size:12px;font-weight:700;color:var(--primary);background:transparent;cursor:pointer;transition:.15s ease}
.launcher-all:hover{background:color-mix(in srgb,var(--primary) 5%,white)}

/* === Mobile Tools Grid === */
.mobile-tool-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:8px 0}
.mobile-tool-grid h3{font-size:11px;font-weight:700;color:#8a8e9a;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.mobile-tool-grid a{display:flex;align-items:center;gap:8px;padding:10px 8px;font-size:13px;font-weight:600;color:var(--ink);text-decoration:none;border-radius:10px;transition:.12s ease}
.mobile-tool-grid a:hover{background:var(--paper)}
.mobile-tile-icon{width:18px;height:18px;color:var(--primary);flex-shrink:0}

/* === Mobile Dock === */
.mobile-dock{display:none}

/* === Focus === */
a:focus-visible,button:focus-visible{outline:2px solid var(--primary);outline-offset:3px}
.navigation-error{margin:0 0 12px;padding:12px 16px;border:1px solid #fecaca;border-radius:10px;color:#991b1b;background:#fef2f2;font-size:13px;font-weight:600}
.navigation-error button{border:0;background:transparent;color:var(--primary);font:inherit;font-weight:800;cursor:pointer;text-decoration:underline}

/* === Desktop (1024+) === */
@media(min-width:1024px){
  .admin-header{height:52px;padding:0 28px}
  .admin-content{padding:20px 28px 40px}
  .header-search-btn kbd{display:inline}
  .section-tabs{padding:6px 28px}
}

/* === Tablet (768–1023) === */
@media(min-width:768px){
  .admin-sidebar{transform:none;box-shadow:none}
  .sidebar-close,.mobile-menu-button,.mobile-nav-scrim,.mobile-tool-grid{display:none!important}
}

/* === Mobile (<768) === */
@media(max-width:767px){
  .admin-main{margin-left:0}
  .admin-sidebar{width:min(400px,92%);overflow-y:auto;z-index:40;align-items:stretch;padding:16px 14px}
  .admin-sidebar .sidebar-nav{display:none}
  .all-apps-trigger{display:none}
  .sidebar-finder{display:none}
  .sidebar-brand{display:flex;justify-content:space-between;align-items:center;padding:0 0 14px}
  .sidebar-brand>div{display:block}
  .sidebar-brand .sidebar-close{display:block!important;width:40px;height:40px;font-size:24px}
  .admin-header{height:52px;padding:0 14px}
  .section-tabs{top:52px;padding:6px 14px}
  .admin-content{padding:16px 14px calc(90px + env(safe-area-inset-bottom))}
  .mobile-dock{display:flex;bottom:0;left:0;right:0;border-radius:0;padding-bottom:calc(6px + env(safe-area-inset-bottom))}
  .mobile-dock__item{width:24%}
  .mobile-dock__item.router-link-active{background:transparent;color:#626270}
  .mobile-dock__item.router-link-exact-active{background:var(--primary);color:white}
  .sidebar-account{margin-top:20px;justify-content:flex-start}
  .account-details{display:block}
  .account-logout{position:static;display:inline-flex}
  .mobile-nav-scrim{z-index:35}
  .app-launcher{display:none}
  .header-search-btn svg{display:none}
  .header-search-btn{padding:6px 8px;font-size:18px}
  .header-search-btn::before{content:'⌕'}
}

/* === Print === */
@media print{.admin-sidebar,.admin-header,.section-tabs,.mobile-dock,.app-launcher{display:none!important}.admin-main{margin-left:0}.admin-content{padding:0}}

/* === Animations === */
@media(prefers-reduced-motion:no-preference){
  .app-launcher{animation:launcher-in .16s ease-out}
  @keyframes launcher-in{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}
}
</style>
