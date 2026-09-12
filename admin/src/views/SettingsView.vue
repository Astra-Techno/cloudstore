<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { settingsApi } from '@/api/settings'
import apiClient from '@/api/client'
import type { StoreSettings, ApiResponse } from '@/types'

const settings = ref<StoreSettings | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const activeTab = ref('store')

const settingsTabs = [
  { id: 'store', label: 'Store profile' },
  { id: 'branding', label: 'Branding' },
  { id: 'orders', label: 'Order rules' },
  { id: 'fulfilment', label: 'Fulfilment' },
  { id: 'payments', label: 'Payments' },
  { id: 'hours', label: 'Business hours' },
  { id: 'security', label: 'Security' },
]

const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']

const paymentOptions = [
  { value: 'cod', label: 'Cash on Delivery' },
]

const defaultBranding = { primary_color: '#000000', logo_url: '' }

// Password change
const passwordForm = ref({ current_password: '', new_password: '', confirm_password: '' })
const passwordSaving = ref(false)
const passwordError = ref('')
const passwordSuccess = ref('')
const togglingLive = ref(false)

async function toggleStoreLive() {
  if (!settings.value) return
  togglingLive.value = true
  error.value = ''
  try {
    const goLive = settings.value.store.status !== 'active'
    const { data } = await settingsApi.toggleLive(goLive)
    if (data.success && data.data) {
      const result = data.data as { status: string; live: boolean }
      settings.value.store.status = result.status
      success.value = goLive ? 'Store is now LIVE' : 'Store is now offline (draft)'
      setTimeout(() => success.value = '', 3000)
    } else {
      error.value = data.error?.message || 'Failed to toggle store status'
    }
  } catch (e) {
    error.value = 'Failed to toggle store status'
  } finally {
    togglingLive.value = false
  }
}

async function changePassword() {
  passwordError.value = ''
  passwordSuccess.value = ''
  if (passwordForm.value.new_password !== passwordForm.value.confirm_password) {
    passwordError.value = 'Passwords do not match'
    return
  }
  if (passwordForm.value.new_password.length < 8) {
    passwordError.value = 'Password must be at least 8 characters'
    return
  }
  passwordSaving.value = true
  try {
    const { data } = await apiClient.post<ApiResponse>('/admin/change-password', {
      current_password: passwordForm.value.current_password,
      new_password: passwordForm.value.new_password,
    })
    if (data.success) {
      passwordSuccess.value = 'Password changed successfully'
      passwordForm.value = { current_password: '', new_password: '', confirm_password: '' }
      setTimeout(() => passwordSuccess.value = '', 3000)
    } else {
      passwordError.value = data.error?.message || 'Failed to change password'
      if (data.error?.fields?.current_password) passwordError.value = data.error.fields.current_password[0]
    }
  } catch (e) {
    passwordError.value = 'Failed to change password'
  } finally {
    passwordSaving.value = false
  }
}

