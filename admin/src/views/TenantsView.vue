<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { platformApi } from '@/api/platform'

interface Tenant {
  id: string
  name: string
  slug: string
  business_type: string
  status: string
  contact_email: string | null
  contact_phone: string | null
  timezone: string
  currency: string
  created_at: string
  admin_count: number
  order_count: number
  total_revenue: number
  capabilities: Record<string, boolean>
}

interface TenantAdmin {
  uuid: string
  name: string
  email: string
  role: string
  status: string
  last_login_at: string | null
  created_at: string
}

interface AppBuild {
  uuid: string
  platform: string
  app_mode: string
  build_type: string
  status: string
  app_name: string
  app_id: string
  github_run_url: string | null
  download_url: string | null
  share_token: string | null
  share_url: string | null
  file_size: number | null
  error_message: string | null
  completed_at: string | null
  created_at: string
}

const tenants = ref<Tenant[]>([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const statusFilter = ref('')

// Modals
const showCreateModal = ref(false)
const showDetailModal = ref(false)
const showAdminModal = ref(false)
const showBuildModal = ref(false)
const saving = ref(false)
const building = ref(false)

// Create form
const form = ref({
  name: '', slug: '', business_type: 'restaurant', status: 'active',
  contact_email: '', contact_phone: '', address: '',
  owner_name: '', owner_email: '', owner_password: '',
})

// Detail/edit
const selectedTenant = ref<Tenant | null>(null)
const tenantAdmins = ref<TenantAdmin[]>([])
const editForm = ref<Record<string, unknown>>({})
const createdToken = ref('')

// Admin create form
const adminForm = ref({ name: '', email: '', password: '', role: 'tenant_owner' })

// Builds
const tenantBuilds = ref<AppBuild[]>([])
const buildForm = ref({ platform: 'android', app_mode: 'customer', build_type: 'apk', app_name: '', app_id: 'com.cloudmarket.cloudstore', app_token: '', primary_color: '#4CAF50' })
const copiedToken = ref('')
const fetchingBuild = ref('')

const allCapabilities = [
  { key: 'orders', label: 'Orders' },
  { key: 'delivery', label: 'Delivery' },
  { key: 'pickup', label: 'Pickup' },
  { key: 'coupons', label: 'Coupons' },
  { key: 'promotions', label: 'Promotions' },
  { key: 'bundles', label: 'Bundles' },
  { key: 'drivers', label: 'Fleet / Drivers' },
  { key: 'payments_online', label: 'Online Payments' },
  { key: 'loyalty', label: 'Loyalty Program' },
  { key: 'reviews', label: 'Reviews & Ratings' },
]

const businessTypes = [
  'restaurant', 'hotel', 'home_kitchen', 'meat_shop', 'fish_shop',
  'bakery', 'cloud_kitchen', 'catering', 'sweet_shop', 'juice_shop', 'other',
]

const filtered = computed(() => {
  let list = tenants.value
  if (statusFilter.value) list = list.filter(t => t.status === statusFilter.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(t => t.name.toLowerCase().includes(q) || t.slug.toLowerCase().includes(q) || (t.contact_email || '').toLowerCase().includes(q))
  }
  return list
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await platformApi.getTenants()
    tenants.value = data.data || []
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'Failed to load tenants'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.value = { name: '', slug: '', business_type: 'restaurant', status: 'active', contact_email: '', contact_phone: '', address: '', owner_name: '', owner_email: '', owner_password: '' }
  createdToken.value = ''
  showCreateModal.value = true
}

function autoSlug() {
  form.value.slug = form.value.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
}

async function createTenant() {
  saving.value = true
  error.value = ''
  try {
    const { data } = await platformApi.createTenant(form.value)
    if (data.data?.app_token) {
      createdToken.value = data.data.app_token
    }
    showCreateModal.value = false
    await load()
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'Failed to create tenant'
  } finally {
    saving.value = false
  }
}

async function openDetail(tenant: Tenant) {
  selectedTenant.value = tenant
  editForm.value = {
    name: tenant.name, status: tenant.status, business_type: tenant.business_type,
    contact_email: tenant.contact_email || '', contact_phone: tenant.contact_phone || '',
  }
  showDetailModal.value = true

  // Load admins and builds in parallel
  try {
    const [adminsRes] = await Promise.all([
      platformApi.getTenantAdmins(tenant.id),
      loadBuilds(tenant.id),
    ])
    tenantAdmins.value = adminsRes.data.data || []
  } catch { tenantAdmins.value = [] }
}

async function saveTenant() {
  if (!selectedTenant.value) return
  saving.value = true
  try {
    await platformApi.updateTenant(selectedTenant.value.id, editForm.value)
    showDetailModal.value = false
    await load()
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'Failed to update'
  } finally {
    saving.value = false
  }
}

async function toggleCapability(tenantId: string, cap: string, enabled: boolean) {
  try {
    await platformApi.updateCapabilities(tenantId, { [cap]: enabled })
    // Update local state
    const t = tenants.value.find(t => t.id === tenantId)
    if (t) t.capabilities[cap] = enabled
    if (selectedTenant.value?.id === tenantId) selectedTenant.value.capabilities[cap] = enabled
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'Failed to update capability'
  }
}

function openAdminCreate() {
  adminForm.value = { name: '', email: '', password: '', role: 'tenant_owner' }
  showAdminModal.value = true
}

async function createAdmin() {
  if (!selectedTenant.value) return
  saving.value = true
  try {
    await platformApi.createTenantAdmin(selectedTenant.value.id, adminForm.value)
    showAdminModal.value = false
    // Reload admins
    const { data } = await platformApi.getTenantAdmins(selectedTenant.value.id)
    tenantAdmins.value = data.data || []
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'Failed to create admin'
  } finally {
    saving.value = false
  }
}

// ── Builds ──

async function loadBuilds(tenantUuid: string) {
  try {
    const { data } = await platformApi.getBuilds(tenantUuid)
    tenantBuilds.value = data.data || []
  } catch { tenantBuilds.value = [] }
}

function tenantToAppId(slug: string): string {
  const clean = slug.replace(/[^a-z0-9]/g, '')
  return `com.cloudmarket.${clean}`
}

const tokenPrefix = ref('')
const regeneratingToken = ref(false)

async function openBuildModal() {
  if (!selectedTenant.value) return
  const slug = selectedTenant.value.slug || selectedTenant.value.name.toLowerCase().replace(/[^a-z0-9]+/g, '')
  tokenPrefix.value = ''

  // Fetch tenant details to get token prefix
  try {
    const { data } = await platformApi.getTenant(selectedTenant.value.id)
    tokenPrefix.value = data.data?.app_token_prefix || ''
  } catch { /* ignore */ }

  buildForm.value = {
    platform: 'android', app_mode: 'customer', build_type: 'apk',
    app_name: selectedTenant.value.name, app_id: tenantToAppId(slug),
    app_token: '', primary_color: '#4CAF50',
  }
  showBuildModal.value = true
}

async function regenerateToken() {
  if (!selectedTenant.value) return
  regeneratingToken.value = true
  try {
    const { data } = await platformApi.regenerateToken(selectedTenant.value.id)
    if (data.data?.app_token) {
      buildForm.value.app_token = data.data.app_token
      tokenPrefix.value = data.data.prefix || ''
    }
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'Failed to regenerate token'
  } finally {
    regeneratingToken.value = false
  }
}

async function triggerBuild() {
  if (!selectedTenant.value) return
  building.value = true
  error.value = ''
  try {
    await platformApi.triggerBuild(selectedTenant.value.id, buildForm.value)
    showBuildModal.value = false
    await loadBuilds(selectedTenant.value.id)
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'Failed to trigger build'
  } finally {
    building.value = false
  }
}

function buildStatusColor(s: string) {
  const map: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-600',
    queued: 'bg-blue-100 text-blue-700',
    building: 'bg-yellow-100 text-yellow-800',
    completed: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
  }
  return map[s] || 'bg-gray-100 text-gray-600'
}

function copyShareLink(url: string) {
  navigator.clipboard.writeText(url)
  copiedToken.value = url
  setTimeout(() => copiedToken.value = '', 2000)
}

async function fetchBuildArtifact(buildUuid: string) {
  fetchingBuild.value = buildUuid
  try {
    const res = await platformApi.fetchArtifact(buildUuid)
    const updated = res.data?.data?.build
    if (updated) {
      const idx = tenantBuilds.value.findIndex(b => b.uuid === buildUuid)
      if (idx !== -1) Object.assign(tenantBuilds.value[idx], updated)
    }
  } catch (e: any) {
    alert(e.response?.data?.error?.message || 'Failed to fetch artifact')
  } finally {
    fetchingBuild.value = ''
  }
}

function formatFileSize(bytes: number | null) {
  if (!bytes) return '-'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function formatMoney(paise: number) {
  return '\u20B9' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 0 })
}

function statusColor(s: string) {
  return s === 'active' ? 'bg-green-100 text-green-800' : s === 'suspended' ? 'bg-red-100 text-red-800' : s === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600'
}

onMounted(load)
</script>

<template>
    <div class="list-page">
      <div class="list-page-intro">
        <div>
          <p class="list-kicker">Platform Management</p>
          <h1>Tenants <span>/ {{ tenants.length }} stores</span></h1>
          <p>Manage all stores, features, and access</p>
        </div>
        <button class="list-primary-action" @click="openCreate">
          <span>+</span> New Tenant
        </button>
      </div>

      <!-- Token display -->
      <div v-if="createdToken" class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl">
        <p class="text-sm font-bold text-green-800 mb-1">App Token (save it — shown only once):</p>
        <code class="text-xs break-all text-green-900 bg-green-100 px-2 py-1 rounded">{{ createdToken }}</code>
      </div>

      <div v-if="error" class="ob-feedback ob-feedback--error mb-4">{{ error }}</div>

      <!-- Toolbar -->
      <div class="list-toolbar">
        <div class="list-search">
          <span>&#x1F50D;</span>
          <input v-model="search" placeholder="Search tenants..." />
        </div>
        <select v-model="statusFilter" class="list-select">
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="draft">Draft</option>
          <option value="suspended">Suspended</option>
        </select>
      </div>

      <!-- Table -->
      <div v-if="loading" class="list-loading"><span></span> Loading tenants...</div>
      <div v-else-if="filtered.length === 0" class="list-empty">No tenants found.</div>
      <div v-else class="list-table-card">
        <table class="list-table">
          <thead>
            <tr>
              <th>Store</th>
              <th>Type</th>
              <th>Status</th>
              <th>Admins</th>
              <th>Orders</th>
              <th>Revenue</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in filtered" :key="t.id" class="list-row" @click="openDetail(t)">
              <td>
                <div class="list-order-id">
                  <div class="list-row-mark">{{ t.name.charAt(0) }}</div>
                  <div>
                    <strong>{{ t.name }}</strong>
                    <small>{{ t.slug }} &middot; {{ t.contact_email }}</small>
                  </div>
                </div>
              </td>
              <td><span class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ t.business_type.replace('_', ' ') }}</span></td>
              <td><span class="list-status px-2 py-1 rounded-full text-xs font-bold" :class="statusColor(t.status)">{{ t.status }}</span></td>
              <td class="text-center font-bold">{{ t.admin_count }}</td>
              <td class="text-center font-bold">{{ t.order_count }}</td>
              <td class="font-bold">{{ formatMoney(t.total_revenue) }}</td>
              <td class="list-action-cell">
                <button class="list-edit-button" @click.stop="openDetail(t)">Manage <span>&rarr;</span></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showCreateModal = false">
      <div class="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between pb-4 border-b mb-4">
          <h2 class="text-lg font-bold">Create New Tenant</h2>
          <button class="text-gray-400 hover:text-gray-600 text-xl" @click="showCreateModal = false">&times;</button>
        </div>
        <form @submit.prevent="createTenant" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">Store Name *</label>
            <input v-model="form.name" @input="autoSlug" required class="w-full border rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">Slug *</label>
            <input v-model="form.slug" required class="w-full border rounded-lg px-3 py-2" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1">Business Type *</label>
              <select v-model="form.business_type" class="w-full border rounded-lg px-3 py-2">
                <option v-for="bt in businessTypes" :key="bt" :value="bt">{{ bt.replace('_', ' ') }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1">Status</label>
              <select v-model="form.status" class="w-full border rounded-lg px-3 py-2">
                <option value="active">Active</option>
                <option value="draft">Draft</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">Contact Email *</label>
            <input v-model="form.contact_email" type="email" required class="w-full border rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">Contact Phone</label>
            <input v-model="form.contact_phone" class="w-full border rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">Address</label>
            <textarea v-model="form.address" rows="2" class="w-full border rounded-lg px-3 py-2"></textarea>
          </div>

          <div class="border-t pt-4">
            <h3 class="text-sm font-bold mb-3">Owner Account (optional)</h3>
            <div class="space-y-3">
              <input v-model="form.owner_name" placeholder="Owner name" class="w-full border rounded-lg px-3 py-2" />
              <input v-model="form.owner_email" type="email" placeholder="Owner email" class="w-full border rounded-lg px-3 py-2" />
              <input v-model="form.owner_password" type="password" placeholder="Owner password (min 8 chars)" class="w-full border rounded-lg px-3 py-2" />
            </div>
          </div>

          <div class="flex gap-3 pt-2">
            <button type="button" class="flex-1 py-2 border rounded-lg font-bold text-gray-600" @click="showCreateModal = false">Cancel</button>
            <button type="submit" :disabled="saving" class="flex-1 py-2 rounded-lg font-bold text-white bg-red-600 hover:bg-red-700 disabled:opacity-50">
              {{ saving ? 'Creating...' : 'Create Tenant' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Detail/Edit Modal -->
    <div v-if="showDetailModal && selectedTenant" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showDetailModal = false">
      <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between pb-4 border-b mb-4">
          <h2 class="text-lg font-bold">{{ selectedTenant.name }}</h2>
          <button class="text-gray-400 hover:text-gray-600 text-xl" @click="showDetailModal = false">&times;</button>
        </div>

        <!-- Edit basic info -->
        <div class="space-y-3 mb-6">
          <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wide">Store Info</h3>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1">Name</label>
              <input v-model="editForm.name" class="w-full border rounded-lg px-3 py-2" />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1">Status</label>
              <select v-model="editForm.status" class="w-full border rounded-lg px-3 py-2">
                <option value="active">Active</option>
                <option value="draft">Draft</option>
                <option value="suspended">Suspended</option>
                <option value="archived">Archived</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1">Email</label>
              <input v-model="editForm.contact_email" class="w-full border rounded-lg px-3 py-2" />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1">Phone</label>
              <input v-model="editForm.contact_phone" class="w-full border rounded-lg px-3 py-2" />
            </div>
          </div>
          <button @click="saveTenant" :disabled="saving" class="px-4 py-2 rounded-lg font-bold text-white bg-red-600 hover:bg-red-700 disabled:opacity-50">
            {{ saving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>

        <!-- Features / Capabilities -->
        <div class="mb-6">
          <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-3">Features</h3>
          <div class="grid grid-cols-2 gap-2">
            <label v-for="cap in allCapabilities" :key="cap.key" class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition" :class="selectedTenant.capabilities[cap.key] ? 'border-green-300 bg-green-50' : ''">
              <input type="checkbox" :checked="selectedTenant.capabilities[cap.key]" @change="toggleCapability(selectedTenant!.id, cap.key, ($event.target as HTMLInputElement).checked)" class="w-4 h-4 accent-red-600" />
              <span class="text-sm font-semibold">{{ cap.label }}</span>
            </label>
          </div>
        </div>

        <!-- App Builds -->
        <div class="mb-6">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wide">App Builds</h3>
            <button class="text-xs font-bold text-red-600 hover:text-red-700" @click="openBuildModal">+ New Build</button>
          </div>
          <div v-if="tenantBuilds.length === 0" class="text-sm text-gray-400 py-4 text-center border rounded-xl">No builds yet — trigger one to generate the tenant's mobile app.</div>
          <div v-else class="space-y-2">
            <div v-for="b in tenantBuilds" :key="b.uuid" class="p-3 border rounded-xl">
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold uppercase px-2 py-0.5 rounded" :class="b.platform === 'android' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">{{ b.platform }}</span>
                  <span class="text-xs text-gray-500">{{ b.app_mode }} &middot; {{ b.build_type }}</span>
                </div>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full" :class="buildStatusColor(b.status)">{{ b.status }}</span>
              </div>
              <p class="text-sm font-semibold">{{ b.app_name }}</p>
              <p class="text-xs text-gray-400">{{ new Date(b.created_at).toLocaleString() }} &middot; {{ formatFileSize(b.file_size) }}</p>
              <div class="flex items-center gap-2 mt-2 flex-wrap">
                <a v-if="b.github_run_url" :href="b.github_run_url" target="_blank" class="text-xs text-blue-600 hover:underline">GitHub Run</a>
                <a v-if="b.share_url && b.status === 'completed'" :href="b.share_url" class="text-xs font-bold text-green-600 hover:underline">Download APK</a>
                <button v-if="b.status === 'completed' && !b.download_url && b.github_run_url" class="text-xs font-bold text-orange-600 hover:underline" :disabled="fetchingBuild === b.uuid" @click="fetchBuildArtifact(b.uuid)">
                  {{ fetchingBuild === b.uuid ? 'Fetching...' : 'Fetch APK from GitHub' }}
                </button>
                <button v-if="b.share_url && b.status === 'completed'" class="text-xs font-bold text-purple-600 hover:underline" @click="copyShareLink(b.share_url)">
                  {{ copiedToken === b.share_url ? 'Copied!' : 'Copy Share Link' }}
                </button>
                <span v-if="b.error_message" class="text-xs text-red-500 truncate max-w-[200px]" :title="b.error_message">{{ b.error_message }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Admins -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wide">Admins</h3>
            <button class="text-xs font-bold text-red-600 hover:text-red-700" @click="openAdminCreate">+ Add Admin</button>
          </div>
          <div v-if="tenantAdmins.length === 0" class="text-sm text-gray-400 py-4 text-center">No admins yet</div>
          <div v-else class="space-y-2">
            <div v-for="a in tenantAdmins" :key="a.uuid" class="flex items-center justify-between p-3 border rounded-xl">
              <div>
                <p class="text-sm font-bold">{{ a.name }}</p>
                <p class="text-xs text-gray-500">{{ a.email }}</p>
              </div>
              <div class="text-right">
                <span class="text-xs font-bold px-2 py-1 rounded-full" :class="a.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'">{{ a.role.replace('_', ' ') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Admin Modal -->
    <div v-if="showAdminModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4" @click.self="showAdminModal = false">
      <div class="bg-white rounded-2xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">Add Admin to {{ selectedTenant?.name }}</h3>
        <form @submit.prevent="createAdmin" class="space-y-3">
          <input v-model="adminForm.name" placeholder="Name" required class="w-full border rounded-lg px-3 py-2" />
          <input v-model="adminForm.email" type="email" placeholder="Email" required class="w-full border rounded-lg px-3 py-2" />
          <input v-model="adminForm.password" type="password" placeholder="Password (min 8)" required minlength="8" class="w-full border rounded-lg px-3 py-2" />
          <select v-model="adminForm.role" class="w-full border rounded-lg px-3 py-2">
            <option value="tenant_owner">Owner</option>
            <option value="tenant_admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="staff">Staff</option>
          </select>
          <div class="flex gap-3 pt-2">
            <button type="button" class="flex-1 py-2 border rounded-lg font-bold" @click="showAdminModal = false">Cancel</button>
            <button type="submit" :disabled="saving" class="flex-1 py-2 rounded-lg font-bold text-white bg-red-600 hover:bg-red-700 disabled:opacity-50">
              {{ saving ? 'Creating...' : 'Create Admin' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Trigger Build Modal -->
    <div v-if="showBuildModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4" @click.self="showBuildModal = false">
      <div class="bg-white rounded-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between pb-4 border-b mb-4">
          <h3 class="text-lg font-bold">Build App for {{ selectedTenant?.name }}</h3>
          <button class="text-gray-400 hover:text-gray-600 text-xl" @click="showBuildModal = false">&times;</button>
        </div>
        <form @submit.prevent="triggerBuild" class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1">Platform *</label>
              <select v-model="buildForm.platform" class="w-full border rounded-lg px-3 py-2" @change="buildForm.build_type = buildForm.platform === 'android' ? 'apk' : 'ad-hoc'">
                <option value="android">Android</option>
                <option value="ios">iOS</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1">App Mode *</label>
              <select v-model="buildForm.app_mode" class="w-full border rounded-lg px-3 py-2">
                <option value="customer">Customer App</option>
                <option value="driver">Driver App</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">Build Type</label>
            <select v-model="buildForm.build_type" class="w-full border rounded-lg px-3 py-2">
              <template v-if="buildForm.platform === 'android'">
                <option value="apk">APK</option>
                <option value="appbundle">App Bundle (AAB)</option>
                <option value="both">Both (APK + AAB)</option>
              </template>
              <template v-else>
                <option value="ad-hoc">Ad Hoc</option>
                <option value="app-store">App Store</option>
                <option value="development">Development</option>
              </template>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">App Display Name</label>
            <input v-model="buildForm.app_name" class="w-full border rounded-lg px-3 py-2" placeholder="Store name shown on device" />
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">Application ID</label>
            <input v-model="buildForm.app_id" class="w-full border rounded-lg px-3 py-2" placeholder="com.cloudmarket.cloudstore" />
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">App Token *</label>
            <div class="flex gap-2">
              <input v-model="buildForm.app_token" required class="flex-1 border rounded-lg px-3 py-2 font-mono text-xs" :placeholder="tokenPrefix ? `Starts with ${tokenPrefix}...` : 'Tenant app token'" />
              <button type="button" @click="regenerateToken" :disabled="regeneratingToken" class="px-3 py-2 bg-gray-100 border rounded-lg text-xs font-bold hover:bg-gray-200 whitespace-nowrap">
                {{ regeneratingToken ? 'Generating...' : 'Regenerate' }}
              </button>
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ buildForm.app_token ? 'Token set — will be injected into the app build' : 'Click Regenerate to create a new token (old one will be revoked)' }}</p>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">Primary Color</label>
            <div class="flex items-center gap-2">
              <input type="color" v-model="buildForm.primary_color" class="w-10 h-10 rounded border cursor-pointer" />
              <input v-model="buildForm.primary_color" class="flex-1 border rounded-lg px-3 py-2 font-mono text-sm" />
            </div>
          </div>
          <div v-if="error" class="text-sm text-red-600 bg-red-50 p-2 rounded-lg">{{ error }}</div>
          <div class="flex gap-3 pt-2">
            <button type="button" class="flex-1 py-2 border rounded-lg font-bold text-gray-600" @click="showBuildModal = false">Cancel</button>
            <button type="submit" :disabled="building" class="flex-1 py-2 rounded-lg font-bold text-white bg-red-600 hover:bg-red-700 disabled:opacity-50">
              {{ building ? 'Triggering...' : 'Start Build' }}
            </button>
          </div>
        </form>
      </div>
    </div>
</template>
