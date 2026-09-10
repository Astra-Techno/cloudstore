<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { catalogApi } from '@/api/catalog'
import { ordersApi } from '@/api/orders'
import type { Category, Product, ProductVariant } from '@/types'

type PosLine = { product: Product; quantity: number; variant?: ProductVariant }
const products = ref<Product[]>([]), categories = ref<Category[]>([]), cart = ref<PosLine[]>([])
const activeCategory = ref('All'), query = ref(''), loading = ref(true), submitting = ref(false)
const paymentMethod = ref<'cash' | 'upi' | 'card'>('cash'), customerName = ref(''), customerPhone = ref(''), notes = ref('')
const error = ref(''), message = ref('')

// Weight variant picker
const weightPickerProduct = ref<Product | null>(null)

const visibleProducts = computed(() => products.value.filter(p => {
  const q = query.value.trim().toLowerCase()
  return (activeCategory.value === 'All' || p.category_name === activeCategory.value) && (!q || p.name.toLowerCase().includes(q) || p.category_name?.toLowerCase().includes(q))
}))

function linePrice(line: PosLine): number {
  if (line.variant) return line.variant.price
  return line.product.sale_price ?? line.product.base_price
}

const total = computed(() => cart.value.reduce((sum, line) => sum + linePrice(line) * line.quantity, 0))
const count = computed(() => cart.value.reduce((sum, line) => sum + line.quantity, 0))
const money = (value: number) => `₹${(value / 100).toFixed(2)}`
const getQuantity = (product: Product) => cart.value.filter(line => line.product.uuid === product.uuid).reduce((sum, l) => sum + l.quantity, 0)

function add(product: Product) {
  // Weight products with variants: show picker
  if (product.pricing_mode === 'weight' && product.variants?.length) {
    weightPickerProduct.value = product
    return
  }
  // Regular product or no variants
  const line = cart.value.find(item => item.product.uuid === product.uuid && !item.variant)
  if (line) line.quantity++
  else cart.value.push({ product, quantity: 1 })
}

function addWithVariant(product: Product, variant: ProductVariant) {
  const line = cart.value.find(item => item.product.uuid === product.uuid && item.variant?.id === variant.id)
  if (line) line.quantity++
  else cart.value.push({ product, quantity: 1, variant })
  weightPickerProduct.value = null
}

function change(line: PosLine, amount: number) { line.quantity += amount; if (line.quantity < 1) cart.value = cart.value.filter(item => item !== line) }

async function checkout() {
  error.value = ''; message.value = ''
  if (!cart.value.length) { error.value = 'Add menu items before taking payment.'; return }
  submitting.value = true
  try {
    const { data } = await ordersApi.posCheckout({
      items: cart.value.map(line => ({
        product_uuid: line.product.uuid,
        quantity: line.quantity,
        variant_uuid: line.variant?.uuid || undefined,
      })),
      customer_name: customerName.value || undefined,
      customer_phone: customerPhone.value || undefined,
      payment_method: paymentMethod.value,
      order_type: 'pickup',
      notes: notes.value || undefined,
    })
    if (!data.success) throw new Error(data.error?.message || 'Could not complete the sale.')
    message.value = `Sale ${data.data?.order.order_number || ''} completed and sent to orders.`
    cart.value = []; customerName.value = ''; customerPhone.value = ''; notes.value = ''
  } catch (e) { error.value = e instanceof Error ? e.message : 'Could not complete the sale.' } finally { submitting.value = false }
}

async function loadAllProducts() {
  let page = 1, all: Product[] = []
  while (true) {
    const { data } = await catalogApi.listProducts(page, undefined, 'active', true)
    all = all.concat(data.data || [])
    if (!data.meta || page >= data.meta.last_page) break
    page++
  }
  return all
}

onMounted(async () => { try { const [p, c] = await Promise.all([loadAllProducts(), catalogApi.listCategories()]); products.value = p; categories.value = c.data.data || [] } finally { loading.value = false } })
</script>

