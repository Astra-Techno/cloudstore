<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { settingsApi } from '@/api/settings'
import { downloadCsv } from '@/utils/csv'
import type { Customer, PaginatedMeta } from '@/types'

const router = useRouter()
const customers = ref<Customer[]>([])
const meta = ref<PaginatedMeta | null>(null)
const loading = ref(true)
const searchQuery = ref('')
const searchTimeout = ref<ReturnType<typeof setTimeout> | null>(null)

function formatPrice(paise: number): string {
  return '₹' + (paise / 100).toFixed(2)
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return 'Never'
  return new Date(dateStr).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}

async function loadCustomers(page = 1) {
  loading.value = true
  try {
    const { data } = await settingsApi.listCustomers(page, searchQuery.value || undefined)
    if (data.success) {
      customers.value = data.data || []
      meta.value = data.meta || null
    }
  } catch (e) {
    console.error('Failed to load customers', e)
  } finally {
    loading.value = false
  }
}

function exportCsv() {
  const headers = ['Name', 'Phone', 'Email', 'Status', 'Orders', 'Total Spent (₹)', 'Last Login', 'Joined']
  const rows = customers.value.map(c => [
    c.name || '', c.phone || '', c.email || '', c.status,
    c.order_count ?? 0, ((c.total_spent ?? 0) / 100).toFixed(2),
    c.last_login_at || 'Never', c.created_at,
  ])
  downloadCsv('customers.csv', headers, rows)
}

watch(searchQuery, () => {
  if (searchTimeout.value) clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => loadCustomers(1), 400)
})

onMounted(loadCustomers)
</script>

<template>
  <div class="list-page">
    <section class="list-page-intro">
      <div>
        <p class="list-kicker">Relationships</p>
        <h1>Customers <span>/ people who trust you</span></h1>
        <p>See who's ordering and build lasting connections.</p>
      </div>
    </section>

    <section class="list-stat-rail">
      <div><span>Total</span><strong>{{ meta?.total ?? customers.length }}</strong><small>registered customers</small></div>
      <div><span>Active</span><strong>{{ customers.filter(c => c.status === 'active').length }}</strong><small>active accounts</small></div>
    </section>

    <section class="list-toolbar">
      <label class="list-search">
        <span aria-hidden="true">⌕</span>
        <input v-model="searchQuery" type="search" placeholder="Search by name, phone or email" />
      </label>
      <button @click="exportCsv" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">Export CSV</button>
    </section>

    <div v-if="loading" class="list-loading">Loading customers<span></span></div>

    <div v-else class="list-table-card">
      <table class="list-table">
        <thead><tr>
          <th>Customer</th><th>Phone</th><th>Email</th><th class="text-right">Orders</th><th class="text-right">Total Spent</th><th>Last Login</th><th>Joined</th>
        </tr></thead>
        <tbody>
          <tr v-for="c in customers" :key="c.uuid" class="list-row cursor-pointer" @click="router.push(`/customers/${c.uuid}`)">
            <td class="list-order-id">
              <span class="product-avatar">{{ (c.name || c.phone || '?').charAt(0).toUpperCase() }}</span>
              <div><strong>{{ c.name || 'No Name' }}</strong><small>{{ c.status }}</small></div>
            </td>
            <td class="list-muted">{{ c.phone || '—' }}</td>
            <td class="list-muted">{{ c.email || '—' }}</td>
            <td class="text-right font-medium">{{ c.order_count ?? 0 }}</td>
            <td class="list-total">{{ formatPrice(c.total_spent ?? 0) }}</td>
            <td class="list-muted text-sm">{{ formatDate(c.last_login_at) }}</td>
            <td class="list-muted text-sm">{{ formatDate(c.created_at) }}</td>
          </tr>
          <tr v-if="customers.length === 0"><td colspan="7" class="list-empty">No customers found.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="list-pagination">
      <button
        v-for="page in meta.last_page" :key="page"
        @click="loadCustomers(page)"
        class="list-page-button"
        :class="page === meta.current_page ? 'list-page-button--active' : ''"
      >{{ page }}</button>
    </div>
  </div>
</template>
