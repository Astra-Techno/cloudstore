<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { supportApi, type SupportMessage, type SupportTicket } from '@/api/support'

const tickets = ref<SupportTicket[]>([])
const selected = ref<SupportTicket | null>(null)
const messages = ref<SupportMessage[]>([])
const filter = ref('')
const reply = ref('')
const loading = ref(true)
const saving = ref(false)
const error = ref('')

const visibleTickets = computed(() => filter.value ? tickets.value.filter(ticket => ticket.status === filter.value) : tickets.value)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await supportApi.list()
    tickets.value = data.data || []
  } catch {
    error.value = 'Unable to load support requests.'
  } finally {
    loading.value = false
  }
}

async function openTicket(ticket: SupportTicket) {
  selected.value = ticket
  reply.value = ''
  try {
    const { data } = await supportApi.get(ticket.uuid)
    selected.value = data.data.ticket
    messages.value = data.data.messages || []
  } catch {
    error.value = 'Unable to open this support request.'
  }
}

async function changeStatus(status: SupportTicket['status']) {
  if (!selected.value) return
  saving.value = true
  try {
    await supportApi.updateStatus(selected.value.uuid, status)
    selected.value = { ...selected.value, status }
    tickets.value = tickets.value.map(ticket => ticket.uuid === selected.value?.uuid ? { ...ticket, status } : ticket)
  } finally {
    saving.value = false
  }
}

async function sendReply() {
  if (!selected.value || reply.value.trim().length === 0) return
  saving.value = true
  try {
    await supportApi.reply(selected.value.uuid, reply.value.trim())
    messages.value.push({ uuid: `local-${Date.now()}`, sender_type: 'admin', body: reply.value.trim(), created_at: new Date().toISOString() })
    reply.value = ''
    if (selected.value.status === 'open') await changeStatus('in_progress')
  } catch {
    error.value = 'Unable to send reply.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--primary)]">Customer care</p>
        <h1 class="text-3xl font-black text-slate-900">Support requests</h1>
        <p class="mt-1 text-sm text-slate-500">Resolve delivery, order, and account issues from one place.</p>
      </div>
      <select v-model="filter" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold">
        <option value="">All requests</option><option value="open">Open</option><option value="in_progress">In progress</option><option value="resolved">Resolved</option><option value="closed">Closed</option>
      </select>
    </div>

    <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
    <div class="grid gap-5 lg:grid-cols-[minmax(280px,0.8fr)_minmax(420px,1.2fr)]">
      <div class="rounded-2xl border border-slate-200 bg-white p-3">
        <p v-if="loading" class="p-5 text-sm text-slate-500">Loading requests…</p>
        <p v-else-if="visibleTickets.length === 0" class="p-5 text-sm text-slate-500">No support requests in this view.</p>
        <button v-for="ticket in visibleTickets" :key="ticket.uuid" @click="openTicket(ticket)" class="mb-2 w-full rounded-xl p-4 text-left transition hover:bg-slate-50" :class="selected?.uuid === ticket.uuid ? 'bg-red-50 ring-1 ring-red-200' : ''">
          <div class="flex items-center justify-between gap-2"><strong class="truncate text-sm">{{ ticket.subject }}</strong><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase">{{ ticket.status.replace('_', ' ') }}</span></div>
          <p class="mt-1 truncate text-xs text-slate-500">{{ ticket.customer_name || 'Customer' }} · {{ ticket.category }}</p>
        </button>
      </div>

      <div class="min-h-[420px] rounded-2xl border border-slate-200 bg-white p-5">
        <div v-if="!selected" class="flex h-full items-center justify-center text-sm text-slate-500">Select a request to view the conversation.</div>
        <template v-else>
          <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
            <div><h2 class="font-black text-slate-900">{{ selected.subject }}</h2><p class="mt-1 text-xs text-slate-500">{{ selected.customer_name }} · {{ selected.customer_phone }} <span v-if="selected.order_number">· {{ selected.order_number }}</span></p></div>
            <select :value="selected.status" :disabled="saving" @change="changeStatus(($event.target as HTMLSelectElement).value as SupportTicket['status'])" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold"><option value="open">Open</option><option value="in_progress">In progress</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select>
          </div>
          <div class="my-4 max-h-72 space-y-3 overflow-y-auto">
            <div v-for="message in messages" :key="message.uuid" class="max-w-[85%] rounded-2xl px-4 py-3 text-sm" :class="message.sender_type === 'admin' ? 'ml-auto bg-[var(--primary)] text-white' : 'bg-slate-100 text-slate-800'">{{ message.body }}</div>
          </div>
          <div class="flex gap-2 border-t border-slate-100 pt-4"><input v-model="reply" @keyup.enter="sendReply" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-3 text-sm" placeholder="Write a helpful reply…"><button :disabled="saving || !reply.trim()" @click="sendReply" class="rounded-xl bg-[var(--primary)] px-4 text-sm font-bold text-white disabled:opacity-50">Send</button></div>
        </template>
      </div>
    </div>
  </section>
</template>
