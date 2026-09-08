<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { offersApi } from '@/api/offers'

interface Promotion {
  uuid: string
  title: string
  description: string
  promotion_type: string
  discount_type: string
  discount_value: number
  max_discount_amount: number | null
  min_order_amount: number
  rules: Record<string, any>
  priority: number
  is_stackable: number
  usage_limit: number | null
  used_count: number
  starts_at: string | null
  expires_at: string | null
  is_active: number
}

const promotions = ref<Promotion[]>([])
const loading = ref(true)
const showForm = ref(false)
const editing = ref<Promotion | null>(null)
const saving = ref(false)
const error = ref('')
const deleteConfirm = ref<Promotion | null>(null)
const deleting = ref(false)

const form = ref({
  title: '',
  description: '',
  promotion_type: 'order_discount',
  discount_type: 'percentage',
  discount_value: 0,
  max_discount_amount: null as number | null,
  min_order_amount: 0,
  rules: {} as Record<string, any>,
  priority: 0,
  is_stackable: 0,
  usage_limit: null as number | null,
  starts_at: '',
  expires_at: '',
  is_active: 1,
})

async function loadPromotions() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await offersApi.listPromotions()
    if (data.success) promotions.value = data.data || []
    else error.value = 'API returned: ' + JSON.stringify(data)
  } catch (e: any) {
    error.value = 'Load failed: ' + (e.response?.status || '') + ' ' + (e.response?.data?.error?.message || e.message || JSON.stringify(e))
    console.error('Failed to load promotions', e)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { title: '', description: '', promotion_type: 'order_discount', discount_type: 'percentage', discount_value: 0, max_discount_amount: null, min_order_amount: 0, rules: {}, priority: 0, is_stackable: 0, usage_limit: null, starts_at: '', expires_at: '', is_active: 1 }
  error.value = ''
  showForm.value = true
}

function openEdit(p: Promotion) {
  editing.value = p
  form.value = {
    title: p.title,
    description: p.description || '',
    promotion_type: p.promotion_type,
    discount_type: p.discount_type,
    discount_value: p.discount_value,
    max_discount_amount: p.max_discount_amount,
    min_order_amount: p.min_order_amount,
    rules: p.rules || {},
    priority: p.priority,
    is_stackable: p.is_stackable,
    usage_limit: p.usage_limit,
    starts_at: p.starts_at || '',
    expires_at: p.expires_at || '',
    is_active: p.is_active,
  }
  error.value = ''
  showForm.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await offersApi.updatePromotion(editing.value.uuid, form.value)
    } else {
      await offersApi.createPromotion(form.value)
    }
    showForm.value = false
    await loadPromotions()
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
    await offersApi.deletePromotion(deleteConfirm.value.uuid)
    deleteConfirm.value = null
    await loadPromotions()
  } catch (e) {
    console.error(e)
  } finally {
    deleting.value = false
  }
}

function formatMoney(paise: number) { return '₹' + (paise / 100).toFixed(2) }

function typeLabel(t: string) {
  const labels: Record<string, string> = {
    order_discount: 'Order Discount',
    category_discount: 'Category Discount',
    buy_x_get_y: 'Buy X Get Y',
    free_delivery: 'Free Delivery',
    flash_sale: 'Flash Sale',
  }
  return labels[t] || t
}

function discountLabel(p: Promotion) {
  if (p.promotion_type === 'free_delivery') return 'Free Delivery'
  if (p.discount_type === 'percentage') return (p.discount_value / 100).toFixed(0) + '% Off'
  return formatMoney(p.discount_value) + ' Off'
}

onMounted(loadPromotions)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Promotions</h1>
        <p class="text-sm text-gray-500 mt-1">Auto-applied discounts and offers for your store</p>
      </div>
      <button @click="openCreate" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition font-medium">
        + New Promotion
      </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="loading" class="p-12 text-center text-gray-400">Loading...</div>

      <table v-else-if="promotions.length" class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-left">Title</th>
            <th class="px-4 py-3 text-left">Type</th>
            <th class="px-4 py-3 text-left">Discount</th>
            <th class="px-4 py-3 text-left">Min Order</th>
            <th class="px-4 py-3 text-center">Priority</th>
            <th class="px-4 py-3 text-center">Stackable</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in promotions" :key="p.uuid" class="border-t border-gray-50 hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ p.title }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs">{{ typeLabel(p.promotion_type) }}</span>
            </td>
            <td class="px-4 py-3 font-semibold">{{ discountLabel(p) }}</td>
            <td class="px-4 py-3">{{ p.min_order_amount > 0 ? formatMoney(p.min_order_amount) : '-' }}</td>
            <td class="px-4 py-3 text-center">{{ p.priority }}</td>
            <td class="px-4 py-3 text-center">{{ p.is_stackable ? 'Yes' : 'No' }}</td>
            <td class="px-4 py-3 text-center">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                {{ p.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right space-x-2">
              <button @click="openEdit(p)" class="text-blue-600 hover:underline text-xs">Edit</button>
              <button @click="deleteConfirm = p" class="text-red-500 hover:underline text-xs">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else-if="error && !loading" class="p-6 text-center">
        <div class="bg-red-50 text-red-600 p-4 rounded-lg text-sm font-mono">{{ error }}</div>
      </div>
      <div v-else class="p-12 text-center text-gray-400">No promotions yet. Create your first auto-applied offer!</div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showForm = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b"><h2 class="text-lg font-bold">{{ editing ? 'Edit Promotion' : 'Create Promotion' }}</h2></div>
        <form @submit.prevent="save" class="p-6 space-y-4">
          <div v-if="error" class="bg-red-50 text-red-600 p-3 rounded-lg text-sm">{{ error }}</div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input v-model="form.title" required class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Promotion Type</label>
              <select v-model="form.promotion_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="order_discount">Order Discount</option>
                <option value="category_discount">Category Discount</option>
                <option value="buy_x_get_y">Buy X Get Y</option>
                <option value="free_delivery">Free Delivery</option>
                <option value="flash_sale">Flash Sale</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type</label>
              <select v-model="form.discount_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="percentage">Percentage</option>
                <option value="fixed">Fixed Amount</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value</label>
              <input v-model.number="form.discount_value" type="number" min="0" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Min Order (paise)</label>
              <input v-model.number="form.min_order_amount" type="number" min="0" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
              <input v-model.number="form.priority" type="number" min="0" class="w-full border rounded-lg px-3 py-2 text-sm" />
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

          <div class="flex items-center gap-6">
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" v-model.number="form.is_stackable" :true-value="1" :false-value="0" class="rounded" />
              Stackable (combine with coupons)
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" v-model.number="form.is_active" :true-value="1" :false-value="0" class="rounded" />
              Active
            </label>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <button type="button" @click="showForm = false" class="px-4 py-2 text-gray-600 text-sm">Cancel</button>
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
        <h3 class="font-bold text-lg mb-2">Delete Promotion</h3>
        <p class="text-gray-600 text-sm mb-4">Delete <strong>{{ deleteConfirm.title }}</strong>? This cannot be undone.</p>
        <div class="flex justify-end gap-3">
          <button @click="deleteConfirm = null" class="px-4 py-2 text-gray-600 text-sm">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
