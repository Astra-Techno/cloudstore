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
    <div class="login-left">
      <div class="login-brand-area">
        <img src="/logo.svg" alt="CloudMarket" class="login-hero-logo" />
        <h2>Cloud<strong>Market</strong></h2>
        <p class="login-tagline">Your Store. Your Delivery. Your Customers.</p>
        <div class="login-features">
          <div class="login-feature"><span>&#128722;</span> Sell</div>
          <div class="login-feature"><span>&#128230;</span> Manage</div>
          <div class="login-feature"><span>&#128690;</span> Deliver</div>
          <div class="login-feature"><span>&#128200;</span> Grow</div>
        </div>
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
  background: linear-gradient(135deg, #E02424 0%, #b91c1c 40%, #7f1d1d 100%);
  position: relative;
  overflow: hidden;
}

.login-left::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 30% 20%, rgba(255,255,255,0.1) 0%, transparent 50%),
              radial-gradient(circle at 70% 80%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.login-brand-area {
  position: relative;
  z-index: 1;
  text-align: center;
  padding: 40px;
}

.login-hero-logo {
  width: 160px;
  height: auto;
  filter: drop-shadow(0 8px 24px rgba(0,0,0,0.2));
  margin-bottom: 24px;
}

.login-brand-area h2 {
  margin: 0;
  color: #fff;
  font-size: 32px;
  font-weight: 400;
  letter-spacing: -0.5px;
}

.login-brand-area h2 strong {
  font-weight: 900;
}

.login-tagline {
  margin: 8px 0 0;
  color: rgba(255,255,255,0.75);
  font-size: 14px;
  letter-spacing: 0.5px;
}

.login-features {
  display: flex;
  gap: 20px;
  margin-top: 40px;
  justify-content: center;
}

.login-feature {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  color: rgba(255,255,255,0.85);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.login-feature span {
  font-size: 24px;
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
