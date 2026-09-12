<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { platformApi } from '@/api/platform'

type FeeEntry = {
  uuid: string
  tenant_name: string
  order_number: string
  gross_order_value: number
  fee_amount: number
  status: 'accrued' | 'settled'
  created_at: string
  settled_at?: string | null
}

const entries = ref<FeeEntry[]>([])
const status = ref<'accrued' | 'settled'>('accrued')
const loading = ref(true)
const settling = ref<string | null>(null)
const error = ref('')
const total = computed(() => entries.value.reduce((sum, entry) => sum + Number(entry.fee_amount), 0))

const money = (paise: number) => `₹${(Number(paise) / 100).toFixed(2)}`
const date = (value: string) => new Date(value).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await platformApi.getFeeLedger(status.value)
    entries.value = data.success ? data.data : []
  } catch {
    error.value = 'Unable to load the fee ledger.'
  } finally {
    loading.value = false
  }
}

async function settle(entry: FeeEntry) {
  settling.value = entry.uuid
  try {
    await platformApi.settleFeeLedger(entry.uuid)
    await load()
  } catch {
    error.value = 'The fee entry could not be marked as settled.'
  } finally {
    settling.value = null
  }
}

onMounted(load)
</script>

<template>
  <main class="fees-page">
    <section class="fees-hero">
      <div>
        <p>Marketplace operations</p>
        <h1>Fee ledger</h1>
        <span>1% per completed marketplace order, capped at ₹5.00.</span>
      </div>
      <div class="fees-total"><small>{{ status === 'accrued' ? 'Awaiting settlement' : 'Settled total' }}</small><strong>{{ money(total) }}</strong></div>
    </section>

    <section class="fees-card">
      <header>
        <div class="tabs" role="tablist">
          <button :class="{ active: status === 'accrued' }" @click="status = 'accrued'; load()">To settle</button>
          <button :class="{ active: status === 'settled' }" @click="status = 'settled'; load()">Settled</button>
        </div>
        <button class="refresh" @click="load">Refresh</button>
      </header>
      <p v-if="error" class="error">{{ error }}</p>
      <div v-if="loading" class="state">Loading fee entries…</div>
      <div v-else-if="entries.length === 0" class="state">No {{ status }} marketplace fees yet.</div>
      <div v-else class="fee-list">
        <article v-for="entry in entries" :key="entry.uuid" class="fee-row">
          <div class="fee-store"><b>{{ entry.tenant_name }}</b><span>{{ entry.order_number }} · {{ date(entry.created_at) }}</span></div>
          <div><small>Order</small><strong>{{ money(entry.gross_order_value) }}</strong></div>
          <div><small>Platform fee</small><strong>{{ money(entry.fee_amount) }}</strong></div>
          <button v-if="entry.status === 'accrued'" class="settle" :disabled="settling === entry.uuid" @click="settle(entry)">{{ settling === entry.uuid ? 'Saving…' : 'Mark settled' }}</button>
          <span v-else class="settled">Settled</span>
        </article>
      </div>
    </section>
  </main>
</template>

<style scoped>
.fees-page{max-width:1240px;margin:0 auto;padding:28px}.fees-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;padding:28px 30px;border-radius:24px;background:linear-gradient(118deg,#151526,#292445);color:#fff}.fees-hero p{margin:0 0 7px;color:#ffb4b4;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.12em}.fees-hero h1{margin:0;font-size:32px;letter-spacing:-.06em}.fees-hero span{display:block;margin-top:8px;color:#c9c8d7;font-size:13px}.fees-total{min-width:175px;padding:14px 18px;border-radius:17px;background:rgba(255,255,255,.1)}.fees-total small,.fee-row small{display:block;color:#8b8b9b;font-size:11px}.fees-total small{color:#d3d2de}.fees-total strong{display:block;margin-top:5px;font-size:25px}.fees-card{margin-top:20px;border:1px solid #e7e5ea;border-radius:22px;background:#fff;overflow:hidden}.fees-card header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #eee}.tabs{display:flex;gap:5px}.tabs button,.refresh,.settle{border:0;border-radius:10px;padding:9px 13px;background:transparent;color:#5d5b6e;font-weight:800;font-size:12px;cursor:pointer}.tabs button.active{background:#fce4e4;color:#a51d1d}.refresh{background:#f4f3f5;color:#232237}.fee-list{display:grid}.fee-row{display:grid;grid-template-columns:1.8fr .8fr .8fr auto;gap:18px;align-items:center;padding:18px;border-bottom:1px solid #f0eff2}.fee-row:last-child{border-bottom:0}.fee-store b,.fee-row strong{display:block;color:#222133;font-size:14px}.fee-store span{display:block;margin-top:4px;color:#858495;font-size:12px}.fee-row strong{margin-top:4px}.settle{background:#2563eb;color:#fff}.settle:disabled{opacity:.6}.settled{justify-self:start;border-radius:999px;padding:6px 10px;background:#dcfce7;color:#147240;font-size:11px;font-weight:800}.state,.error{padding:42px 18px;text-align:center;color:#777687}.error{margin:0;padding:12px;background:#fff1f1;color:#b42318}@media(max-width:700px){.fees-page{padding:16px}.fees-hero{align-items:flex-start;flex-direction:column}.fee-row{grid-template-columns:1fr 1fr}.fee-store{grid-column:1/-1}.settle,.settled{grid-column:1/-1;justify-self:start}}
</style>
