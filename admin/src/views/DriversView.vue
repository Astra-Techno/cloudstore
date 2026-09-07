<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import apiClient from '@/api/client'
import type { ApiResponse, PaginatedMeta } from '@/types'

interface Driver {
  id: number
  uuid: string
  name: string
  phone: string
  email: string | null
  vehicle_type: string | null
  vehicle_number: string | null
  status: string
  availability: string
  created_at: string
}

const drivers = ref<Driver[]>([])
const meta = ref<PaginatedMeta | null>(null)
const loading = ref(true)
const showForm = ref(false)
const editing = ref<Driver | null>(null)
const saving = ref(false)
const error = ref('')
const searchQuery = ref('')
const deleteConfirm = ref<Driver | null>(null)
const deleting = ref(false)

const form = ref({
  name: '',
  phone: '',
  email: '',
  password: '',
  vehicle_type: 'bike',
  vehicle_number: '',
  status: 'active',
})

async function loadDrivers(page = 1) {
  loading.value = true
  try {
    const { data } = await apiClient.get<ApiResponse<Driver[]>>('/admin/drivers', { params: { page } })
    if (data.success) {
      drivers.value = data.data || []
      meta.value = data.meta || null
    }
  } catch (e) {
    console.error('Failed to load drivers', e)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { name: '', phone: '', email: '', password: '', vehicle_type: 'bike', vehicle_number: '', status: 'active' }
  error.value = ''
  showForm.value = true
}

function openEdit(d: Driver) {
  editing.value = d
  form.value = {
    name: d.name,
    phone: d.phone,
    email: d.email || '',
    password: '',
    vehicle_type: d.vehicle_type || 'bike',
    vehicle_number: d.vehicle_number || '',
    status: d.status,
  }
  error.value = ''
  showForm.value = true
}

async function saveDriver() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      const payload: Record<string, unknown> = {
        name: form.value.name,
        phone: form.value.phone,
        email: form.value.email || null,
        vehicle_type: form.value.vehicle_type,
        vehicle_number: form.value.vehicle_number || null,
        status: form.value.status,
      }
      if (form.value.password) payload.password = form.value.password
      const { data } = await apiClient.put<ApiResponse>(`/admin/drivers/${editing.value.uuid}`, payload)
      if (!data.success) { error.value = data.error?.message || 'Failed to update'; return }
    } else {
      if (!form.value.password) { error.value = 'Password is required for new drivers'; return }
      const { data } = await apiClient.post<ApiResponse>('/admin/drivers', {
        name: form.value.name,
        phone: form.value.phone,
        email: form.value.email || null,
        password: form.value.password,
        vehicle_type: form.value.vehicle_type,
        vehicle_number: form.value.vehicle_number || null,
        status: form.value.status,
      })
      if (!data.success) { error.value = data.error?.message || 'Failed to create'; return }
    }
    showForm.value = false
    await loadDrivers()
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'An error occurred'
  } finally {
    saving.value = false
  }
}

async function deleteDriver() {
  if (!deleteConfirm.value) return
  deleting.value = true
  try {
    const { data } = await apiClient.delete<ApiResponse>(`/admin/drivers/${deleteConfirm.value.uuid}`)
    if (!data.success) { error.value = data.error?.message || 'Failed to delete'; return }
    deleteConfirm.value = null
    await loadDrivers()
  } catch (e) {
    error.value = 'Failed to delete driver'
  } finally {
    deleting.value = false
  }
}

