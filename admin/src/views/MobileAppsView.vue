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

const customerBuilds = computed(() => builds.value.filter(b => b.app_mode === 'customer'))
const driverBuilds = computed(() => builds.value.filter(b => b.app_mode === 'driver'))

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

function platformIcon(platform: string): string {
  return platform === 'android' ? '🤖' : '🍎'
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
      <!-- Customer Apps -->
      <section v-if="customerBuilds.length" class="apps-section">
        <div class="apps-section__header">
          <div class="apps-section__icon apps-section__icon--customer">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </div>
          <div>
            <h2>Customer App</h2>
            <p>For your customers to browse, order and track deliveries.</p>
          </div>
        </div>
        <div class="apps-grid">
          <div v-for="build in customerBuilds" :key="build.uuid" class="app-card">
            <div class="app-card__head">
              <span class="app-card__platform" :class="build.platform">{{ platformIcon(build.platform) }} {{ build.platform }}</span>
              <span class="app-card__type">{{ build.build_type.toUpperCase() }}</span>
            </div>
            <div class="app-card__body">
              <h3>{{ build.app_name }}</h3>
              <div class="app-card__meta">
                <span v-if="build.file_size">{{ formatFileSize(build.file_size) }}</span>
                <span>Built {{ formatDate(build.completed_at || build.created_at) }}</span>
              </div>
            </div>
            <a :href="build.download_url" target="_blank" class="app-card__download">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              Download
            </a>
          </div>
        </div>
      </section>

      <!-- Driver Apps -->
      <section v-if="driverBuilds.length" class="apps-section">
        <div class="apps-section__header">
          <div class="apps-section__icon apps-section__icon--driver">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
        <div class="apps-grid">
          <div v-for="build in driverBuilds" :key="build.uuid" class="app-card">
            <div class="app-card__head">
              <span class="app-card__platform" :class="build.platform">{{ platformIcon(build.platform) }} {{ build.platform }}</span>
              <span class="app-card__type">{{ build.build_type.toUpperCase() }}</span>
            </div>
            <div class="app-card__body">
              <h3>{{ build.app_name }}</h3>
              <div class="app-card__meta">
                <span v-if="build.file_size">{{ formatFileSize(build.file_size) }}</span>
                <span>Built {{ formatDate(build.completed_at || build.created_at) }}</span>
              </div>
            </div>
            <a :href="build.download_url" target="_blank" class="app-card__download">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              Download
            </a>
          </div>
        </div>
      </section>

      <div class="apps-note">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        <span>Apps are generated by the platform. Contact support if you need a new build or an update.</span>
      </div>
    </template>
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

.apps-section { margin-bottom: 32px; }
.apps-section__header {
  display: flex; align-items: center; gap: 14px; margin-bottom: 16px;
}
.apps-section__icon {
  display: grid; width: 48px; height: 48px; place-items: center;
  border-radius: 16px; color: #fff;
}
.apps-section__icon--customer { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
.apps-section__icon--driver { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
.apps-section__header h2 { margin: 0; color: var(--ink); font-size: 18px; letter-spacing: -.4px; }
.apps-section__header p { margin: 4px 0 0; color: #8a8e9a; font-size: 12px; }

.apps-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px;
}

.app-card {
  display: flex; flex-direction: column; padding: 20px;
  border: 1px solid var(--line); border-radius: 20px; background: #fff;
  box-shadow: 0 8px 24px rgba(20,20,30,.04);
  transition: transform .25s cubic-bezier(.34, 1.56, .64, 1), box-shadow .25s ease;
}
.app-card:hover { transform: translateY(-3px); box-shadow: 0 14px 32px rgba(20,20,30,.1); }

.app-card__head {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;
}
.app-card__platform {
  display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
  border-radius: 8px; font-size: 11px; font-weight: 800; text-transform: capitalize;
}
.app-card__platform.android { color: #166534; background: #dcfce7; }
.app-card__platform.ios { color: #1e40af; background: #dbeafe; }

.app-card__type {
  padding: 3px 8px; border-radius: 6px;
  color: #6b7280; background: #f3f4f6; font-size: 10px; font-weight: 800;
}

.app-card__body { flex: 1; margin-bottom: 16px; }
.app-card__body h3 { margin: 0 0 8px; color: var(--ink); font-size: 16px; font-weight: 800; letter-spacing: -.3px; }
.app-card__meta {
  display: flex; flex-wrap: wrap; gap: 6px;
}
.app-card__meta span {
  padding: 3px 8px; border-radius: 6px; background: #f9fafb;
  color: #6b7280; font-size: 11px; font-weight: 600;
}

.app-card__download {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 12px 20px; border: 0; border-radius: 14px;
  color: #fff; background: var(--primary);
  font-size: 13px; font-weight: 800; text-decoration: none;
  box-shadow: 0 6px 16px color-mix(in srgb, var(--primary) 25%, transparent);
  transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
}
.app-card__download:hover {
  background: var(--primary-deep);
  transform: translateY(-2px);
  box-shadow: 0 10px 22px color-mix(in srgb, var(--primary) 35%, transparent);
}
.app-card__download:active { transform: scale(.97); }

.apps-note {
  display: flex; align-items: center; gap: 10px; padding: 14px 18px;
  border: 1px solid var(--line); border-radius: 12px;
  color: #6b7280; font-size: 12px; background: #fafbfc;
}

@media (max-width: 640px) {
  .apps-grid { grid-template-columns: 1fr; }
  .apps-section__header { flex-direction: column; align-items: flex-start; gap: 10px; }
}
</style>
