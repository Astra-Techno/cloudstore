<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import QRCode from 'qrcode'
import api from '@/api/client'

const tables = ref<any[]>([])
const name = ref('')
const error = ref('')
const busy = ref(false)
const qr = ref<Record<number, string>>({})
const storeName = ref('')
const logoUrl = ref('')
const brandColor = ref('#E23744')
const tagline = ref('')
let timer: ReturnType<typeof setInterval>
const money = (v: number) => `₹${(v / 100).toFixed(2)}`
const url = (t: any) => `${location.origin}${import.meta.env.BASE_URL}table/${t.token}`
async function load() {
  try {
    const { data } = await api.get('/admin/tables')
    tables.value = data.data
    for (const t of tables.value) qr.value[t.id] = await QRCode.toDataURL(url(t), { width: 400, margin: 3, errorCorrectionLevel: 'M' })
    error.value = ''
  } catch (e: any) { error.value = e.response?.data?.error?.message || 'Unable to load tables. Please retry.' }
}
async function loadBranding() {
  try {
    const { data } = await api.get('/admin/settings')
    if (data.success && data.data) {
      storeName.value = data.data.store?.name || ''
      logoUrl.value = data.data.branding?.logo_url || ''
      brandColor.value = data.data.branding?.primary_color || '#E23744'
      tagline.value = data.data.branding?.tagline || ''
    }
  } catch { /* Use defaults */ }
}

function downloadSticker(t: any) {
  const canvas = document.createElement('canvas')
  const W = 600, H = 820
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')!
  const color = brandColor.value
  const r = 32

  // Rounded rect background
  ctx.beginPath()
  ctx.moveTo(r, 0); ctx.lineTo(W - r, 0); ctx.quadraticCurveTo(W, 0, W, r)
  ctx.lineTo(W, H - r); ctx.quadraticCurveTo(W, H, W - r, H)
  ctx.lineTo(r, H); ctx.quadraticCurveTo(0, H, 0, H - r)
  ctx.lineTo(0, r); ctx.quadraticCurveTo(0, 0, r, 0)
  ctx.closePath()
  ctx.fillStyle = '#fff'
  ctx.fill()

  // Top colored band
  ctx.beginPath()
  ctx.moveTo(r, 0); ctx.lineTo(W - r, 0); ctx.quadraticCurveTo(W, 0, W, r)
  ctx.lineTo(W, 130); ctx.lineTo(0, 130); ctx.lineTo(0, r); ctx.quadraticCurveTo(0, 0, r, 0)
  ctx.closePath()
  ctx.fillStyle = color
  ctx.fill()

  // Store name on band
  ctx.fillStyle = '#fff'
  ctx.font = 'bold 32px Inter, system-ui, sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText(storeName.value || 'Our Restaurant', W / 2, 55, W - 60)

  // Tagline or subtitle
  ctx.font = '500 16px Inter, system-ui, sans-serif'
  ctx.globalAlpha = 0.85
  ctx.fillText(tagline.value || 'Scan. Order. Enjoy.', W / 2, 85, W - 60)
  ctx.globalAlpha = 1

  // Table name pill
  ctx.font = 'bold 15px Inter, system-ui, sans-serif'
  const pillText = t.name
  const pillW = ctx.measureText(pillText).width + 40
  const pillH = 34, pillY = 108, pillX = (W - pillW) / 2
  ctx.beginPath()
  ctx.roundRect(pillX, pillY, pillW, pillH, 17)
  ctx.fillStyle = '#fff'
  ctx.fill()
  ctx.fillStyle = color
  ctx.textAlign = 'center'
  ctx.fillText(pillText, W / 2, pillY + 23)

  // Border
  ctx.beginPath()
  ctx.moveTo(r, 0); ctx.lineTo(W - r, 0); ctx.quadraticCurveTo(W, 0, W, r)
  ctx.lineTo(W, H - r); ctx.quadraticCurveTo(W, H, W - r, H)
  ctx.lineTo(r, H); ctx.quadraticCurveTo(0, H, 0, H - r)
  ctx.lineTo(0, r); ctx.quadraticCurveTo(0, 0, r, 0)
  ctx.closePath()
  ctx.strokeStyle = '#e2e8f0'
  ctx.lineWidth = 2
  ctx.stroke()

  // QR code
  const qrImg = new Image()
  qrImg.onload = () => {
    const qrSize = 340
    const qrX = (W - qrSize) / 2, qrY = 165
    // QR shadow
    ctx.shadowColor = 'rgba(0,0,0,0.08)'
    ctx.shadowBlur = 20
    ctx.shadowOffsetY = 4
    ctx.beginPath()
    ctx.roundRect(qrX - 16, qrY - 16, qrSize + 32, qrSize + 32, 20)
    ctx.fillStyle = '#fff'
    ctx.fill()
    ctx.shadowColor = 'transparent'
    ctx.shadowBlur = 0
    ctx.shadowOffsetY = 0
    // QR border
    ctx.strokeStyle = '#f1f5f9'
    ctx.lineWidth = 1
    ctx.stroke()
    // Draw QR
    ctx.drawImage(qrImg, qrX, qrY, qrSize, qrSize)

    // "Scan to order" heading
    ctx.fillStyle = '#0f172a'
    ctx.font = 'bold 26px Inter, system-ui, sans-serif'
    ctx.textAlign = 'center'
    ctx.fillText('Scan to Order', W / 2, qrY + qrSize + 60)

    // Instructions
    ctx.fillStyle = '#64748b'
    ctx.font = '500 15px Inter, system-ui, sans-serif'
    ctx.fillText('Open your phone camera and point it at the QR code', W / 2, qrY + qrSize + 88)
    ctx.fillText('Browse the menu, customize & place your order', W / 2, qrY + qrSize + 110)

    // Bottom accent line
    ctx.beginPath()
    ctx.moveTo(W / 2 - 40, H - 50)
    ctx.lineTo(W / 2 + 40, H - 50)
    ctx.strokeStyle = color
    ctx.lineWidth = 3
    ctx.lineCap = 'round'
    ctx.stroke()

    // "No app needed" badge
    ctx.font = 'bold 12px Inter, system-ui, sans-serif'
    ctx.fillStyle = color
    ctx.fillText('NO APP NEEDED', W / 2, H - 28)

    // Download
    const link = document.createElement('a')
    link.download = `${storeName.value || 'table'}-${t.name}.png`
    link.href = canvas.toDataURL('image/png')
    link.click()
  }
  qrImg.src = qr.value[t.id]
}
async function create() {
  busy.value = true
  try { await api.post('/admin/tables', { name: name.value }); name.value = ''; await load() }
  catch (e: any) { error.value = e.response?.data?.error?.message || 'Unable to create table.' }
  finally { busy.value = false }
}
const renaming = ref<number | null>(null)
const renameName = ref('')

