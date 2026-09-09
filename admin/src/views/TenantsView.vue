<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import AppLayout from '@/components/AppLayout.vue'
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

const tenants = ref<Tenant[]>([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const statusFilter = ref('')

// Modals
const showCreateModal = ref(false)
const showDetailModal = ref(false)
const showAdminModal = ref(false)
const saving = ref(false)

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

  // Load admins
  try {
    const { data } = await platformApi.getTenantAdmins(tenant.id)
    tenantAdmins.value = data.data || []
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

function formatMoney(paise: number) {
  return '\u20B9' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 0 })
}

function statusColor(s: string) {
  return s === 'active' ? 'bg-green-100 text-green-800' : s === 'suspended' ? 'bg-red-100 text-red-800' : s === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600'
}

onMounted(load)
</script>

<template>
  <AppLayout>
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
  </AppLayout>
</template>
