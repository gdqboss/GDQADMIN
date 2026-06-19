<script setup>
import { ref, computed, nextTick, watch, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

const props = defineProps({
  qrcodeId: { type: Number, required: true },
  h5Token: { type: String, default: null },
  h5User: { type: Object, default: null },
})

// 匿名设备 ID（用于免登录客服）
const deviceId = ref('')
onMounted(() => {
  let did = localStorage.getItem('anonymous_device_id')
  if (!did) {
    did = 'anon_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8)
    localStorage.setItem('anonymous_device_id', did)
  }
  deviceId.value = did
})

const isAnonymous = computed(() => !props.h5Token)

const isOpen = ref(false)
const messages = ref([])
const input = ref('')
const sending = ref(false)
const loading = ref(false)
const error = ref('')
const awaitingReply = ref(false) // 是否在等待客服回复
const aftersaleId = ref(null)
const lastTimestamp = ref('1970-01-01')
const messagesContainer = ref(null)
let pollTimer = null

function scrollToBottom() {
  nextTick(() => {
    const el = messagesContainer.value
    if (el) el.scrollTop = el.scrollHeight
  })
}

watch(() => messages.value.length, scrollToBottom)

async function openChat() {
  isOpen.value = true
  if (!aftersaleId.value) {
    await initChat()
  }
  startPolling()
}

function isAuthenticated() {
  return !!props.h5Token
}

function closeChat() {
  isOpen.value = false
  stopPolling()
}

async function initChat() {
  loading.value = true
  error.value = ''
  try {
    let endpoint
    let headers = {}
    if (isAuthenticated()) {
      endpoint = `/api/h5/chat/${props.qrcodeId}`
      headers = { 'Authorization': `Bearer ${props.h5Token}` }
    } else {
      endpoint = `/api/h5/chat/${props.qrcodeId}/anonymous?device_id=${encodeURIComponent(deviceId.value)}`
    }
    const res = await fetch(endpoint, { headers })
    const json = await res.json()
    if (json.code === 0) {
      aftersaleId.value = json.data.aftersaleId
      messages.value = json.data.messages || []
      if (messages.value.length) {
        lastTimestamp.value = messages.value[messages.value.length - 1].created_at
      }
    } else {
      error.value = json.message || t('serviceChat.networkError')
    }
  } catch {
    error.value = t('serviceChat.networkError')
  } finally {
    loading.value = false
  }
}

async function pollMessages() {
  if (!aftersaleId.value || !isOpen.value) return
  try {
    let endpoint
    let headers = {}
    if (isAuthenticated()) {
      endpoint = `/api/h5/chat/${aftersaleId.value}/messages?since=${encodeURIComponent(lastTimestamp.value)}`
      headers = { 'Authorization': `Bearer ${props.h5Token}` }
    } else {
      endpoint = `/api/h5/chat/${props.qrcodeId}/anonymous/messages?since=${encodeURIComponent(lastTimestamp.value)}&device_id=${encodeURIComponent(deviceId.value)}`
    }
    const res = await fetch(endpoint, { headers })
    const json = await res.json()
    if (json.code === 0 && json.data.length > 0) {
      messages.value.push(...json.data)
      lastTimestamp.value = json.data[json.data.length - 1].created_at
      // 如果拿到客服回复，取消"等待中"提示
      const hasStaffReply = json.data.some(m => m.sender_type === 'staff')
      if (hasStaffReply) {
        awaitingReply.value = false
      }
    }
  } catch { /* silent */ }
}

function startPolling() {
  stopPolling()
  pollTimer = setInterval(pollMessages, 5000)
}

function stopPolling() {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
}

onUnmounted(stopPolling)

async function handleSend() {
  const text = input.value.trim()
  if (!text || sending.value) return
  input.value = ''
  sending.value = true

  // 乐观更新
  const senderName = isAuthenticated() ? (props.h5User?.name || props.h5User?.phone) : '访客'
  const tempMsg = { id: Date.now(), sender_type: 'customer', sender_name: senderName, content: text, created_at: new Date().toISOString() }
  messages.value.push(tempMsg)

  try {
    let endpoint
    let headers = { 'Content-Type': 'application/json' }
    let body
    if (isAuthenticated()) {
      endpoint = `/api/h5/chat/${aftersaleId.value}/messages`
      headers['Authorization'] = `Bearer ${props.h5Token}`
      body = JSON.stringify({ content: text })
    } else {
      endpoint = `/api/h5/chat/${props.qrcodeId}/anonymous/messages`
      headers['X-Device-Id'] = deviceId.value
      body = JSON.stringify({ content: text, device_id: deviceId.value })
    }
    const res = await fetch(endpoint, { method: 'POST', headers, body })
    const json = await res.json()
    if (json.code === 0) {
      lastTimestamp.value = tempMsg.created_at
      awaitingReply.value = true  // 等待客服回复
    } else {
      error.value = json.message || t('serviceChat.sendFailed')
    }
  } catch {
    error.value = t('serviceChat.sendFailed')
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="fixed bottom-6 right-4 z-50">
    <!-- Chat Panel -->
    <transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 scale-95 translate-y-4"
      enter-to-class="opacity-100 scale-100 translate-y-0"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="opacity-100 scale-100 translate-y-0"
      leave-to-class="opacity-0 scale-95 translate-y-4"
    >
      <div
        v-if="isOpen"
        class="absolute bottom-16 right-0 w-[calc(100vw-2rem)] sm:w-[340px] h-[440px] bg-white rounded-xl border border-gray-100 shadow-lg flex flex-col overflow-hidden"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-primary text-white">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px]">support_agent</span>
            <span class="font-bold text-sm">{{ $t('serviceChat.title') }}</span>
          </div>
          <button @click="closeChat" class="p-1 rounded-lg hover:bg-white/20 transition-colors">
            <span class="material-symbols-outlined text-[18px]">close</span>
          </button>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-3 space-y-3">
          <!-- Loading -->
          <div v-if="loading" class="flex items-center justify-center h-full">
            <div class="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
          </div>

          <!-- Empty -->
          <div v-else-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-center px-4">
            <span class="material-symbols-outlined text-primary/30 text-[40px] mb-2">forum</span>
            <p class="text-xs text-text-secondary">{{ $t('serviceChat.noMessages') }}</p>
          </div>

          <!-- Message bubbles -->
          <div
            v-for="msg in messages"
            :key="msg.id"
            :class="['flex', msg.sender_type === 'customer' ? 'justify-end' : 'justify-start']"
          >
            <div class="max-w-[80%]">
              <p v-if="msg.sender_type === 'staff'" class="text-[10px] text-text-secondary mb-0.5">{{ msg.sender_name || $t('serviceChat.title') }}</p>
              <div
                :class="[
                  'px-3 py-2 text-sm leading-relaxed whitespace-pre-wrap break-words',
                  msg.sender_type === 'customer'
                    ? 'bg-primary text-white rounded-2xl rounded-br-sm'
                    : 'bg-gray-100 text-text-primary rounded-2xl rounded-bl-sm'
                ]"
              >
                {{ msg.content }}
              </div>
              <p class="text-[10px] text-text-secondary mt-0.5" :class="msg.sender_type === 'customer' ? 'text-right' : ''">
                {{ msg.created_at?.slice(11, 16) }}
              </p>
            </div>
          </div>
        </div>

        <!-- 等待客服回复 -->
        <div v-if="awaitingReply" class="flex justify-start">
          <div class="max-w-[80%]">
            <p class="text-[10px] text-text-secondary mb-0.5">{{ $t('serviceChat.title') }}</p>
            <div class="px-3 py-2 text-sm leading-relaxed bg-gray-100 text-text-primary rounded-2xl rounded-bl-sm">
              <span>{{ $t('serviceChat.awaitingReply') }}</span>
              <span class="inline-flex ml-1">
                <span class="animate-bounce [animation-delay:0ms]">.</span>
                <span class="animate-bounce [animation-delay:150ms]">.</span>
                <span class="animate-bounce [animation-delay:300ms]">.</span>
              </span>
            </div>
          </div>
        </div>

        <!-- Error -->
        <p v-if="error" class="px-3 py-1 text-xs text-red-600 bg-red-50">{{ error }}</p>

        <!-- Input -->
        <div class="px-3 py-2 border-t border-gray-100 bg-white">
          <div class="flex items-center gap-2">
            <input
              v-model="input"
              @keydown.enter="handleSend"
              type="text"
              :placeholder="$t('serviceChat.inputPlaceholder')"
              class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              :disabled="sending || loading"
            />
            <button
              @click="handleSend"
              :disabled="!input.trim() || sending"
              :class="[
                'p-2 rounded-lg transition-colors shrink-0',
                input.trim() && !sending
                  ? 'bg-primary hover:bg-primary-hover text-white'
                  : 'bg-gray-100 text-text-secondary cursor-not-allowed'
              ]"
            >
              <span class="material-symbols-outlined text-[18px]">send</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- FAB Button -->
    <button
      @click="isOpen ? closeChat() : openChat()"
      :class="[
        'rounded-full shadow-lg flex items-center justify-center transition-all duration-300',
        isOpen ? 'w-12 h-12 bg-gray-500 hover:bg-gray-600' : 'h-12 px-4 gap-1.5 bg-primary hover:bg-primary-hover'
      ]"
    >
      <span class="material-symbols-outlined text-white text-[24px]">
        {{ isOpen ? 'close' : 'support_agent' }}
      </span>
      <span v-if="!isOpen" class="text-white text-sm font-medium">{{ $t('serviceChat.fab') }}</span>
    </button>
  </div>
</template>
