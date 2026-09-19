<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/api/client'

interface AppBuild {
  uuid: string
  platform: string
  app_mode: string
  build_type: string
  app_name: string
  file_size: number | null
  download_url: string
  completed_at: string
  created_at: string
}

const builds = ref<AppBuild[]>([])
const loading = ref(true)
const error = ref('')
const toast = ref('')

const customerBuild = computed(() => builds.value.find(b => b.app_mode === 'customer') || null)
const driverBuild = computed(() => builds.value.find(b => b.app_mode === 'driver') || null)

function formatFileSize(bytes: number | null): string {
  if (!bytes) return ''
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB'
  if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB'
  return bytes + ' B'
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-IN', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

async function copyLink(url: string) {
  try {
    await navigator.clipboard.writeText(url)
    showToast('Download link copied!')
  } catch {
    // Fallback
    const ta = document.createElement('textarea')
    ta.value = url
    ta.style.position = 'fixed'
    ta.style.left = '-9999px'
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
    showToast('Download link copied!')
  }
}

function shareWhatsApp(build: AppBuild) {
  const text = `Download ${build.app_name} (${build.app_mode} app):\n${build.download_url}`
  window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank')
}

async function shareBuild(build: AppBuild) {
  if (navigator.share) {
    try {
      await navigator.share({
        title: `${build.app_name} - ${build.app_mode} app`,
        text: `Download ${build.app_name}:`,
        url: build.download_url,
      })
      return
    } catch (e: any) {
      if (e.name === 'AbortError') return
    }
  }
  await copyLink(build.download_url)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await apiClient.get('/admin/app-builds')
    if (data.success) {
      builds.value = data.data || []
    } else {
      error.value = data.error?.message || 'Failed to load builds'
    }
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'Unable to load app builds.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="list-page">
    <div class="list-page-intro">
      <div>
        <p class="list-kicker">Distribution</p>
        <h1>Mobile Apps</h1>
        <p>Download your store's customer and driver apps.</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="list-loading"><span></span><span style="animation-delay:.15s"></span><span style="animation-delay:.3s"></span> Loading builds...</div>

    <!-- Error -->
    <div v-else-if="error" class="apps-error">
      <strong>Could not load app builds</strong>
      <span>{{ error }}</span>
      <button class="apps-retry" @click="load">Retry</button>
    </div>

    <!-- No builds -->
    <div v-else-if="!builds.length" class="apps-empty">
      <div class="apps-empty__icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
          <line x1="12" y1="18" x2="12.01" y2="18"/>
        </svg>
      </div>
      <h2>No apps available yet</h2>
      <p>Your mobile apps will appear here once the platform administrator generates a build for your store.</p>
    </div>

    <!-- Builds exist -->
    <template v-else>
      <div class="apps-grid">

        <!-- Customer App -->
        <div v-if="customerBuild" class="app-card app-card--customer">
          <div class="app-card__badge">
            <div class="app-card__icon app-card__icon--customer">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
            </div>
            <div>
              <h2>Customer App</h2>
              <p>For your customers to browse, order and track deliveries.</p>
            </div>
          </div>
          <div class="app-card__body">
            <span class="app-card__platform" :class="customerBuild.platform">{{ customerBuild.platform === 'android' ? 'Android' : 'iOS' }}</span>
            <h3>{{ customerBuild.app_name }}</h3>
            <div class="app-card__meta">
              <span v-if="customerBuild.file_size">{{ formatFileSize(customerBuild.file_size) }}</span>
              <span>Built {{ formatDate(customerBuild.completed_at || customerBuild.created_at) }}</span>
            </div>
          </div>
          <div class="app-card__actions">
            <a :href="customerBuild.download_url" target="_blank" class="app-card__download">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              Download APK
            </a>
            <div class="app-card__share-row">
              <button class="app-card__share app-card__share--wa" @click="shareWhatsApp(customerBuild)" title="Share via WhatsApp">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                WhatsApp
              </button>
              <button class="app-card__share app-card__share--copy" @click="copyLink(customerBuild.download_url)" title="Copy download link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
                Copy Link
              </button>
              <button class="app-card__share app-card__share--more" @click="shareBuild(customerBuild)" title="Share">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                  <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
                Share
              </button>
            </div>
          </div>
        </div>

        <!-- Driver App -->
        <div v-if="driverBuild" class="app-card app-card--driver">
          <div class="app-card__badge">
            <div class="app-card__icon app-card__icon--driver">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <circle cx="12" cy="12" r="3"/>
                <line x1="12" y1="2" x2="12" y2="5"/>
                <line x1="12" y1="19" x2="12" y2="22"/>
                <line x1="2" y1="12" x2="5" y2="12"/>
                <line x1="19" y1="12" x2="22" y2="12"/>
              </svg>
            </div>
            <div>
              <h2>Driver App</h2>
              <p>For your delivery partners to accept and complete deliveries.</p>
            </div>
          </div>
          <div class="app-card__body">
            <span class="app-card__platform" :class="driverBuild.platform">{{ driverBuild.platform === 'android' ? 'Android' : 'iOS' }}</span>
            <h3>{{ driverBuild.app_name }}</h3>
            <div class="app-card__meta">
              <span v-if="driverBuild.file_size">{{ formatFileSize(driverBuild.file_size) }}</span>
              <span>Built {{ formatDate(driverBuild.completed_at || driverBuild.created_at) }}</span>
            </div>
          </div>
          <div class="app-card__actions">
            <a :href="driverBuild.download_url" target="_blank" class="app-card__download app-card__download--driver">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              Download APK
            </a>
            <div class="app-card__share-row">
              <button class="app-card__share app-card__share--wa" @click="shareWhatsApp(driverBuild)" title="Share via WhatsApp">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                WhatsApp
              </button>
              <button class="app-card__share app-card__share--copy" @click="copyLink(driverBuild.download_url)" title="Copy download link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
                Copy Link
              </button>
              <button class="app-card__share app-card__share--more" @click="shareBuild(driverBuild)" title="Share">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                  <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
                Share
              </button>
            </div>
          </div>
        </div>

      </div>

      <div class="apps-note">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        <span>Apps are generated by the platform. Contact support if you need a new build or an update.</span>
      </div>
    </template>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast" class="apps-toast">{{ toast }}</div>
    </Transition>
  </div>
</template>

<style scoped>
.apps-error {
  display: grid; gap: 6px; padding: 24px; border: 1px solid #fecaca;
  border-radius: 16px; background: #fef2f2; color: #991b1b; font-size: 13px;
}
.apps-error span { color: #b91c1c; font-size: 12px; }
.apps-retry {
  justify-self: start; margin-top: 6px; padding: 8px 16px; border: 1px solid #fca5a5;
  border-radius: 10px; background: #fff; color: #991b1b; font-weight: 700; font-size: 12px; cursor: pointer;
}
.apps-retry:hover { background: #fef2f2; }

.apps-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  min-height: 380px; padding: 40px; border: 2px dashed var(--line); border-radius: 24px;
  text-align: center; color: #8a8e9a;
}
.apps-empty__icon { color: #c8c8d0; margin-bottom: 16px; }
.apps-empty h2 { margin: 0 0 8px; color: var(--ink); font-size: 20px; letter-spacing: -.4px; }
.apps-empty p { max-width: 360px; margin: 0; font-size: 13px; line-height: 1.6; }

.apps-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px;
  margin-bottom: 24px;
}

.app-card {
  display: flex; flex-direction: column; padding: 24px;
  border: 1px solid var(--line); border-radius: 24px; background: #fff;
  box-shadow: 0 8px 24px rgba(20,20,30,.04);
  transition: transform .25s cubic-bezier(.34, 1.56, .64, 1), box-shadow .25s ease;
}
.app-card:hover { transform: translateY(-3px); box-shadow: 0 14px 32px rgba(20,20,30,.1); }

.app-card__badge {
  display: flex; align-items: center; gap: 14px; margin-bottom: 20px;
  padding-bottom: 16px; border-bottom: 1px solid var(--line);
}
.app-card__icon {
  display: grid; width: 48px; height: 48px; place-items: center;
  border-radius: 16px; color: #fff; flex-shrink: 0;
}
.app-card__icon--customer { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
.app-card__icon--driver { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
.app-card__badge h2 { margin: 0; color: var(--ink); font-size: 16px; font-weight: 800; letter-spacing: -.3px; }
.app-card__badge p { margin: 3px 0 0; color: #8a8e9a; font-size: 11px; }

.app-card__body { flex: 1; margin-bottom: 18px; }
.app-card__platform {
  display: inline-flex; align-items: center; padding: 4px 10px; margin-bottom: 10px;
  border-radius: 8px; font-size: 11px; font-weight: 800; text-transform: capitalize;
}
.app-card__platform.android { color: #166534; background: #dcfce7; }
.app-card__platform.ios { color: #1e40af; background: #dbeafe; }
.app-card__body h3 { margin: 0 0 8px; color: var(--ink); font-size: 18px; font-weight: 800; letter-spacing: -.3px; }
.app-card__meta { display: flex; flex-wrap: wrap; gap: 6px; }
.app-card__meta span {
  padding: 3px 8px; border-radius: 6px; background: #f9fafb;
  color: #6b7280; font-size: 11px; font-weight: 600;
}

.app-card__actions { display: flex; flex-direction: column; gap: 10px; }

.app-card__download {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 14px 20px; border: 0; border-radius: 14px;
  color: #fff; background: var(--primary);
  font-size: 14px; font-weight: 800; text-decoration: none;
  box-shadow: 0 6px 16px color-mix(in srgb, var(--primary) 25%, transparent);
  transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
}
.app-card__download:hover {
  background: var(--primary-deep);
  transform: translateY(-2px);
  box-shadow: 0 10px 22px color-mix(in srgb, var(--primary) 35%, transparent);
}
.app-card__download:active { transform: scale(.97); }
.app-card__download--driver {
  background: #7c3aed;
  box-shadow: 0 6px 16px rgba(124,58,237,.25);
}
.app-card__download--driver:hover {
  background: #6d28d9;
  box-shadow: 0 10px 22px rgba(124,58,237,.35);
}

.app-card__share-row {
  display: flex; gap: 8px;
}
.app-card__share {
  flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 10px 12px; border: 1px solid var(--line); border-radius: 12px;
  background: #fff; color: #4b5563; font-size: 12px; font-weight: 700;
  cursor: pointer; transition: all .2s ease;
}
.app-card__share:hover { background: #f9fafb; border-color: #d1d5db; }
.app-card__share--wa { color: #25d366; border-color: #bbf7d0; }
.app-card__share--wa:hover { background: #f0fdf4; border-color: #86efac; }
.app-card__share--copy:hover { color: var(--primary); border-color: color-mix(in srgb, var(--primary) 30%, #e5e7eb); }

.apps-note {
  display: flex; align-items: center; gap: 10px; padding: 14px 18px;
  border: 1px solid var(--line); border-radius: 12px;
  color: #6b7280; font-size: 12px; background: #fafbfc;
}

/* Toast */
.apps-toast {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  padding: 12px 24px; border-radius: 12px; background: var(--ink); color: #fff;
  font-size: 13px; font-weight: 700; box-shadow: 0 12px 32px rgba(0,0,0,.2);
  z-index: 100; white-space: nowrap;
}
.toast-enter-active { animation: toast-in .3s ease; }
.toast-leave-active { animation: toast-in .25s ease reverse; }
@keyframes toast-in { from { opacity: 0; transform: translateX(-50%) translateY(12px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }

@media (max-width: 640px) {
  .apps-grid { grid-template-columns: 1fr; }
  .app-card__share-row { flex-wrap: wrap; }
  .app-card__share { font-size: 11px; padding: 8px 10px; }
}
</style>
