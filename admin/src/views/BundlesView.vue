<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { offersApi } from '@/api/offers'

interface BundleItem {
  id: number
  product_name: string
  product_uuid: string
  variant_name: string | null
  quantity: number
  base_price: number
  sale_price: number | null
}

interface Bundle {
  uuid: string
  name: string
  description: string
  bundle_price: number
  original_price: number
  image_url: string | null
  is_active: number
  starts_at: string | null
  expires_at: string | null
  items: BundleItem[]
}

const bundles = ref<Bundle[]>([])
const loading = ref(true)
const showForm = ref(false)
const editing = ref<Bundle | null>(null)
const saving = ref(false)
const error = ref('')
const deleteConfirm = ref<Bundle | null>(null)
const deleting = ref(false)

const form = ref({
  name: '',
  description: '',
  bundle_price: 0,
  original_price: 0,
  image_url: '',
  is_active: 1,
  starts_at: '',
  expires_at: '',
})

async function loadBundles() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await offersApi.listBundles()
    if (data.success) bundles.value = data.data || []
    else error.value = 'API returned: ' + JSON.stringify(data)
  } catch (e: any) {
    error.value = 'Load failed: ' + (e.response?.status || '') + ' ' + (e.response?.data?.error?.message || e.message || JSON.stringify(e))
    console.error('Failed to load bundles', e)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { name: '', description: '', bundle_price: 0, original_price: 0, image_url: '', is_active: 1, starts_at: '', expires_at: '' }
  error.value = ''
  showForm.value = true
}

function openEdit(b: Bundle) {
  editing.value = b
  form.value = {
    name: b.name,
    description: b.description || '',
    bundle_price: b.bundle_price,
    original_price: b.original_price,
    image_url: b.image_url || '',
    is_active: b.is_active,
    starts_at: b.starts_at || '',
    expires_at: b.expires_at || '',
  }
  error.value = ''
  showForm.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await offersApi.updateBundle(editing.value.uuid, form.value)
    } else {
      await offersApi.createBundle(form.value)
    }
    showForm.value = false
    await loadBundles()
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
    await offersApi.deleteBundle(deleteConfirm.value.uuid)
    deleteConfirm.value = null
    await loadBundles()
  } catch (e) {
    console.error(e)
  } finally {
    deleting.value = false
  }
}

async function removeItem(bundle: Bundle, itemId: number) {
  try {
    await offersApi.removeBundleItem(bundle.uuid, itemId)
    await loadBundles()
  } catch (e) {
    console.error(e)
  }
}

function formatMoney(paise: number) { return '₹' + (paise / 100).toFixed(2) }

function savings(b: Bundle) {
  if (b.original_price <= 0) return 0
  return Math.round((1 - b.bundle_price / b.original_price) * 100)
}

onMounted(loadBundles)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Bundles</h1>
        <p class="text-sm text-gray-500 mt-1">Create combo deals with special pricing</p>
      </div>
      <button @click="openCreate" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition font-medium">
        + New Bundle
      </button>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-12">Loading...</div>

    <div v-else-if="bundles.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="b in bundles" :key="b.uuid" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="font-bold text-gray-900">{{ b.name }}</h3>
              <p class="text-sm text-gray-500 mt-1">{{ b.description }}</p>
            </div>
            <span v-if="savings(b) > 0" class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs font-bold">
              Save {{ savings(b) }}%
            </span>
          </div>

          <div class="mt-4 flex items-baseline gap-2">
            <span class="text-2xl font-bold text-red-600">{{ formatMoney(b.bundle_price) }}</span>
            <span v-if="b.original_price > b.bundle_price" class="text-sm text-gray-400 line-through">{{ formatMoney(b.original_price) }}</span>
          </div>

          <div v-if="b.items && b.items.length" class="mt-4 space-y-2">
            <p class="text-xs text-gray-500 font-medium uppercase">Includes:</p>
            <div v-for="item in b.items" :key="item.id" class="flex items-center justify-between text-sm bg-gray-50 rounded-lg px-3 py-2">
              <span>{{ item.product_name }}{{ item.variant_name ? ' - ' + item.variant_name : '' }} x{{ item.quantity }}</span>
              <button @click="removeItem(b, item.id)" class="text-red-400 hover:text-red-600 text-xs">Remove</button>
            </div>
          </div>
          <div v-else class="mt-4 text-sm text-gray-400 italic">No items added yet</div>

          <div class="mt-4 flex items-center justify-between">
            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="b.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
              {{ b.is_active ? 'Active' : 'Inactive' }}
            </span>
            <div class="space-x-2">
              <button @click="openEdit(b)" class="text-blue-600 hover:underline text-xs">Edit</button>
              <button @click="deleteConfirm = b" class="text-red-500 hover:underline text-xs">Delete</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="error && !loading" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
      <div class="bg-red-50 text-red-600 p-4 rounded-lg text-sm font-mono">{{ error }}</div>
    </div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
      No bundles yet. Create your first combo deal!
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showForm = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
        <div class="p-6 border-b"><h2 class="text-lg font-bold">{{ editing ? 'Edit Bundle' : 'Create Bundle' }}</h2></div>
        <form @submit.prevent="save" class="p-6 space-y-4">
          <div v-if="error" class="bg-red-50 text-red-600 p-3 rounded-lg text-sm">{{ error }}</div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input v-model="form.name" required class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="e.g. Family Combo" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Bundle Price (paise)</label>
              <input v-model.number="form.bundle_price" type="number" min="0" required class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Original Price (paise)</label>
              <input v-model.number="form.original_price" type="number" min="0" class="w-full border rounded-lg px-3 py-2 text-sm" />
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

          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" v-model.number="form.is_active" :true-value="1" :false-value="0" class="rounded" />
            Active
          </label>

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
        <h3 class="font-bold text-lg mb-2">Delete Bundle</h3>
        <p class="text-gray-600 text-sm mb-4">Delete <strong>{{ deleteConfirm.name }}</strong>? This cannot be undone.</p>
        <div class="flex justify-end gap-3">
          <button @click="deleteConfirm = null" class="px-4 py-2 text-gray-600 text-sm">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
