<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { settingsApi } from '@/api/settings'
import type { DeliveryZone } from '@/types'

const zones = ref<DeliveryZone[]>([])
const loading = ref(true)
const showForm = ref(false)
const editing = ref<DeliveryZone | null>(null)
const saving = ref(false)
const error = ref('')

const form = ref({
  name: '', min_distance_km: 0, max_distance_km: 5, fee: 0, min_order_free_delivery: 0, status: 'active', sort_order: 0,
})

function formatPrice(paise: number): string {
  return '₹' + (paise / 100).toFixed(2)
}

async function loadZones() {
  loading.value = true
  try {
    const { data } = await settingsApi.listZones()
    if (data.success) zones.value = data.data || []
  } catch (e) {
    console.error('Failed to load zones', e)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { name: '', min_distance_km: 0, max_distance_km: 5, fee: 0, min_order_free_delivery: 0, status: 'active', sort_order: 0 }
  error.value = ''
  showForm.value = true
}

function openEdit(zone: DeliveryZone) {
  editing.value = zone
  form.value = {
    name: zone.name,
    min_distance_km: zone.min_distance_km,
    max_distance_km: zone.max_distance_km,
    fee: zone.fee / 100,
    min_order_free_delivery: (zone.min_order_free_delivery || 0) / 100,
    status: zone.status,
    sort_order: zone.sort_order,
  }
  error.value = ''
  showForm.value = true
}

async function saveZone() {
  saving.value = true
  error.value = ''

  try {
    const payload = {
      name: form.value.name,
      min_distance_km: form.value.min_distance_km,
      max_distance_km: form.value.max_distance_km,
      fee: Math.round(form.value.fee * 100),
      min_order_free_delivery: form.value.min_order_free_delivery > 0 ? Math.round(form.value.min_order_free_delivery * 100) : null,
      status: form.value.status,
      sort_order: form.value.sort_order,
    }

    if (editing.value) {
      const { data } = await settingsApi.updateZone(editing.value.id, payload)
      if (!data.success) { error.value = data.error?.message || 'Failed'; return }
    } else {
      const { data } = await settingsApi.createZone(payload)
      if (!data.success) { error.value = data.error?.message || 'Failed'; return }
    }
    showForm.value = false
    await loadZones()
  } catch (e) {
    error.value = 'An error occurred'
  } finally {
    saving.value = false
  }
}

async function deleteZone(zone: DeliveryZone) {
  if (!confirm(`Delete zone "${zone.name}"?`)) return
  try {
    await settingsApi.deleteZone(zone.id)
    await loadZones()
  } catch (e) {
    error.value = 'Failed to delete zone'
  }
}

onMounted(loadZones)
</script>

<template>
  <div class="list-page">
    <section class="list-page-intro">
      <div>
        <p class="list-kicker">Logistics</p>
        <h1>Delivery Zones <span>/ coverage & pricing</span></h1>
        <p>Define delivery areas and set distance-based fees.</p>
      </div>
      <button @click="openCreate" class="list-primary-action"><span>＋</span> Add zone</button>
    </section>

    <section class="list-stat-rail">
      <div><span>Zones</span><strong>{{ zones.length }}</strong><small>delivery areas</small></div>
      <div><span>Active</span><strong>{{ zones.filter(z => z.status === 'active').length }}</strong><small>serving customers</small></div>
    </section>

    <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ error }}</div>

    <div v-if="loading" class="list-loading">Loading delivery zones<span></span></div>

    <div v-else class="list-table-card">
      <table class="list-table">
        <thead><tr>
          <th>Zone Name</th><th>Distance Range</th><th class="text-right">Fee</th><th class="text-right">Free Above</th><th>Status</th><th>Order</th><th></th>
        </tr></thead>
        <tbody>
          <tr v-for="zone in zones" :key="zone.id" class="list-row">
            <td class="font-medium">{{ zone.name }}</td>
            <td class="list-muted">{{ zone.min_distance_km }} — {{ zone.max_distance_km }} km</td>
            <td class="list-total">{{ formatPrice(zone.fee) }}</td>
            <td class="text-right list-muted">{{ zone.min_order_free_delivery ? formatPrice(zone.min_order_free_delivery) : '—' }}</td>
            <td><span class="list-status" :class="zone.status === 'active' ? 'list-status--lime' : 'list-status--coral'"><i></i>{{ zone.status }}</span></td>
            <td class="list-muted">{{ zone.sort_order }}</td>
            <td class="list-action-cell">
              <button @click="openEdit(zone)" class="text-blue-600 text-sm mr-2 hover:underline">Edit</button>
              <button @click="deleteZone(zone)" class="text-red-600 text-sm hover:underline">Delete</button>
            </td>
          </tr>
          <tr v-if="zones.length === 0"><td colspan="7" class="list-empty">No delivery zones configured.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ editing ? 'Edit Zone' : 'New Delivery Zone' }}</h2>
        <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ error }}</div>
        <form @submit.prevent="saveZone">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Zone Name</label>
            <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. Nearby, City Wide" />
          </div>
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Min Distance (km)</label>
              <input v-model.number="form.min_distance_km" type="number" step="0.1" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Max Distance (km)</label>
              <input v-model.number="form.max_distance_km" type="number" step="0.1" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Fee (₹)</label>
              <input v-model.number="form.fee" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Free Delivery Above (₹)</label>
              <input v-model.number="form.min_order_free_delivery" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <p class="text-xs text-gray-400 mt-1">0 = no free delivery</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select v-model="form.status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>
          <div class="flex justify-end gap-3">
            <button type="button" @click="showForm = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">{{ saving ? 'Saving...' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