function matchesSearch(d: Driver): boolean {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return true
  return [d.name, d.phone, d.vehicle_number].some(v => v?.toLowerCase().includes(q))
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(loadDrivers)
</script>

<template>
  <div class="list-page">
    <section class="list-page-intro">
      <div>
        <p class="list-kicker">Fleet management</p>
        <h1>Drivers <span>/ your delivery team</span></h1>
        <p>Manage delivery drivers and their availability.</p>
      </div>
      <button @click="openCreate" class="list-primary-action"><span>+</span> Add Driver</button>
    </section>

    <section class="list-stat-rail">
      <div><span>Total</span><strong>{{ drivers.length }}</strong><small>registered drivers</small></div>
      <div><span>Available</span><strong>{{ drivers.filter(d => d.availability === 'available').length }}</strong><small>ready for delivery</small></div>
      <div><span>Active</span><strong>{{ drivers.filter(d => d.status === 'active').length }}</strong><small>active accounts</small></div>
    </section>

    <section class="list-toolbar">
      <label class="list-search"><span aria-hidden="true">&#x2315;</span><input v-model="searchQuery" type="search" placeholder="Search by name, phone or vehicle" /></label>
    </section>

    <div v-if="loading" class="list-loading">Loading drivers<span></span></div>

    <div v-else class="list-table-card">
      <table class="list-table">
        <thead><tr>
          <th>Driver</th><th>Phone</th><th>Vehicle</th><th>Status</th><th>Availability</th><th>Joined</th><th class="text-right">Actions</th>
        </tr></thead>
        <tbody>
          <tr v-for="d in drivers" v-show="matchesSearch(d)" :key="d.uuid" class="list-row">
            <td class="list-order-id">
              <span class="product-avatar">{{ d.name.charAt(0).toUpperCase() }}</span>
              <div><strong>{{ d.name }}</strong><small>{{ d.email || 'No email' }}</small></div>
            </td>
            <td class="list-muted">{{ d.phone }}</td>
            <td class="list-muted">{{ d.vehicle_type || '—' }} {{ d.vehicle_number || '' }}</td>
            <td><span class="list-status" :class="d.status === 'active' ? 'list-status--lime' : 'list-status--coral'"><i></i>{{ d.status }}</span></td>
            <td><span class="list-status" :class="d.availability === 'available' ? 'list-status--lime' : 'list-status--coral'"><i></i>{{ d.availability }}</span></td>
            <td class="list-muted text-sm">{{ formatDate(d.created_at) }}</td>
            <td class="list-action-cell">
              <button @click="openEdit(d)" class="list-edit-button">Edit <span>&rarr;</span></button>
              <button @click="deleteConfirm = d" class="list-edit-button" style="color:#ef4444;margin-left:8px">Delete</button>
            </td>
          </tr>
          <tr v-if="drivers.length === 0 || !drivers.some(matchesSearch)"><td colspan="7" class="list-empty">No drivers found.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="list-pagination">
      <button v-for="page in meta.last_page" :key="page" @click="loadDrivers(page)"
        class="list-page-button" :class="page === meta.current_page ? 'list-page-button--active' : ''">{{ page }}</button>
    </div>

    <!-- Delete Confirm -->
    <div v-if="deleteConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Delete Driver</h2>
        <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete <strong>{{ deleteConfirm.name }}</strong>?</p>
        <div class="flex justify-end gap-3">
          <button @click="deleteConfirm = null" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
          <button @click="deleteDriver" :disabled="deleting" class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ editing ? 'Edit Driver' : 'New Driver' }}</h2>
        <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ error }}</div>
        <form @submit.prevent="saveDriver">
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
              <input v-model="form.phone" type="tel" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input v-model="form.email" type="email" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Password {{ editing ? '(leave blank to keep)' : '' }}</label>
              <input v-model="form.password" type="password" :required="!editing" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
          </div>
          <div class="grid grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Type</label>
              <select v-model="form.vehicle_type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                <option value="bike">Bike</option>
                <option value="scooter">Scooter</option>
                <option value="car">Car</option>
                <option value="van">Van</option>
                <option value="bicycle">Bicycle</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Number</label>
              <input v-model="form.vehicle_number" type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="TN XX AB 1234" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select v-model="form.status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="suspended">Suspended</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-3">
            <button type="button" @click="showForm = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">{{ saving ? 'Saving...' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