async function loadSettings() {
  loading.value = true
  try {
    const { data } = await settingsApi.getSettings()
    if (data.success && data.data) {
      settings.value = {
        ...data.data,
        // API values are paise; the merchant-facing form always uses rupees.
        min_order_amount: Number(data.data.min_order_amount || 0) / 100,
        delivery_charge_fixed: Number(data.data.delivery_charge_fixed || 0) / 100,
        payment_methods: ['cod'],
        branding: data.data.branding ?? { ...defaultBranding },
      }
    }
  } catch (e) {
    error.value = 'Failed to load settings'
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  if (!settings.value) return
  saving.value = true
  error.value = ''
  success.value = ''

  // Validate business hours and merchant ordering choices.
  if (!settings.value.delivery_enabled && !settings.value.pickup_enabled) {
    error.value = 'Enable delivery, pickup, or both so customers can place orders.'
    saving.value = false
    return
  }
  for (const day of days) {
    const h = settings.value.business_hours[day]
    if (h.open && h.start && h.end && h.end <= h.start) {
      error.value = `${formatDay(day)}: closing time must be after opening time`
      saving.value = false
      return
    }
  }

  try {
    const { data } = await settingsApi.updateSettings({
      business_hours: settings.value.business_hours,
      preparation_time_default: settings.value.preparation_time_default,
      min_order_amount: Math.round(Number(settings.value.min_order_amount || 0) * 100),
      tax_rate: settings.value.tax_rate,
      delivery_charge_fixed: Math.round(Number(settings.value.delivery_charge_fixed || 0) * 100),
      service_charge_percent: settings.value.service_charge_percent,
      delivery_enabled: settings.value.delivery_enabled,
      pickup_enabled: settings.value.pickup_enabled,
      payment_methods: settings.value.payment_methods,
      branding: settings.value.branding,
    })
    if (data.success) {
      success.value = 'Settings saved successfully'
      setTimeout(() => success.value = '', 3000)
    } else {
      error.value = data.error?.message || 'Failed to save'
    }
  } catch (e) {
    error.value = 'An error occurred'
  } finally {
    saving.value = false
  }
}

function togglePayment(method: string) {
  if (!settings.value) return
  if (method === 'cod') return
  const idx = settings.value.payment_methods.indexOf(method)
  if (idx >= 0) {
    if (settings.value.payment_methods.length > 1) {
      settings.value.payment_methods.splice(idx, 1)
    }
  } else {
    settings.value.payment_methods.push(method)
  }
}

function formatDay(day: string): string {
  return day.charAt(0).toUpperCase() + day.slice(1)
}

onMounted(loadSettings)
</script>

<template>
  <div class="list-page">
    <section class="list-page-intro">
      <div>
        <p class="list-kicker">Configuration</p>
        <h1>Store Settings <span>/ your store, your rules</span></h1>
        <p>Configure business hours, payment methods, and delivery options.</p>
      </div>
      <button @click="saveSettings" :disabled="saving" class="list-primary-action">
        <span>{{ saving ? '...' : '✓' }}</span> {{ saving ? 'Saving' : 'Save Settings' }}
      </button>
    </section>

    <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ error }}</div>
    <div v-if="success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ success }}</div>

    <div v-if="loading" class="list-loading">Loading settings<span></span></div>

    <template v-else-if="settings">
      <div class="settings-tabs" role="tablist" aria-label="Store settings sections">
        <button
          v-for="tab in settingsTabs"
          :key="tab.id"
          type="button"
          class="settings-tab"
          :class="{ 'settings-tab--active': activeTab === tab.id }"
          :aria-selected="activeTab === tab.id"
          role="tab"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="settings-tab-content">
        <!-- Store Info -->
        <div v-show="activeTab === 'store'" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Store Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Store Name</label>
              <input :value="settings.store.name" disabled class="w-full border border-gray-200 bg-gray-50 rounded-md px-3 py-2 text-gray-500" />
              <p class="text-xs text-gray-400 mt-1">Contact support to change store name</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Store Status</label>
              <div class="flex items-center gap-3">
                <span class="inline-block px-3 py-1 rounded-full text-sm font-medium" :class="settings.store.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'">{{ settings.store.status === 'active' ? 'LIVE' : 'Offline' }}</span>
                <button
                  @click="toggleStoreLive"
                  :disabled="togglingLive || settings.store.status === 'suspended' || settings.store.status === 'archived'"
                  class="px-4 py-1.5 text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
                  :class="settings.store.status === 'active' ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200'"
                >{{ togglingLive ? 'Updating...' : (settings.store.status === 'active' ? 'Go Offline' : 'Go Live') }}</button>
              </div>
              <p v-if="settings.store.status === 'suspended' || settings.store.status === 'archived'" class="text-xs text-red-500 mt-1">Store is {{ settings.store.status }}. Contact support to change.</p>
            </div>
          </div>
        </div>

        <!-- Branding -->
        <div v-if="settings.branding" v-show="activeTab === 'branding'" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Branding</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Primary Color</label>
              <div class="flex gap-2">
                <input v-model="settings.branding.primary_color" type="color" class="w-10 h-10 rounded border border-gray-300 cursor-pointer" />
                <input v-model="settings.branding.primary_color" type="text" class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Logo URL</label>
              <input v-model="settings.branding.logo_url" type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="https://..." />
            </div>
          </div>
        </div>

        <!-- Order Settings -->
        <div v-show="activeTab === 'orders'" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Order & Pricing</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Default Prep Time (min)</label>
              <input v-model.number="settings.preparation_time_default" type="number" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Min Order Amount (₹)</label>
              <input v-model.number="settings.min_order_amount" type="number" min="0" step="0.01" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tax Rate (%)</label>
              <input v-model.number="settings.tax_rate" type="number" min="0" max="100" step="0.01" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-100">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Charge (₹)</label>
              <input v-model.number="settings.delivery_charge_fixed" type="number" min="0" step="1" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
              <p class="text-xs text-gray-400 mt-1">Flat delivery fee in rupees. Applied when no delivery zone matches.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Service Charge (%)</label>
              <input v-model.number="settings.service_charge_percent" type="number" min="0" max="100" step="0.5" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
              <p class="text-xs text-gray-400 mt-1">Percentage of subtotal added as service charge (e.g. 5 = 5%).</p>
            </div>
          </div>
        </div>

        <!-- Fulfillment -->
        <div v-show="activeTab === 'fulfilment'" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Fulfillment Options</h2>
          <div class="flex gap-6">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="settings.delivery_enabled" class="w-4 h-4 text-red-600 rounded" />
              <span class="text-sm font-medium text-gray-700">Delivery Enabled</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="settings.pickup_enabled" class="w-4 h-4 text-red-600 rounded" />
              <span class="text-sm font-medium text-gray-700">Pickup Enabled</span>
            </label>
          </div>
        </div>

        <!-- Payment Methods -->
        <div v-show="activeTab === 'payments'" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Methods</h2>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <button v-for="opt in paymentOptions" :key="opt.value" @click="togglePayment(opt.value)"
              class="p-3 border-2 rounded-lg text-sm font-medium text-left transition-colors"
              :class="settings.payment_methods.includes(opt.value) ? 'border-red-500 bg-red-50 text-red-700' : 'border-gray-200 text-gray-500 hover:border-gray-300'"
            >
              {{ opt.label }}
            </button>
          </div>
          <p class="mt-3 text-xs text-gray-500">Cash on delivery is active. Online, UPI, and card payments will appear only after a payment gateway is connected for this store.</p>
        </div>

        <!-- Password Change -->
        <div v-show="activeTab === 'security'" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Change Password</h2>
          <div v-if="passwordError" class="mb-3 p-2 bg-red-50 border border-red-200 text-red-700 rounded text-sm">{{ passwordError }}</div>
          <div v-if="passwordSuccess" class="mb-3 p-2 bg-green-50 border border-green-200 text-green-700 rounded text-sm">{{ passwordSuccess }}</div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
              <input v-model="passwordForm.current_password" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
              <input v-model="passwordForm.new_password" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
              <input v-model="passwordForm.confirm_password" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
          </div>
          <div class="mt-4">
            <button @click="changePassword" :disabled="passwordSaving" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
              {{ passwordSaving ? 'Changing...' : 'Change Password' }}
            </button>
          </div>
        </div>

        <!-- Business Hours -->
        <div v-show="activeTab === 'hours'" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Business Hours</h2>
          <div class="space-y-3">
            <div v-for="day in days" :key="day" class="flex items-center gap-4 py-2 border-b border-gray-50 last:border-0">
              <label class="w-28 text-sm font-medium text-gray-700">{{ formatDay(day) }}</label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="settings.business_hours[day].open" class="w-4 h-4 text-red-600 rounded" />
                <span class="text-xs text-gray-500">Open</span>
              </label>
              <template v-if="settings.business_hours[day].open">
                <input v-model="settings.business_hours[day].start" type="time" class="border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                <span class="text-gray-400 text-sm">to</span>
                <input v-model="settings.business_hours[day].end" type="time" class="border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
              </template>
              <span v-else class="text-sm text-gray-400">Closed</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
