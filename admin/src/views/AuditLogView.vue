<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { analyticsApi } from '@/api/analytics'

interface AuditEntry {
  id: number
  admin_name: string | null
  action: string
  entity_type: string
  entity_id: number | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address: string | null
  created_at: string
}

const logs = ref<AuditEntry[]>([])
const loading = ref(true)
const offset = ref(0)
const limit = 50
const hasMore = ref(true)
const expandedId = ref<number | null>(null)

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatAction(action: string): string {
  return action.replace(/_/g, ' ')
}

function toggleExpand(id: number) {
  expandedId.value = expandedId.value === id ? null : id
}

async function loadLogs(append = false) {
  loading.value = true
  try {
    const { data } = await analyticsApi.getAuditLog(limit, offset.value)
    if (data.success && data.data) {
      const entries = data.data as AuditEntry[]
      if (append) {
        logs.value = [...logs.value, ...entries]
      } else {
        logs.value = entries
      }
      hasMore.value = entries.length === limit
    }
  } catch (e) {
    console.error('Failed to load audit log', e)
  } finally {
    loading.value = false
  }
}

function loadMore() {
  offset.value += limit
  loadLogs(true)
}

onMounted(() => loadLogs())
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Audit Log</h1>

    <div v-if="loading && !logs.length" class="text-center py-12 text-gray-500">Loading...</div>
    <div v-else-if="!logs.length" class="text-center py-12 text-gray-400">No audit log entries</div>
    <div v-else class="space-y-2">
      <div
        v-for="log in logs"
        :key="log.id"
        class="bg-white border rounded-lg p-4 cursor-pointer hover:bg-gray-50 transition-colors"
        @click="toggleExpand(log.id)"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
              :class="{
                'bg-blue-100 text-blue-800': log.action.includes('create'),
                'bg-yellow-100 text-yellow-800': log.action.includes('update'),
                'bg-red-100 text-red-800': log.action.includes('delete'),
                'bg-gray-100 text-gray-800': !log.action.includes('create') && !log.action.includes('update') && !log.action.includes('delete'),
              }"
            >
              {{ formatAction(log.action) }}
            </span>
            <span class="text-sm font-medium">{{ log.entity_type }}</span>
            <span v-if="log.entity_id" class="text-xs text-gray-400">#{{ log.entity_id }}</span>
          </div>
          <div class="flex items-center gap-3 text-sm text-gray-500">
            <span v-if="log.admin_name">{{ log.admin_name }}</span>
            <span>{{ formatDate(log.created_at) }}</span>
          </div>
        </div>
        <div v-if="expandedId === log.id" class="mt-3 pt-3 border-t">
          <div class="grid grid-cols-2 gap-4 text-xs">
            <div v-if="log.old_values">
              <div class="font-semibold text-gray-600 mb-1">Previous Values</div>
              <pre class="bg-red-50 p-2 rounded text-red-800 overflow-auto max-h-40">{{ JSON.stringify(log.old_values, null, 2) }}</pre>
            </div>
            <div v-if="log.new_values">
              <div class="font-semibold text-gray-600 mb-1">New Values</div>
              <pre class="bg-green-50 p-2 rounded text-green-800 overflow-auto max-h-40">{{ JSON.stringify(log.new_values, null, 2) }}</pre>
            </div>
          </div>
          <div v-if="log.ip_address" class="text-xs text-gray-400 mt-2">IP: {{ log.ip_address }}</div>
        </div>
      </div>
      <div v-if="hasMore" class="text-center pt-4">
        <button @click="loadMore" :disabled="loading" class="text-indigo-600 font-medium text-sm hover:text-indigo-800">
          {{ loading ? 'Loading...' : 'Load more' }}
        </button>
      </div>
    </div>
  </div>
</template>
