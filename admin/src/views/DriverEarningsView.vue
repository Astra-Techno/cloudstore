<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/api/client'

interface Driver {
  uuid: string
  name: string
  phone: string
  availability: string
  vehicle_type: string | null
  vehicle_number: string | null
  total_deliveries: number
  total_earnings: number
}

const drivers = ref<Driver[]>([])
const loading = ref(true)

function formatPrice(paise: number): string {
  return '₹' + (paise / 100).toFixed(2)
}

function availabilityColor(status: string): string {
  if (status === 'available') return 'bg-green-100 text-green-800'
  if (status === 'busy') return 'bg-yellow-100 text-yellow-800'
  return 'bg-gray-100 text-gray-800'
}

async function loadDrivers() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/admin/drivers')
    if (data.success && data.data) {
      drivers.value = data.data as Driver[]
    }
  } catch (e) {
    console.error('Failed to load drivers', e)
  } finally {
    loading.value = false
  }
}

onMounted(loadDrivers)
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Driver Earnings & Management</h1>

    <div v-if="loading" class="text-center py-12 text-gray-500">Loading...</div>
    <div v-else-if="!drivers.length" class="text-center py-12 text-gray-400">No drivers found</div>
    <div v-else class="space-y-3">
      <div v-for="driver in drivers" :key="driver.uuid" class="bg-white border rounded-xl p-5">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold">
              {{ driver.name[0]?.toUpperCase() }}
            </div>
            <div>
              <div class="font-semibold">{{ driver.name }}</div>
              <div class="text-xs text-gray-500">{{ driver.phone }}</div>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium" :class="availabilityColor(driver.availability)">
              {{ driver.availability }}
            </span>
          </div>
          <div class="text-right">
            <div v-if="driver.vehicle_type" class="text-xs text-gray-400">{{ driver.vehicle_type }} {{ driver.vehicle_number ?? '' }}</div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t">
          <div>
            <div class="text-xs text-gray-500">Total Deliveries</div>
            <div class="text-lg font-bold">{{ driver.total_deliveries ?? 0 }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Total Earnings</div>
            <div class="text-lg font-bold text-green-600">{{ formatPrice(driver.total_earnings ?? 0) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
