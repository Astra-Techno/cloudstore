<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { offersApi } from '@/api/offers'

interface Coupon {
  uuid: string
  code: string
  title: string
  description: string
  discount_type: string
  discount_value: number
  min_order_amount: number
  max_discount_amount: number | null
  usage_limit: number | null
  per_customer_limit: number
  used_count: number
  starts_at: string | null
  expires_at: string | null
  is_active: number
  applies_to: string
}

const coupons = ref<Coupon[]>([])
const loading = ref(true)
const showForm = ref(false)
const editing = ref<Coupon | null>(null)
const saving = ref(false)
const error = ref('')
const searchQuery = ref('')
const deleteConfirm = ref<Coupon | null>(null)
const deleting = ref(false)

const form = ref({
  code: '',
  title: '',
  description: '',
  discount_type: 'percentage',
  discount_value: 0,
  min_order_amount: 0,
  max_discount_amount: null as number | null,
  usage_limit: null as number | null,
  per_customer_limit: 1,
  starts_at: '',
  expires_at: '',
  is_active: 1,
})

const filtered = computed(() => {
  if (!searchQuery.value) return coupons.value
  const q = searchQuery.value.toLowerCase()
  return coupons.value.filter(c => c.code.toLowerCase().includes(q) || c.title.toLowerCase().includes(q))
})

async function loadCoupons() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await offersApi.listCoupons()
    if (data.success) coupons.value = data.data || []
    else error.value = 'API returned: ' + JSON.stringify(data)
  } catch (e: any) {
    error.value = 'Load failed: ' + (e.response?.status || '') + ' ' + (e.response?.data?.error?.message || e.message || JSON.stringify(e))
    console.error('Failed to load coupons', e)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { code: '', title: '', description: '', discount_type: 'percentage', discount_value: 0, min_order_amount: 0, max_discount_amount: null, usage_limit: null, per_customer_limit: 1, starts_at: '', expires_at: '', is_active: 1 }
  error.value = ''
  showForm.value = true
}

