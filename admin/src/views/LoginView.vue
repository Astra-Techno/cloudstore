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
  } catch (e: any) {
    error.value = e?.response?.data?.error?.message || (e instanceof Error ? e.message : 'Login failed')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <div class="login-left">
      <div class="login-brand-area">
        <img src="/logo.png" alt="CloudMarket" class="login-hero-logo" />
      </div>
    </div>
    <div class="login-right">
      <div class="login-card">
        <div class="login-card-header">
          <h1>Welcome back</h1>
          <p>Sign in to your admin dashboard</p>
        </div>

        <div v-if="error" class="login-error">
          {{ error }}
        </div>

        <form @submit.prevent="handleLogin">
          <div class="login-field">
            <label>Email address</label>
            <input
              v-model="email"
              type="email"
              required
              placeholder="admin@yourstore.com"
            />
          </div>

          <div class="login-field">
            <label>Password</label>
            <input
              v-model="password"
              type="password"
              required
              placeholder="Enter your password"
            />
          </div>

          <button type="submit" :disabled="loading" class="login-btn">
            {{ loading ? 'Signing in...' : 'Sign In' }}
          </button>
        </form>

        <p class="login-footer">Powered by <strong>CloudMarket</strong></p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
}

.login-left {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  position: relative;
  overflow: hidden;
}

.login-left::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 50% 100%, rgba(224,36,36,0.06) 0%, transparent 60%);
}

.login-brand-area {
  position: relative;
  z-index: 1;
  text-align: center;
  padding: 40px;
}

.login-hero-logo {
  width: 380px;
  max-width: 90%;
  height: auto;
  filter: drop-shadow(0 12px 32px rgba(224,36,36,0.15));
}

.login-right {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8f8fa;
}

.login-card {
  width: 100%;
  max-width: 380px;
  padding: 20px 32px;
}

.login-card-header {
  margin-bottom: 32px;
}

.login-card-header h1 {
  margin: 0;
  font-size: 28px;
  font-weight: 900;
  color: #1a1a2e;
  letter-spacing: -0.8px;
}

.login-card-header p {
  margin: 6px 0 0;
  color: #7a7a8a;
  font-size: 14px;
}

.login-error {
  padding: 12px 14px;
  margin-bottom: 20px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  color: #991b1b;
  font-size: 13px;
}

.login-field {
  margin-bottom: 20px;
}

.login-field label {
  display: block;
  margin-bottom: 6px;
  color: #4a4a5a;
  font-size: 13px;
  font-weight: 700;
}

.login-field input {
  width: 100%;
  padding: 12px 14px;
  border: 2px solid #e5e5ea;
  border-radius: 10px;
  background: #fff;
  color: #1a1a2e;
  font: inherit;
  font-size: 14px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  outline: none;
}

.login-field input:focus {
  border-color: #E02424;
  box-shadow: 0 0 0 3px rgba(224,36,36,0.1);
}

.login-field input::placeholder {
  color: #b0b0ba;
}

.login-btn {
  width: 100%;
  padding: 13px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, #E02424 0%, #c41e1e 100%);
  color: #fff;
  font: inherit;
  font-size: 15px;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 16px rgba(224,36,36,0.25);
}

.login-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 24px rgba(224,36,36,0.35);
}

.login-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.login-footer {
  margin: 32px 0 0;
  text-align: center;
  color: #b0b0ba;
  font-size: 12px;
}

.login-footer strong {
  color: #E02424;
}

@media (max-width: 900px) {
  .login-left { display: none; }
  .login-right { padding: 24px; }
  .login-card { max-width: 420px; }
}
</style>
