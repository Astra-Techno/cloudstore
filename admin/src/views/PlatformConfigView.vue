<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { platformApi } from '@/api/platform'

type ConfigState = Record<string, { configured: boolean; updated_at: string | null }>
const tenants = ref<Array<{ id: number; name: string }>>([])
const tenantId = ref(0)
const config = ref<ConfigState>({})
const values = ref<Record<string, string>>({})
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')

const fields = [
  ['fcm_server_key', 'Firebase server key', 'Used only by the backend to send push notifications.'],
  ['otp_webhook_url', 'OTP webhook URL', 'HTTPS endpoint of your SMS or WhatsApp provider relay.'],
  ['otp_webhook_token', 'OTP webhook token', 'Bearer token sent to your OTP relay.'],
  ['razorpay_key_id', 'Razorpay key ID', 'Safe to return to a payment client when online payment is enabled.'],
  ['razorpay_key_secret', 'Razorpay key secret', 'Backend-only Razorpay API credential.'],
  ['razorpay_webhook_secret', 'Razorpay webhook secret', 'Validates payment callbacks from Razorpay.'],
  ['github_token', 'GitHub build token', 'Backend-only token used to dispatch tenant APK builds.'],
  ['github_repo', 'GitHub repository', 'Repository in owner/name format used for automated builds.'],
  ['build_webhook_secret', 'Build webhook secret', 'Validates callbacks from the build workflow.'],
  ['mobile_api_origin', 'Mobile API origin', 'Public HTTPS server origin embedded in new mobile builds.'],
  ['mappls_static_key', 'Mappls browser key', 'Default map/search key. Restrict it to your CloudMarket domain in Mappls.'],
] as const

async function load() {
  loading.value = true; error.value = ''; success.value = ''
  try {
    const [{ data: configResponse }, { data: tenantResponse }] = await Promise.all([
      platformApi.getConfig(tenantId.value),
      platformApi.getTenants(),
    ])
    config.value = (configResponse.data?.settings ?? {}) as ConfigState
    tenants.value = (tenantResponse.data ?? []) as Array<{ id: number; name: string }>
    values.value = {}
  } catch { error.value = 'Unable to load configuration.' }
  finally { loading.value = false }
}

async function save() {
  const changed = Object.fromEntries(Object.entries(values.value).filter(([, value]) => value.trim() !== ''))
  if (!Object.keys(changed).length) { error.value = 'Enter at least one value to save.'; return }
  saving.value = true; error.value = ''; success.value = ''
  try {
    const { data } = await platformApi.updateConfig(tenantId.value, changed)
    if (data.success) { config.value = data.data.settings as ConfigState; values.value = {}; success.value = 'Configuration saved securely.' }
    else error.value = data.error?.message || 'Unable to save configuration.'
  } catch { error.value = 'Unable to save configuration.' }
  finally { saving.value = false }
}

watch(tenantId, load)
onMounted(load)
</script>

<template>
  <div class="max-w-4xl space-y-6">
    <section>
      <p class="text-xs font-bold uppercase tracking-widest text-red-600">Platform settings</p>
      <h1 class="mt-1 text-3xl font-black text-slate-900">Service configuration</h1>
      <p class="mt-2 text-sm text-slate-500">Values are encrypted in the database. Blank fields never overwrite a saved value.</p>
    </section>
    <div v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
    <div v-if="success" class="rounded-xl bg-green-50 p-3 text-sm text-green-700">{{ success }}</div>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <label class="block text-sm font-semibold text-slate-700">Apply configuration to</label>
      <select v-model.number="tenantId" class="mt-2 w-full max-w-md rounded-lg border border-slate-300 px-3 py-2">
        <option :value="0">All stores — platform default</option>
        <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">{{ tenant.name }} — tenant override</option>
      </select>
      <p class="mt-2 text-xs text-slate-500">A tenant override takes precedence over the platform default for its OTP, push, and payment calls.</p>
    </section>
    <form v-if="!loading" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="save">
      <div class="grid gap-5 md:grid-cols-2">
        <div v-for="([key, label, help]) in fields" :key="key">
          <label class="block text-sm font-semibold text-slate-700">{{ label }}</label>
          <input v-model="values[key]" type="password" autocomplete="new-password" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2" :placeholder="config[key]?.configured ? 'Configured — enter only to replace' : 'Not configured'" />
          <p class="mt-1 text-xs text-slate-500">{{ help }}</p>
        </div>
      </div>
      <button :disabled="saving" class="mt-6 rounded-lg bg-red-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">{{ saving ? 'Saving…' : 'Save configuration' }}</button>
    </form>
  </div>
</template>
