<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import api from '@/api/client'

type ProductOverride = { price?:number|null; quantity_limit?:number|null; prep_minutes?:number|null; available?:boolean }
type ExceptionDate = { exception_date:string; action:'closed'|'extended_cutoff'; cutoff_override?:string|null; reason?:string|null }
type Session = { uuid:string; name:string; ordering_mode:'preorder'|'instant'|'both'; weekdays:number[]; service_start:string; service_end:string; opens_day_offset:number; opens_at:string; cutoff_day_offset:number; cutoff_at:string; max_orders:number|null; delivery_fee_override:number|null; min_order_amount:number|null; enabled:boolean|number; paused:boolean|number; sort_order:number; product_uuids:string[]; product_overrides?:Record<string,ProductOverride>; exceptions?:ExceptionDate[] }

const sessions = ref<Session[]>([]), products = ref<any[]>([]), error = ref(''), busy = ref(false), editing = ref<string|null>(null)
const tab = ref<'sessions'|'production'>('sessions')
const days = [{v:1,n:'Mon'},{v:2,n:'Tue'},{v:3,n:'Wed'},{v:4,n:'Thu'},{v:5,n:'Fri'},{v:6,n:'Sat'},{v:7,n:'Sun'}]
const blank = (): Session => ({ uuid:'', name:'', ordering_mode:'preorder', weekdays:[1,2,3,4,5,6,7], service_start:'07:00', service_end:'10:00', opens_day_offset:-1, opens_at:'18:00', cutoff_day_offset:0, cutoff_at:'06:00', max_orders:null, delivery_fee_override:null, min_order_amount:null, enabled:true, paused:false, sort_order:0, product_uuids:[], product_overrides:{}, exceptions:[] })
const form = ref<Session>(blank())
const title = computed(() => editing.value ? 'Edit meal session' : 'Add meal session')

// Exception form
const excForm = ref<{uuid:string; date:string; action:string; cutoff:string; reason:string}>({uuid:'',date:'',action:'closed',cutoff:'',reason:''})

// Production
const prodDate = ref(new Date().toISOString().slice(0,10))
const production = ref<any[]>([])

function message(e:any){ const errors=e.response?.data?.error?.details; if(errors) return Object.values(errors).flat().join(' '); return e.response?.data?.error?.message || 'Unable to save.' }

async function loadProducts(){
  const all:any[]=[]
  let page=1, lastPage=1
  do {
    const response=await api.get('/admin/products',{params:{page,per_page:100,status:'active'}})
    all.push(...(response.data.data||[]))
    lastPage=Number(response.data.meta?.last_page||1)
    page++
  } while(page<=lastPage)
  return all
}

async function load(){ try { const [s,p]=await Promise.all([api.get('/admin/meal-sessions'),loadProducts()]); sessions.value=s.data.data||[]; products.value=p; error.value='' } catch(e:any){error.value=message(e)} }
function edit(s?:Session){ editing.value=s?.uuid||null; form.value=s ? {...s,weekdays:[...s.weekdays],product_uuids:[...s.product_uuids],product_overrides:{...(s.product_overrides||{})},exceptions:[...(s.exceptions||[])],service_start:s.service_start.slice(0,5),service_end:s.service_end.slice(0,5),opens_at:s.opens_at.slice(0,5),cutoff_at:s.cutoff_at.slice(0,5)} : blank(); window.scrollTo({top:0,behavior:'smooth'}) }
async function save(){ busy.value=true; error.value=''; try { if(editing.value) await api.put(`/admin/meal-sessions/${editing.value}`,form.value); else await api.post('/admin/meal-sessions',form.value); edit(); await load() } catch(e:any){error.value=message(e)} finally{busy.value=false} }
async function remove(s:Session){ if(!confirm(`Remove ${s.name}? Existing order history will be preserved.`))return; try{await api.delete(`/admin/meal-sessions/${s.uuid}`);await load()}catch(e:any){error.value=message(e)} }

// Quick actions
async function quickAction(uuid:string, action:string, extra:Record<string,any>={}){ try { await api.post(`/admin/meal-sessions/${uuid}/action`,{action,...extra}); await load() }catch(e:any){error.value=message(e)} }
async function togglePause(s:Session){ await quickAction(s.uuid, s.paused ? 'resume' : 'pause') }
async function extendCutoff(s:Session){ const m=prompt('Extend cutoff by how many minutes?','30'); if(!m)return; await quickAction(s.uuid,'extend_cutoff',{minutes:parseInt(m)}) }
async function closeEarly(s:Session){ if(!confirm(`Close ordering for ${s.name} now?`))return; await quickAction(s.uuid,'close_early') }
async function adjustCapacity(s:Session){ const c=prompt('New capacity (blank for unlimited):',s.max_orders?.toString()||''); if(c===null)return; await quickAction(s.uuid,'adjust_capacity',{max_orders:c===''?null:parseInt(c)}) }
async function markSoldOut(uuid:string, productUuid:string, soldOut:boolean){ await quickAction(uuid,'mark_sold_out',{product_uuid:productUuid, sold_out:soldOut}) }

