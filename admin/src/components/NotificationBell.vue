<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { notificationsApi, type Notification } from '@/api/notifications'

const notifications = ref<Notification[]>([])
const unreadCount = ref(0)
const showDropdown = ref(false)
const showToast = ref(false)
const toastMessage = ref('')
const loading = ref(false)

let pollTimer: ReturnType<typeof setInterval> | null = null
let toastTimer: ReturnType<typeof setTimeout> | null = null
let lastKnownCount = 0

const hasUnread = computed(() => unreadCount.value > 0)

async function fetchNotifications() {
  try {
    const { data } = await notificationsApi.list()
    if (data.success && data.data) {
      notifications.value = data.data.notifications
      const newCount = data.data.unread_count

      // Show toast + play sound if new notifications arrived
      if (newCount > lastKnownCount && lastKnownCount >= 0) {
        const diff = newCount - lastKnownCount
        if (lastKnownCount > 0) {
          // Only alert for genuinely new ones (not initial load)
          const newest = data.data.notifications.find(n => !n.read_at)
          if (newest) {
            triggerToast(newest.title + ': ' + newest.body)
            playNotificationSound()
          }
        }
      }

      lastKnownCount = newCount
      unreadCount.value = newCount
    }
  } catch (e) {
    // silently fail polling
  }
}

function triggerToast(message: string) {
  toastMessage.value = message
  showToast.value = true
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    showToast.value = false
  }, 5000)
}

function playNotificationSound() {
  try {
    const ctx = new AudioContext()
    const oscillator = ctx.createOscillator()
    const gain = ctx.createGain()
    oscillator.connect(gain)
    gain.connect(ctx.destination)
    oscillator.frequency.value = 800
    oscillator.type = 'sine'
    gain.gain.value = 0.3
    oscillator.start()
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3)
    oscillator.stop(ctx.currentTime + 0.3)
  } catch {
    // AudioContext not available
  }
}

function toggleDropdown() {
  showDropdown.value = !showDropdown.value
  if (showDropdown.value && notifications.value.length === 0) {
    fetchNotifications()
  }
}

async function markRead(id: number) {
  try {
    await notificationsApi.markRead(id)
    const n = notifications.value.find(n => n.id === id)
    if (n) n.read_at = new Date().toISOString()
    if (unreadCount.value > 0) unreadCount.value--
  } catch {
    // ignore
  }
}

async function markAllRead() {
  loading.value = true
  try {
    await notificationsApi.markAllRead()
    notifications.value.forEach(n => {
      if (!n.read_at) n.read_at = new Date().toISOString()
    })
    unreadCount.value = 0
  } catch {
    // ignore
  } finally {
    loading.value = false
  }
}

function formatTime(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  return new Date(dateStr).toLocaleDateString('en-IN', { dateStyle: 'short' })
}

function closeDropdown(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.notification-bell')) {
    showDropdown.value = false
  }
}

onMounted(() => {
  fetchNotifications()
  pollTimer = setInterval(fetchNotifications, 15000) // Poll every 15 seconds
  document.addEventListener('click', closeDropdown)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  if (toastTimer) clearTimeout(toastTimer)
  document.removeEventListener('click', closeDropdown)
})
</script>

<template>
  <!-- Toast notification -->
  <Teleport to="body">
    <Transition name="toast">
      <div
        v-if="showToast"
        class="fixed top-4 right-4 z-[100] max-w-sm bg-white border border-red-200 shadow-lg rounded-xl p-4 flex items-start gap-3"
      >
        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-900 truncate">New Order!</p>
          <p class="text-sm text-gray-600 truncate">{{ toastMessage }}</p>
        </div>
        <button @click="showToast = false" class="text-gray-400 hover:text-gray-600">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </Transition>
  </Teleport>

  <!-- Bell button & dropdown -->
  <div class="notification-bell relative">
    <button
      @click="toggleDropdown"
      class="relative p-2 text-gray-500 hover:text-gray-700 transition-colors rounded-lg hover:bg-gray-100"
    >
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      <span
        v-if="hasUnread"
        class="absolute -top-0.5 -right-0.5 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown -->
    <div
      v-if="showDropdown"
      class="absolute right-0 top-full mt-2 w-96 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden"
    >
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900 text-sm">Notifications</h3>
        <button
          v-if="hasUnread"
          @click="markAllRead"
          :disabled="loading"
          class="text-xs text-red-600 hover:text-red-800 font-medium"
        >
          Mark all read
        </button>
      </div>

      <div class="max-h-96 overflow-y-auto">
        <div v-if="notifications.length === 0" class="px-4 py-8 text-center text-gray-400 text-sm">
          No notifications yet
        </div>
        <div
          v-for="n in notifications"
          :key="n.id"
          @click="!n.read_at && markRead(n.id)"
          class="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition-colors"
          :class="{ 'bg-red-50/50': !n.read_at }"
        >
          <div class="flex items-start gap-3">
            <div
              class="w-2 h-2 rounded-full mt-1.5 shrink-0"
              :class="n.read_at ? 'bg-transparent' : 'bg-red-500'"
            ></div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ n.title }}</p>
              <p class="text-xs text-gray-500 truncate">{{ n.body }}</p>
              <p class="text-xs text-gray-400 mt-1">{{ formatTime(n.created_at) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.toast-enter-active {
  transition: all 0.3s ease-out;
}
.toast-leave-active {
  transition: all 0.2s ease-in;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(100px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(100px);
}
</style>
