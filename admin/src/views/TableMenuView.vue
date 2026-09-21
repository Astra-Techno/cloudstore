<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const token = String(route.params.token)
// Guest ordering never sends admin credentials or redirects guests to admin login.
const api = axios.create({ baseURL: import.meta.env.VITE_API_URL || '/api/v1', timeout: 20000 })
const menu = ref<any>(null), error = ref(''), loading = ref(true), busy = ref(false)
const search = ref(''), category = ref('All'), code = ref(''), notes = ref('')
const cart = ref<any[]>([]), selected = ref<any>(null), variant = ref(''), extras = ref<number[]>([])
const receipts = ref<any[]>([])
const receiptTokens = ref<string[]>([])
let requestKey = crypto.randomUUID()
let timer: ReturnType<typeof setInterval>

// Phone identification
const phone = ref('')
const identified = ref(false)
const identifying = ref(false)
const customerName = ref('')
const suggestions = ref<{ name: string; price: number }[]>([])

const money = (v: number) => `₹${(v / 100).toFixed(2)}`
const categories = computed(() => ['All', ...new Set<string>((menu.value?.products || []).map((p: any) => p.category))])
const products = computed(() => (menu.value?.products || []).filter((p: any) => (category.value === 'All' || p.category === category.value) && p.name.toLowerCase().includes(search.value.toLowerCase())))
const total = computed(() => cart.value.reduce((sum, line) => sum + line.price * line.quantity, 0))
const color = computed(() => /^#[a-f0-9]{6}$/i.test(menu.value?.branding?.primary_color || '') ? menu.value.branding.primary_color : '#E23744')

function cartCount(productUuid: string) {
  return cart.value.filter(l => l.product_uuid === productUuid).reduce((s, l) => s + l.quantity, 0)
}

function errorMessage(e: any) { return e.response?.data?.error?.message || 'Connection interrupted. Please try again.' }

async function load() {
  loading.value = true
  try { menu.value = (await api.get(`/dining/menu/${token}`)).data.data; error.value = '' }
  catch (e) { error.value = errorMessage(e) }
  finally { loading.value = false }
}

async function identify() {
  const cleaned = phone.value.replace(/\D/g, '')
  if (cleaned.length < 10) { error.value = 'Enter a valid 10-digit mobile number.'; return }
  identifying.value = true; error.value = ''
  try {
    const { data } = await api.post(`/dining/menu/${token}/identify`, { phone: cleaned })
    if (data.data) {
      customerName.value = data.data.name || ''
      suggestions.value = data.data.suggestions || []
      identified.value = true
      try { localStorage.setItem(`dining-phone:${token}`, cleaned) } catch { /* private browsing */ }
    }
  } catch (e) { error.value = errorMessage(e) }
  finally { identifying.value = false }
}

function addSuggestion(s: { name: string; price: number }) {
  const product = (menu.value?.products || []).find((p: any) => p.name === s.name && p.available)
  if (product) choose(product)
}

function choose(p: any) { selected.value = p; variant.value = ''; extras.value = []; error.value = '' }
function add() {
  const p = selected.value
  const v = p.variants.find((v: any) => v.uuid === variant.value)
  if (p.variants.length && !v) { error.value = 'Please choose an option.'; return }
  for (const g of p.addons) {
    const count = g.items.filter((i: any) => extras.value.includes(Number(i.id))).length
    if (count < Math.max(Number(g.min_selections), g.is_required ? 1 : 0) || count > Number(g.max_selections)) { error.value = `Check your choices for ${g.name}.`; return }
  }
  const items = p.addons.flatMap((g: any) => g.items).filter((i: any) => extras.value.includes(Number(i.id)))
  cart.value.push({ product_uuid: p.uuid, variant_uuid: v?.uuid || null, addon_ids: [...extras.value], name: p.name, option: [v?.name, ...items.map((i: any) => i.name)].filter(Boolean).join(', '), price: Number(v?.price ?? p.price) + items.reduce((s: number, i: any) => s + Number(i.price), 0), quantity: 1 })
  requestKey = crypto.randomUUID(); selected.value = null; error.value = ''
}
function quantity(index: number, delta: number) {
  cart.value[index].quantity = Math.min(99, cart.value[index].quantity + delta)
  if (!cart.value[index].quantity) cart.value.splice(index, 1)
  requestKey = crypto.randomUUID()
}
async function track() {
  try { receipts.value = await Promise.all(receiptTokens.value.map(async (t) => (await api.get(`/dining/receipts/${t}`)).data.data)) }
  catch { /* Keep the last confirmed status; retries happen on the next poll. */ }
}
async function place() {
  busy.value = true; error.value = ''
  try {
    const { data } = await api.post(`/dining/menu/${token}/orders`, { request_key: requestKey, access_code: code.value, notes: notes.value, items: cart.value.map(({product_uuid,variant_uuid,addon_ids,quantity}) => ({product_uuid,variant_uuid,addon_ids,quantity})) })
    if (!receiptTokens.value.includes(data.data.receipt_token)) receiptTokens.value.push(data.data.receipt_token)
    try { localStorage.setItem(`dining:${token}`, JSON.stringify(receiptTokens.value.slice(-20))) } catch { /* Private browsing may disallow storage. */ }
    cart.value = []; notes.value = ''; requestKey = crypto.randomUUID(); await track()
  } catch (e) { error.value = errorMessage(e) }
  finally { busy.value = false }
}
onMounted(async () => {
  try { const saved = JSON.parse(localStorage.getItem(`dining:${token}`) || '[]'); if (Array.isArray(saved)) receiptTokens.value = saved.filter((s: any) => typeof s === 'string' && /^[a-f0-9]{64}$/.test(s)).slice(-20) } catch { /* Ignore invalid local cache. */ }
  // Restore saved phone
  try { const savedPhone = localStorage.getItem(`dining-phone:${token}`); if (savedPhone && /^\d{10,}$/.test(savedPhone)) { phone.value = savedPhone; identified.value = true } } catch { /* ignore */ }
  await load(); await track(); timer = setInterval(() => { if (!document.hidden) track() }, 8000)
})
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <main class="guest-menu" :style="{ '--brand': color }">
    <p v-if="loading" role="status">Preparing your menu…</p>
    <template v-if="menu">
      <header>
        <p class="eyebrow">WELCOME TO YOUR TABLE</p>
        <h1>{{ menu.store }}</h1>
        <p>{{ menu.branding.tagline || 'Freshly prepared. Served right here.' }}</p>
        <span class="table-pill">Table {{ menu.table }} · Dine-in</span>
      </header>

      <!-- Phone identification gate -->
      <section v-if="!identified" class="phone-gate">
        <h2>Enter your mobile number</h2>
        <p>We'll remember your preferences for faster ordering.</p>
        <div class="phone-form">
          <div class="phone-input-row">
            <span class="phone-prefix">+91</span>
            <input v-model="phone" type="tel" inputmode="numeric" maxlength="10" placeholder="10-digit mobile" :disabled="identifying" @keyup.enter="identify">
          </div>
          <button class="primary" :disabled="identifying || phone.replace(/\D/g, '').length < 10" @click="identify">{{ identifying ? 'Checking…' : 'Continue' }}</button>
        </div>
      </section>

      <template v-if="identified">
        <!-- Welcome back message for returning customers -->
        <div v-if="customerName" class="welcome-back">Welcome back, {{ customerName }}!</div>

        <p v-if="!menu.session_open" class="notice">Ask the staff to open your table visit before ordering. You can browse the menu now.</p>

        <!-- Previous order suggestions -->
        <section v-if="suggestions.length" class="suggestions">
          <h3>Your favourites</h3>
          <div class="suggestion-chips">
            <button v-for="s in suggestions" :key="s.name" class="suggestion-chip" @click="addSuggestion(s)">
              <strong>{{ s.name }}</strong>
              <small>{{ money(s.price) }}</small>
            </button>
          </div>
        </section>

        <section v-if="receipts.length" class="receipts">
          <h2>Your orders</h2>
          <article v-for="o in receipts" :key="o.order_number">
            <strong>{{ o.status === 'confirmed' ? 'Order placed ✓' : o.status.replaceAll('_', ' ') }}</strong>
            <span>{{ o.order_number }} · {{ money(o.total) }} · {{ o.payment_status === 'paid' ? 'Paid' : 'Pay at counter' }}</span>
          </article>
          <small>Status refreshes automatically. <button @click="track">Refresh status</button></small>
        </section>

        <input v-model="search" class="search" placeholder="Search the menu" aria-label="Search the menu">
        <nav aria-label="Menu categories"><button v-for="c in categories" :key="c" :class="{ active: c === category }" @click="category = c">{{ c }}</button></nav>

        <section class="products">
          <article v-for="p in products" :key="p.uuid" :class="{ unavailable: !p.available }">
            <div>
              <small>{{ p.category }}</small>
              <h2>{{ p.name }}</h2>
              <p>{{ p.description }}</p>
              <strong>{{ money(p.price) }} <small v-if="p.variants.length">· options available</small></strong>
            </div>
            <div class="product-action">
              <span v-if="cartCount(p.uuid)" class="item-badge">{{ cartCount(p.uuid) }}</span>
              <button :disabled="!p.available || !menu.session_open || busy" @click="choose(p)">{{ p.available ? 'Add +' : 'Sold out' }}</button>
            </div>
          </article>
          <p v-if="!products.length">No matching dishes. Try another search or category.</p>
        </section>

        <section v-if="cart.length" class="cart">
          <h2>Your table order <span class="cart-count">{{ cart.reduce((s, l) => s + l.quantity, 0) }} items</span></h2>
          <article v-for="(line, i) in cart" :key="i">
            <div>
              <strong>{{ line.name }}</strong>
              <small>{{ line.option }}</small>
              <span>{{ money(line.price * line.quantity) }}</span>
            </div>
            <div class="quantity">
              <button :disabled="busy" @click="quantity(i, -1)" aria-label="Reduce quantity">−</button>
              <span>{{ line.quantity }}</span>
              <button :disabled="busy || line.quantity >= 99" @click="quantity(i, 1)" aria-label="Increase quantity">+</button>
            </div>
          </article>
          <p>Subtotal {{ money(total) }} · Applicable tax added at checkout</p>
          <label>Table code from staff<input v-model="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Six-digit code" :disabled="busy"></label>
          <label>Special instructions<textarea v-model="notes" maxlength="500" placeholder="Any requests for the kitchen?" :disabled="busy" /></label>
          <p>Payment is collected at the counter after your meal.</p>
          <button class="primary" :disabled="busy || !/^\d{6}$/.test(code)" @click="place">{{ busy ? 'Sending order…' : `Place order · ${money(total)}` }}</button>
        </section>
      </template>
    </template>
    <div v-if="error" class="error" role="alert">{{ error }} <button v-if="!menu" @click="load">Retry</button></div>
    <div v-if="selected" class="scrim" @click.self="selected = null">
      <section class="options" role="dialog" aria-modal="true" aria-label="Customize item">
        <button class="close" @click="selected = null" aria-label="Close">×</button>
        <h2>{{ selected.name }}</h2>
        <fieldset v-if="selected.variants.length"><legend>Choose an option</legend><label v-for="v in selected.variants" :key="v.uuid"><input type="radio" v-model="variant" :value="v.uuid" :disabled="v.stock_mode === 'limited_stock' && Number(v.stock_quantity) < 1">{{ v.name }} · {{ money(Number(v.price)) }}</label></fieldset>
        <fieldset v-for="g in selected.addons" :key="g.id"><legend>{{ g.name }} ({{ Math.max(Number(g.min_selections), g.is_required ? 1 : 0) }}–{{ g.max_selections }})</legend><label v-for="item in g.items.filter((i: any) => i.status === 'active')" :key="item.id"><input type="checkbox" v-model="extras" :value="Number(item.id)">{{ item.name }} · {{ money(Number(item.price)) }}</label></fieldset>
        <p v-if="error" role="alert">{{ error }}</p>
        <button class="primary" @click="add">Add to order</button>
      </section>
    </div>
  </main>
</template>

<style scoped>
.guest-menu{--brand:#e23744;max-width:760px;margin:auto;padding:28px 20px 60px;background:white;min-height:100vh;color:#172033;font-family:Inter,system-ui,sans-serif}.eyebrow{font-size:11px;letter-spacing:2px;font-weight:750;color:var(--brand)}h1{font-size:30px;font-weight:800;margin:8px 0}h2{font-size:18px;font-weight:750}header p{color:#64748b;margin-bottom:12px}.table-pill{display:inline-block;background:#fff1f2;color:var(--brand);border-radius:30px;padding:9px 14px;font-weight:650}.search{width:100%;background:#f8fafc;padding:15px;border-radius:14px;border:1px solid #e2e8f0;margin:24px 0 12px}nav{display:flex;gap:8px;overflow:auto;padding-bottom:16px}button{cursor:pointer;border:1px solid #e2e8f0;border-radius:10px;padding:9px 14px;white-space:nowrap}button:disabled{opacity:.45;cursor:not-allowed}nav .active,.primary{background:var(--brand);color:white;border-color:var(--brand)}.products article{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:22px 0;border-bottom:1px solid #eef2f6}.products small{color:#64748b}.products p{color:#64748b;margin:6px 0 10px}.products article>div+.product-action button{color:var(--brand);font-weight:750}.product-action{position:relative;flex-shrink:0}.item-badge{position:absolute;top:-8px;right:-8px;min-width:22px;height:22px;display:grid;place-items:center;border-radius:50%;background:var(--brand);color:white;font-size:11px;font-weight:800;z-index:1}.unavailable{opacity:.5}.cart,.receipts{margin-top:28px;padding:20px;background:#f8fafc;border-radius:20px}.cart small,.cart span,.receipts span{display:block}.cart-count{font-size:14px;font-weight:600;color:var(--brand)}.quantity{display:flex;align-items:center;gap:12px}.cart label{display:block;margin:14px 0;font-weight:600}.cart input,.cart textarea{display:block;width:100%;margin-top:8px;padding:12px;border:1px solid #ddd;border-radius:10px;background:white}.cart p{margin:14px 0;color:#64748b;font-size:14px}.primary{width:100%;padding:14px;font-weight:700;font-size:16px}.error,.notice{padding:16px;background:#fff1f2;color:#9f1239;border-radius:12px;margin:16px 0}.receipts article{margin:14px 0}.receipts span{font-size:12px;color:#64748b}.receipts strong{text-transform:capitalize;color:var(--brand)}.scrim{position:fixed;inset:0;background:#0007;display:flex;align-items:end;justify-content:center;z-index:100}.options{position:relative;background:white;padding:28px;width:min(100%,600px);border-radius:24px 24px 0 0;max-height:85vh;overflow:auto}.options fieldset{margin:22px 0}.options label{display:flex;gap:12px;margin:12px 0}.close{float:right}.options legend{font-weight:700}
.phone-gate{text-align:center;padding:32px 0}.phone-gate h2{margin-bottom:6px}.phone-gate p{color:#64748b;font-size:14px;margin-bottom:20px}.phone-form{max-width:320px;margin:auto}.phone-input-row{display:flex;align-items:center;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:14px}.phone-prefix{padding:12px;background:#f8fafc;color:#64748b;font-weight:700;border-right:1px solid #e2e8f0}.phone-input-row input{flex:1;border:0;padding:12px;outline:none;font-size:16px}
.welcome-back{padding:14px 18px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;color:#166534;font-weight:700;margin:16px 0}
.suggestions{margin:20px 0}.suggestions h3{font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px}.suggestion-chips{display:flex;gap:8px;overflow-x:auto;padding-bottom:8px}.suggestion-chip{display:flex;flex-direction:column;align-items:flex-start;gap:2px;padding:10px 14px;border:1px solid #e2e8f0;border-radius:12px;background:white;white-space:nowrap}.suggestion-chip strong{font-size:13px;color:#172033}.suggestion-chip small{font-size:11px;color:#64748b}
@media(prefers-reduced-motion:no-preference){.receipts article{animation:arrive .25s ease-out}@keyframes arrive{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}}
</style>