// Exception dates
async function addException(){ if(!excForm.value.uuid||!excForm.value.date)return; try { await api.post(`/admin/meal-sessions/${excForm.value.uuid}/exceptions`,{exception_date:excForm.value.date,action:excForm.value.action,cutoff_override:excForm.value.cutoff||null,reason:excForm.value.reason||null}); excForm.value={uuid:'',date:'',action:'closed',cutoff:'',reason:''}; await load() }catch(e:any){error.value=message(e)} }
async function removeException(uuid:string, date:string){ try{await api.delete(`/admin/meal-sessions/${uuid}/exceptions/${date}`);await load()}catch(e:any){error.value=message(e)} }

// Production dashboard
async function loadProduction(){ try { const r=await api.get('/admin/meal-sessions/production',{params:{date:prodDate.value}}); production.value=r.data.data||[] }catch(e:any){error.value=message(e)} }
function pName(uuid:string){ return products.value.find((p:any)=>p.uuid===uuid)?.name || uuid.slice(0,8) }
function printPage(){ window.print() }

onMounted(async ()=>{ await load(); await loadProduction() })
</script>

<template>
  <main class="meal-page">
    <header>
      <div><p>PREORDER OPERATIONS</p><h1>Meal sessions</h1><span>Show the right menu at the right time and enforce every cutoff on the server.</span></div>
      <nav class="tab-bar"><button :class="{active:tab==='sessions'}" @click="tab='sessions'">Sessions</button><button :class="{active:tab==='production'}" @click="tab='production';loadProduction()">Kitchen production</button></nav>
    </header>
    <p v-if="error" class="meal-error">{{ error }}</p>

    <!-- Sessions tab -->
    <template v-if="tab==='sessions'">
      <section class="editor">
        <div class="editor-head"><h2>{{ title }}</h2><button v-if="editing" @click="edit()">Cancel edit</button></div>
        <div class="grid">
          <label>Session name<input v-model.trim="form.name" placeholder="Morning Tiffin"></label>
          <label>Ordering mode<select v-model="form.ordering_mode"><option value="preorder">Preorder only</option><option value="instant">Instant ordering</option><option value="both">Preorder + instant</option></select></label>
          <label>Service starts<input v-model="form.service_start" type="time"></label><label>Service ends<input v-model="form.service_end" type="time"></label>
          <label>Ordering opens<select v-model.number="form.opens_day_offset"><option :value="-2">2 days before</option><option :value="-1">Previous day</option><option :value="0">Same day</option></select><input v-model="form.opens_at" type="time"></label>
          <label>Cutoff<select v-model.number="form.cutoff_day_offset"><option :value="-1">Previous day</option><option :value="0">Same day</option><option :value="1">Next day</option></select><input v-model="form.cutoff_at" type="time"></label>
          <label>Order capacity<input v-model.number="form.max_orders" type="number" min="1" placeholder="Unlimited"></label>
          <label>Display order<input v-model.number="form.sort_order" type="number" min="0"></label>
          <label>Delivery fee override (paise)<input v-model.number="form.delivery_fee_override" type="number" min="0" placeholder="Use default"></label>
          <label>Min order amount (paise)<input v-model.number="form.min_order_amount" type="number" min="0" placeholder="Use default"></label>
        </div>
        <fieldset><legend>Available weekdays</legend><label v-for="d in days" :key="d.v" class="check"><input v-model="form.weekdays" type="checkbox" :value="d.v">{{d.n}}</label></fieldset>
        <fieldset><legend>Menu items ({{form.product_uuids.length}} selected)</legend><div class="products"><label v-for="p in products" :key="p.uuid" class="check"><input v-model="form.product_uuids" type="checkbox" :value="p.uuid"><span>{{p.name}}<small>{{p.category_name||'Uncategorized'}}</small></span></label></div></fieldset>
        <label class="check"><input v-model="form.enabled" type="checkbox">Session enabled</label>
        <button class="save" :disabled="busy||!form.name||!form.weekdays.length||!form.product_uuids.length" @click="save">{{busy?'Saving…':'Save meal session'}}</button>
      </section>

      <!-- Exception dates -->
      <section class="exc-section" v-if="sessions.length">
        <h2>Holiday & exception dates</h2>
        <div class="exc-form">
          <select v-model="excForm.uuid"><option value="">Select session…</option><option v-for="s in sessions" :key="s.uuid" :value="s.uuid">{{s.name}}</option></select>
          <input v-model="excForm.date" type="date">
          <select v-model="excForm.action"><option value="closed">Closed</option><option value="extended_cutoff">Extended cutoff</option></select>
          <input v-if="excForm.action==='extended_cutoff'" v-model="excForm.cutoff" type="time" placeholder="Override cutoff">
          <input v-model="excForm.reason" placeholder="Reason (optional)">
          <button @click="addException" :disabled="!excForm.uuid||!excForm.date">Add exception</button>
        </div>
        <div v-for="s in sessions.filter(s=>(s.exceptions||[]).length)" :key="s.uuid" class="exc-list">
          <h3>{{s.name}}</h3>
          <div v-for="ex in s.exceptions" :key="ex.exception_date" class="exc-item">
            <span>{{ex.exception_date}} — <strong>{{ex.action==='closed'?'Closed':'Extended cutoff'}}</strong><template v-if="ex.cutoff_override"> to {{ex.cutoff_override}}</template><template v-if="ex.reason"> — {{ex.reason}}</template></span>
            <button class="sm-btn danger" @click="removeException(s.uuid, ex.exception_date)">Remove</button>
          </div>
        </div>
      </section>

      <!-- Session cards -->
      <section class="cards">
        <article v-for="s in sessions" :key="s.uuid" :class="{off:!s.enabled, paused:!!s.paused}">
          <div>
            <span class="mode-badge">{{s.ordering_mode.replace('_',' ')}}</span>
            <span v-if="s.paused" class="pause-badge">PAUSED</span>
            <h2>{{s.name}}</h2>
            <p>{{s.service_start.slice(0,5)}}–{{s.service_end.slice(0,5)}} · cutoff {{s.cutoff_day_offset===-1?'previous day':'same day'}} {{s.cutoff_at.slice(0,5)}}</p>
            <small>{{s.product_uuids.length}} menu items · {{s.max_orders?`${s.max_orders} order capacity`:'unlimited capacity'}}</small>
            <small v-if="s.delivery_fee_override!=null"> · delivery ₹{{(s.delivery_fee_override/100).toFixed(2)}}</small>
            <small v-if="s.min_order_amount!=null"> · min ₹{{(s.min_order_amount/100).toFixed(2)}}</small>
          </div>
          <div class="card-actions">
            <button @click="togglePause(s)">{{s.paused?'Resume':'Pause'}}</button>
            <button @click="extendCutoff(s)">+Cutoff</button>
            <button @click="closeEarly(s)">Close now</button>
            <button @click="adjustCapacity(s)">Capacity</button>
            <button @click="edit(s)">Edit</button>
            <button class="danger" @click="remove(s)">Remove</button>
          </div>
        </article>
        <p v-if="!sessions.length">No meal sessions yet. Add the first session above.</p>
      </section>
    </template>

    <!-- Production tab -->
    <template v-if="tab==='production'">
      <section class="prod-section">
        <div class="prod-head">
          <label>Date <input type="date" v-model="prodDate" @change="loadProduction()"></label>
          <button @click="loadProduction()">Refresh</button>
          <button @click="printPage">Print</button>
        </div>
        <div v-if="!production.length" class="prod-empty">No sessions scheduled for this date.</div>
        <div v-for="sess in production" :key="sess.session_uuid" class="prod-card">
          <div class="prod-card-head">
            <h2>{{sess.session_name}}</h2>
            <span>{{sess.service_start}}–{{sess.service_end}}</span>
            <span class="prod-count">{{sess.order_count}} orders<template v-if="sess.capacity"> / {{sess.capacity}} capacity</template></span>
          </div>
          <table v-if="sess.items.length" class="prod-table">
            <thead><tr><th>Item</th><th>Qty</th></tr></thead>
            <tbody><tr v-for="item in sess.items" :key="item.product_uuid"><td>{{item.product_name}}</td><td class="qty">{{item.total_quantity}}</td></tr></tbody>
          </table>
          <p v-else class="prod-empty">No orders yet for this session.</p>
        </div>
      </section>
    </template>
  </main>
