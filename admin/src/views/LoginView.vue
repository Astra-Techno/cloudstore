<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const auth = useAuthStore()
const router = useRouter()

async function handleLogin() {
  error.value = ''
  loading.value = true

  try {
    await auth.login(email.value, password.value)
    router.push('/')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Login failed'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-brand">
        <img src="/logo.svg" alt="CloudStore" class="login-logo" />
        <h1>CloudStore</h1>
        <p>Admin Panel</p>
      </div>

      <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
        {{ error }}
      </div>

      <form @submit.prevent="handleLogin">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input
            v-model="email"
            type="email"
            required
            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
          />
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input
            v-model="password"
            type="password"
            required
            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-red-600 text-white py-2.5 rounded-md hover:bg-red-700 transition disabled:opacity-50 font-semibold"
        >
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #fef2f2 0%, #f8f8fa 50%, #fff 100%);
}
.login-card {
  width: 100%;
  max-width: 400px;
  padding: 40px 32px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e5e5ea;
  box-shadow: 0 8px 32px rgba(220,38,38,.06), 0 2px 8px rgba(0,0,0,.04);
}
.login-brand {
  text-align: center;
  margin-bottom: 32px;
}
.login-logo {
  width: 56px;
  height: 56px;
  margin: 0 auto 12px;
}
.login-brand h1 {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: #1a1a2e;
  letter-spacing: -.5px;
}
.login-brand p {
  margin: 4px 0 0;
  color: #8a8a9a;
  font-size: 13px;
}
</style>