function openEdit(c: Coupon) {
  editing.value = c
  form.value = {
    code: c.code,
    title: c.title,
    description: c.description || '',
    discount_type: c.discount_type,
    discount_value: c.discount_value,
    min_order_amount: c.min_order_amount,
    max_discount_amount: c.max_discount_amount,
    usage_limit: c.usage_limit,
    per_customer_limit: c.per_customer_limit,
    starts_at: c.starts_at || '',
    expires_at: c.expires_at || '',
    is_active: c.is_active,
  }
  error.value = ''
  showForm.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  if (form.value.starts_at && form.value.expires_at && form.value.expires_at <= form.value.starts_at) {
    error.value = 'Expiry date must be after start date'
    saving.value = false
    return
  }
  try {
    const payload = { ...form.value }
    if (editing.value) {
      await offersApi.updateCoupon(editing.value.uuid, payload)
    } else {
      await offersApi.createCoupon(payload)
    }
    showForm.value = false
    await loadCoupons()
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'Save failed'
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (!deleteConfirm.value) return
  deleting.value = true
  try {
    await offersApi.deleteCoupon(deleteConfirm.value.uuid)
    deleteConfirm.value = null
    await loadCoupons()
  } catch (e) {
    console.error('Delete failed', e)
  } finally {
    deleting.value = false
  }
}

function formatMoney(paise: number) {
  return '₹' + (paise / 100).toFixed(2)
}

function discountLabel(c: Coupon) {
  if (c.discount_type === 'percentage') return (c.discount_value / 100).toFixed(0) + '% Off'
  if (c.discount_type === 'fixed') return formatMoney(c.discount_value) + ' Off'
  return 'Free Delivery'
}

function statusClass(c: Coupon) {
  if (!c.is_active) return 'bg-gray-100 text-gray-600'
  if (c.expires_at && new Date(c.expires_at) < new Date()) return 'bg-yellow-100 text-yellow-700'
  return 'bg-green-100 text-green-700'
}

function statusLabel(c: Coupon) {
  if (!c.is_active) return 'Inactive'
  if (c.expires_at && new Date(c.expires_at) < new Date()) return 'Expired'
  return 'Active'
}

onMounted(loadCoupons)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Coupons</h1>
        <p class="text-sm text-gray-500 mt-1">Create and manage discount codes for your customers</p>
      </div>
      <button @click="openCreate" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition font-medium">
        + New Coupon
      </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="p-4 border-b border-gray-100">
        <input v-model="searchQuery" type="text" placeholder="Search coupons..." class="w-full sm:w-72 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent" />
      </div>

      <div v-if="loading" class="p-12 text-center text-gray-400">Loading...</div>

      <table v-else-if="filtered.length" class="mobile-records w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-left">Code</th>
            <th class="px-4 py-3 text-left">Title</th>
            <th class="px-4 py-3 text-left">Discount</th>
            <th class="px-4 py-3 text-left">Min Order</th>
            <th class="px-4 py-3 text-center">Used</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in filtered" :key="c.uuid" class="border-t border-gray-50 hover:bg-gray-50 transition">
            <td class="px-4 py-3 font-mono font-bold text-red-600">{{ c.code }}</td>
            <td class="px-4 py-3">{{ c.title }}</td>
            <td class="px-4 py-3 font-semibold">{{ discountLabel(c) }}</td>
            <td class="px-4 py-3">{{ c.min_order_amount > 0 ? formatMoney(c.min_order_amount) : '-' }}</td>
            <td class="px-4 py-3 text-center">{{ c.used_count }}{{ c.usage_limit ? ' / ' + c.usage_limit : '' }}</td>
            <td class="px-4 py-3 text-center">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(c)">{{ statusLabel(c) }}</span>
            </td>
            <td class="px-4 py-3 text-right space-x-2">
              <button @click="openEdit(c)" class="text-blue-600 hover:underline text-xs">Edit</button>
              <button @click="deleteConfirm = c" class="text-red-500 hover:underline text-xs">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else-if="error && !loading" class="p-6 text-center">
        <div class="bg-red-50 text-red-600 p-4 rounded-lg text-sm font-mono">{{ error }}</div>
      </div>
      <div v-else class="p-12 text-center text-gray-400">No coupons yet. Create your first coupon!</div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showForm = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b">
          <h2 class="text-lg font-bold">{{ editing ? 'Edit Coupon' : 'Create Coupon' }}</h2>
        </div>
        <form @submit.prevent="save" class="p-6 space-y-4">
          <div v-if="error" class="bg-red-50 text-red-600 p-3 rounded-lg text-sm">{{ error }}</div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
              <input v-model="form.code" required class="w-full border rounded-lg px-3 py-2 text-sm uppercase" placeholder="e.g. WELCOME10" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type</label>
              <select v-model="form.discount_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed Amount (₹)</option>
                <option value="free_delivery">Free Delivery</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input v-model="form.title" required class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="e.g. 10% Off First Order" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Optional description"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-4" v-if="form.discount_type !== 'free_delivery'">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ form.discount_type === 'percentage' ? 'Discount (basis pts, e.g. 1000 = 10%)' : 'Discount Amount (paise)' }}
              </label>
              <input v-model.number="form.discount_value" type="number" min="0" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Max Discount (paise, optional)</label>
              <input v-model.number="form.max_discount_amount" type="number" min="0" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="No cap" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Min Order Amount (paise)</label>
              <input v-model.number="form.min_order_amount" type="number" min="0" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Per Customer Limit</label>
              <input v-model.number="form.per_customer_limit" type="number" min="1" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Usage Limit (total)</label>
              <input v-model.number="form.usage_limit" type="number" min="0" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Unlimited" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select v-model.number="form.is_active" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option :value="1">Active</option>
                <option :value="0">Inactive</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Starts At</label>
              <input v-model="form.starts_at" type="datetime-local" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Expires At</label>
              <input v-model="form.expires_at" type="datetime-local" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <button type="button" @click="showForm = false" class="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm">Cancel</button>
            <button type="submit" :disabled="saving" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 text-sm font-medium disabled:opacity-50">
              {{ saving ? 'Saving...' : (editing ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirm -->
    <div v-if="deleteConfirm" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="deleteConfirm = null">
      <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm">
        <h3 class="font-bold text-lg mb-2">Delete Coupon</h3>
        <p class="text-gray-600 text-sm mb-4">Delete coupon <strong>{{ deleteConfirm.code }}</strong>? This cannot be undone.</p>
        <div class="flex justify-end gap-3">
          <button @click="deleteConfirm = null" class="px-4 py-2 text-gray-600 text-sm">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm">
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