</template>

<style scoped>
.meal-page{padding:38px;max-width:1300px;color:#172033}.meal-page header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px}.meal-page header p{color:#e23744;font-size:12px;font-weight:800;letter-spacing:2px}.meal-page h1{font-size:34px;font-weight:850}.meal-page header span{color:#64748b}.meal-error{margin:18px 0;padding:14px;background:#fff1f2;color:#9f1239;border-radius:12px}
.tab-bar{display:flex;gap:4px;background:#f1f5f9;border-radius:10px;padding:4px}.tab-bar button{padding:10px 18px;border:0;background:transparent;border-radius:8px;font-weight:700;cursor:pointer}.tab-bar button.active{background:white;box-shadow:0 1px 3px rgba(0,0,0,.1)}
.editor{margin:28px 0;padding:26px;background:white;border:1px solid #e2e8f0;border-radius:22px}.editor-head{display:flex;justify-content:space-between}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0}label{font-size:13px;font-weight:700;color:#475569}input,select{display:block;width:100%;margin-top:7px;padding:11px;border:1px solid #dbe2ea;border-radius:10px;background:white}fieldset{margin:20px 0;padding:16px;border:1px solid #e2e8f0;border-radius:14px}legend{padding:0 8px;font-weight:800}.check{display:inline-flex;align-items:center;gap:8px;margin:7px 16px 7px 0}.check input{width:auto;margin:0}.check small{display:block;color:#94a3b8}.products{display:grid;grid-template-columns:repeat(4,1fr);max-height:260px;overflow:auto}.save{background:#e23744;color:white;border:0;border-radius:12px;padding:13px 22px;font-weight:800}.save:disabled{opacity:.45}
.exc-section{margin:28px 0;padding:26px;background:white;border:1px solid #e2e8f0;border-radius:22px}.exc-section h2{margin-bottom:14px}.exc-form{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.exc-form input,.exc-form select{margin:0;padding:10px;width:auto;flex:1;min-width:130px}.exc-form button{padding:10px 16px;background:#e23744;color:white;border:0;border-radius:10px;font-weight:700}.exc-form button:disabled{opacity:.4}.exc-list{margin-top:12px}.exc-list h3{font-size:14px;margin-bottom:6px}.exc-item{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:4px;font-size:13px}.sm-btn{padding:4px 10px;border:1px solid #e2e8f0;border-radius:6px;background:white;font-size:12px;cursor:pointer}.sm-btn.danger{color:#b91c1c}
.cards{display:grid;gap:12px;margin-top:16px}.cards article{display:flex;justify-content:space-between;align-items:center;padding:22px;background:white;border:1px solid #e2e8f0;border-radius:18px}.cards article.off{opacity:.55}.cards article.paused{border-left:4px solid #f59e0b}.mode-badge{text-transform:uppercase;color:#e23744;font-size:11px;font-weight:800}.pause-badge{background:#fef3c7;color:#92400e;font-size:10px;font-weight:800;padding:2px 8px;border-radius:6px;margin-left:8px}.cards p,.cards small{color:#64748b}.card-actions{display:flex;flex-wrap:wrap;gap:6px}.card-actions button{padding:8px 12px;border:1px solid #e2e8f0;border-radius:9px;background:white;font-size:12px;font-weight:600;cursor:pointer}.card-actions .danger{color:#b91c1c}
.prod-section{margin:28px 0}.prod-head{display:flex;gap:12px;align-items:flex-end;margin-bottom:20px}.prod-head label{margin:0}.prod-head input{margin:0;padding:10px}.prod-head button{padding:10px 16px;border:1px solid #e2e8f0;border-radius:10px;background:white;font-weight:700;cursor:pointer}.prod-card{background:white;border:1px solid #e2e8f0;border-radius:18px;padding:22px;margin-bottom:14px}.prod-card-head{display:flex;align-items:baseline;gap:12px;margin-bottom:14px}.prod-card-head h2{font-size:20px;margin:0}.prod-count{background:#ecfdf5;color:#065f46;padding:4px 12px;border-radius:8px;font-size:13px;font-weight:700}.prod-table{width:100%;border-collapse:collapse}.prod-table th{text-align:left;padding:8px 12px;border-bottom:2px solid #e2e8f0;font-size:13px;color:#64748b}.prod-table td{padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:14px}.prod-table .qty{font-weight:800;font-size:18px;text-align:right;width:80px}.prod-empty{color:#94a3b8;padding:20px;text-align:center}
@media(max-width:900px){.meal-page{padding:20px}.grid,.products{grid-template-columns:1fr 1fr}.card-actions{flex-direction:column}}@media(max-width:560px){.grid,.products{grid-template-columns:1fr}.cards article{align-items:flex-start;gap:14px;flex-direction:column}}
@media print{.tab-bar,.editor,.exc-section,.card-actions,.prod-head,.meal-page header{display:none!important}.prod-card{break-inside:avoid;page-break-inside:avoid}}
</style>