<template>
  <main class="pos-page">
    <header class="pos-header"><div><p>COUNTER / POS</p><h1>Make a new sale</h1><span>Tap products, take payment, and send it to the kitchen.</span></div><div class="pos-live"><i></i> Counter open</div></header>
    <p v-if="message" class="pos-notice pos-notice--ok">{{ message }}</p><p v-if="error" class="pos-notice pos-notice--error">{{ error }}</p>
    <div class="pos-layout"><section class="pos-menu"><div class="pos-search"><span>⌕</span><input v-model="query" placeholder="Search menu" autofocus /></div><div class="pos-categories"><button :class="{ active: activeCategory === 'All' }" @click="activeCategory = 'All'">All items</button><button v-for="category in categories" :key="category.uuid" :class="{ active: activeCategory === category.name }" @click="activeCategory = category.name">{{ category.name }}</button></div><div v-if="loading" class="pos-empty">Loading menu…</div><div v-else class="pos-products"><button v-for="product in visibleProducts" :key="product.uuid" class="pos-product" :class="{ selected: getQuantity(product) }" @click="add(product)"><span>{{ product.name.charAt(0) }}</span><strong>{{ product.name }}</strong><small>{{ product.pricing_mode === 'weight' ? `${product.category_name || 'Menu item'} · by weight` : product.category_name || 'Menu item' }}</small><b>{{ product.pricing_mode === 'weight' ? `${money(product.base_price)}/kg` : money(product.sale_price ?? product.base_price) }}</b><em v-if="getQuantity(product)">{{ getQuantity(product) }}</em></button><p v-if="!visibleProducts.length" class="pos-empty">No menu item found.</p></div></section>
      <aside class="pos-cart"><div class="pos-cart-head"><div><p>CURRENT SALE</p><h2>{{ count }} {{ count === 1 ? 'item' : 'items' }}</h2></div><button v-if="cart.length" @click="cart = []">Clear</button></div><div class="pos-lines"><div v-if="!cart.length" class="pos-empty"><strong>Your sale is empty</strong><small>Choose menu items from the left.</small></div><article v-for="(line, idx) in cart" :key="idx"><div><strong>{{ line.product.name }}</strong><small>{{ line.variant ? `${line.variant.name} · ` : '' }}{{ money(linePrice(line)) }} each</small></div><div class="pos-stepper"><button @click="change(line, -1)">−</button><b>{{ line.quantity }}</b><button @click="change(line, 1)">+</button></div><strong>{{ money(linePrice(line) * line.quantity) }}</strong></article></div><div class="pos-customer"><label>Customer <small>optional</small></label><div><input v-model="customerName" placeholder="Walk-in customer" /><input v-model="customerPhone" placeholder="Phone number" /></div><input v-model="notes" placeholder="Kitchen note" /></div><div class="pos-payment"><label>Payment</label><div><button v-for="method in ['cash', 'upi', 'card']" :key="method" :class="{ active: paymentMethod === method }" @click="paymentMethod = method as 'cash' | 'upi' | 'card'">{{ method }}</button></div></div><footer class="pos-footer"><div><span>Total payable</span><strong>{{ money(total) }}</strong><small>Pickup · paid now</small></div><button :disabled="submitting || !cart.length" @click="checkout">{{ submitting ? 'Completing…' : `Pay ${money(total)}` }} <b>→</b></button></footer></aside>
    </div>

    <!-- Weight Variant Picker Modal -->
    <div v-if="weightPickerProduct" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="weightPickerProduct = null">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ weightPickerProduct.name }}</h2>
        <p class="text-sm text-gray-500 mb-4">Choose weight — {{ money(weightPickerProduct.base_price) }}/kg</p>
        <div class="grid grid-cols-2 gap-3">
          <button
            v-for="v in weightPickerProduct.variants"
            :key="v.id"
            class="p-4 border-2 border-gray-200 rounded-xl text-center hover:border-red-500 hover:bg-red-50 transition-colors"
            @click="addWithVariant(weightPickerProduct!, v)"
          >
            <div class="text-lg font-bold text-gray-900">{{ v.name }}</div>
            <div class="text-sm font-semibold text-red-600">{{ money(v.price) }}</div>
          </button>
        </div>
        <button class="mt-4 w-full text-sm text-gray-500 hover:text-gray-700" @click="weightPickerProduct = null">Cancel</button>
      </div>
    </div>
  </main>
</template>