async function action(t: any, act: string, extra: Record<string, any> = {}) {
  if (act === 'close' && !confirm(`Confirm ${money(t.bill_total)} has been collected and close this table visit?`)) return
  if (act === 'regenerate' && !confirm('Replace this QR code? The old printed QR will stop working.')) return
  if (act === 'delete' && !confirm(`Delete table "${t.name}"? This cannot be undone.`)) return
  busy.value = true
  try { await api.post(`/admin/tables/${t.id}/action`, { action: act, payment_received: act === 'close', ...extra }); renaming.value = null; await load() }
  catch (e: any) { error.value = e.response?.data?.error?.message || 'Unable to update table.' }
  finally { busy.value = false }
}
function startRename(t: any) { renaming.value = t.id; renameName.value = t.name }
function submitRename(t: any) { if (renameName.value.trim()) action(t, 'rename', { name: renameName.value.trim() }) }
function print() { window.print() }
onMounted(() => { load(); loadBranding(); timer = setInterval(() => { if (!busy.value && !document.hidden) load() }, 10000) })
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <section class="tables-page">
    <header><div><p class="eyebrow">DINE-IN ADD-ON</p><h1>QR table ordering</h1><p>Open a visit when guests arrive. Share its code. Serve orders, collect payment, then close the bill.</p></div><button @click="print">Print QR cards</button></header>
    <p v-if="error" role="alert" class="error">{{ error }} <button @click="load">Retry</button></p>
    <form @submit.prevent="create" class="controls"><input v-model="name" required maxlength="80" placeholder="Table name, e.g. Terrace 4" aria-label="Table name"><button :disabled="busy">Add table</button></form>
    <p v-if="!tables.length && !error">Create your first table to generate its QR code. The platform admin must enable the QR Table Ordering add-on for this tenant.</p>
    <div class="table-grid">
      <article v-for="t in tables" :key="t.id" class="table-card">
        <div v-if="renaming === t.id" style="display:flex;gap:8px;margin-bottom:8px"><input v-model="renameName" maxlength="80" @keyup.enter="submitRename(t)" style="flex:1;border:1px solid #ddd;border-radius:8px;padding:8px"><button :disabled="busy" @click="submitRename(t)" style="padding:8px 14px">Save</button><button @click="renaming = null" class="cancel-btn" style="padding:8px 14px">Cancel</button></div>
        <h2 v-else @dblclick="startRename(t)" title="Double-click to rename">{{ t.name }}</h2><p>Scan to view the menu and order</p>
        <div class="sticker-preview">
          <div class="sticker-band" :style="{ background: brandColor }">
            <span class="sticker-store">{{ storeName || 'Your Store' }}</span>
            <span class="sticker-tagline">{{ tagline || 'Scan. Order. Enjoy.' }}</span>
            <span class="sticker-table-pill" :style="{ color: brandColor }">{{ t.name }}</span>
          </div>
          <img :src="qr[t.id]" :alt="`Menu QR for ${t.name}`" width="180" height="180">
          <span class="sticker-cta">Scan to Order</span>
          <span class="sticker-sub">No app needed</span>
        </div>
        <a :href="url(t)" target="_blank" rel="noopener">Open table menu</a>
        <div class="management">
          <a href="#" @click.prevent="downloadSticker(t)">Download sticker</a>
          <p>{{ t.enabled ? 'Enabled' : 'Disabled' }} · {{ t.session ? 'Guests seated' : 'No open visit' }}</p>
          <template v-if="t.session">
            <p class="code">Guest code: {{ t.session.access_code }}</p>
            <p>Current bill <strong>{{ money(t.bill_total) }}</strong></p>
            <ul><li v-for="o in t.orders" :key="o.uuid"><router-link :to="`/orders/${o.uuid}`">{{ o.order_number }}</router-link><span>{{ o.status.replaceAll('_', ' ') }} · {{ money(o.total) }}</span></li></ul>
            <button :disabled="busy" @click="action(t, 'close')">Payment collected · Close bill</button>
          </template>
          <template v-else>
            <button :disabled="busy || !t.enabled" @click="action(t, 'open')">Seat guests · Open visit</button>
            <div class="secondary"><button :disabled="busy" @click="action(t, t.enabled ? 'disable' : 'enable')">{{ t.enabled ? 'Disable' : 'Enable' }}</button><button :disabled="busy" @click="action(t, 'regenerate')">Replace QR</button><button :disabled="busy" @click="startRename(t)">Rename</button><button :disabled="busy" @click="action(t, 'delete')" class="delete-btn">Delete</button></div>
          </template>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.tables-page{max-width:1400px;margin:auto}.tables-page header{display:flex;justify-content:space-between;gap:24px;align-items:center;margin-bottom:24px}h1{font-size:28px;font-weight:750}h2{font-size:22px;font-weight:700}.eyebrow{color:var(--primary,#e23744);font-weight:700;font-size:12px;letter-spacing:2px}p{margin:10px 0;color:#64748b}button{background:var(--primary,#e23744);color:white;padding:12px 18px;border-radius:12px;font-weight:600}button:disabled{opacity:.5}.controls{display:flex;gap:12px;margin:24px 0}.controls input{border:1px solid #ddd;border-radius:12px;padding:12px;min-width:260px}.table-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px}.table-card{background:white;border:1px solid #e2e8f0;border-radius:20px;padding:24px}.table-card img{display:block}.table-card a{color:var(--primary,#e23744);text-decoration:underline}.management{border-top:1px solid #eee;margin-top:20px;padding-top:16px}.management .code{color:#111827;font-weight:700;letter-spacing:2px}.management li{margin-bottom:12px}.management li span{display:block;font-size:13px}.secondary{display:flex;gap:8px;margin-top:12px}.secondary button{background:#f1f5f9;color:#334155}.delete-btn{background:#fee2e2!important;color:#b91c1c!important}.cancel-btn{background:#f1f5f9!important;color:#334155!important}.error{padding:14px;background:#fff1f2;color:#b91c1c}
.sticker-preview{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;margin:12px 0;text-align:center}.sticker-band{padding:16px 12px 28px;position:relative;display:flex;flex-direction:column;align-items:center;gap:2px}.sticker-store{color:#fff;font-weight:700;font-size:15px}.sticker-tagline{color:rgba(255,255,255,.85);font-size:12px}.sticker-table-pill{position:absolute;bottom:-13px;background:#fff;padding:4px 16px;border-radius:12px;font-weight:700;font-size:13px}.sticker-preview img{margin:20px auto 8px}.sticker-cta{display:block;font-weight:700;font-size:15px;color:#0f172a}.sticker-sub{display:block;font-size:11px;color:#64748b;margin-bottom:12px}
@media print{.controls,.management,header button,.error{display:none}.table-card{break-inside:avoid}.table-card>a{display:none}.table-grid{grid-template-columns:1fr 1fr}.sticker-preview{border:none}}
</style>
