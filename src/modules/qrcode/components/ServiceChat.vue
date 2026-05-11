<template>
  <div class="service-chat-fab">
    <!-- 聊天窗口 -->
    <Transition name="chat-slide">
      <div v-if="visible" class="chat-window">
        <div class="chat-header">
          <div class="chat-header-left">
            <span class="material-symbols-outlined">support_agent</span>
            <span class="chat-title">{{ $t('serviceChat.title') }}</span>
          </div>
          <button class="close-btn" @click="close">×</button>
        </div>

        <!-- 加载中 -->
        <div v-if="connecting" class="chat-loading">
          <div class="spinner"></div>
        </div>

        <!-- 空消息 -->
        <div v-else-if="messages.length === 0" class="chat-empty">
          <span class="material-symbols-outlined empty-icon">forum</span>
          <p>{{ $t('serviceChat.noMessages') }}</p>
        </div>

        <!-- 消息列表 -->
        <div ref="msgList" class="messages-container">
          <div v-for="(msg, idx) in messages" :key="idx" :class="['message', msg.sender_type]">
            <div class="bubble" :class="msg.sender_type">
              <span v-if="msg.sender_type === 'staff'" class="sender-name">{{ msg.sender_name || $t('serviceChat.title') }}</span>
              <p class="msg-content">{{ msg.content }}</p>
              <span class="msg-time">{{ msg.created_at?.slice(11, 16) }}</span>
            </div>
          </div>
        </div>

        <!-- 错误提示 -->
        <p v-if="errorMsg" class="error-tip">{{ errorMsg }}</p>

        <!-- 输入区 -->
        <div class="input-area">
          <input v-model="inputText"             @keyup.enter="send"
            type="text"
            :placeholder="$t('serviceChat.inputPlaceholder')"
            :disabled="sending || connecting"
          />
          <button 
            :disabled="!inputText.trim() || sending" 
            :class="['send-btn', inputText.trim() && !sending ? 'active' : '']"
            @click="send"
          >
            <span class="material-symbols-outlined">send</span>
          </button>
        </div>
      </div>
    </Transition>

    <!-- 悬浮按钮 -->
    <button :class="['fab', visible ? 'fab-close' : 'fab-open']" @click="visible ? close() : open()">
      <span class="material-symbols-outlined">{{ visible ? 'close' : 'support_agent' }}</span>
      <span v-if="!visible" class="fab-label">{{ $t('serviceChat.fab') }}</span>
    </button>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  qrcodeId: { type: Number, required: true },
  h5Token: { type: String, default: null },
  h5User: { type: Object, default: null }
})

const { t } = useI18n()
const visible = ref(false)
const messages = ref([])
const inputText = ref('')
const sending = ref(false)
const connecting = ref(false)
const errorMsg = ref('')
const msgList = ref(null)
const roomId = ref(null)
const lastMsgTime = ref('1970-01-01')
let pollTimer = null

watch(() => messages.value.length, () => {
  nextTick(() => {
    if (msgList.value) msgList.value.scrollTop = msgList.value.scrollHeight
  })
})

async function open() {
  if (!props.h5User) {
    window.location.href = `/h5/login?redirect=/scan/${props.qrcodeId}`
    return
  }
  visible.value = true
  if (!roomId.value) await initRoom()
  startPolling()
}

function close() {
  visible.value = false
  stopPolling()
}

function stopPolling() {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
}

async function initRoom() {
  connecting.value = true
  try {
    const res = await fetch(`/api/h5/chat/${props.qrcodeId}`, {
      headers: { 'Authorization': `Bearer ${props.h5Token}` }
    })
    const data = await res.json()
    if (data.code === 0) {
      roomId.value = data.data.aftersaleId
      messages.value = data.data.messages || []
      if (messages.value.length) lastMsgTime.value = messages.value[messages.value.length - 1].created_at
    } else {
      errorMsg.value = data.message || t('serviceChat.networkError')
    }
  } catch {
    errorMsg.value = t('serviceChat.networkError')
  } finally {
    connecting.value = false
  }
}

function startPolling() {
  stopPolling()
  pollTimer = setInterval(pollNew, 5000)
}

async function pollNew() {
  if (!roomId.value || !visible.value) return
  try {
    const res = await fetch(`/api/h5/chat/${roomId.value}/messages?since=${encodeURIComponent(lastMsgTime.value)}`, {
      headers: { 'Authorization': `Bearer ${props.h5Token}` }
    })
    const data = await res.json()
    if (data.code === 0 && data.data.length > 0) {
      messages.value.push(...data.data)
      lastMsgTime.value = data.data[data.data.length - 1].created_at
    }
  } catch {}
}

async function send() {
  const text = inputText.value.trim()
  if (!text || sending.value || !roomId.value) return
  inputText.value = ''
  sending.value = true
  const msg = {
    id: Date.now(),
    sender_type: 'customer',
    sender_name: props.h5User?.name,
    content: text,
    created_at: new Date().toISOString()
  }
  messages.value.push(msg)
  try {
    const res = await fetch(`/api/h5/chat/${roomId.value}/messages`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${props.h5Token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ content: text })
    })
    const data = await res.json()
    if (data.code === 0) {
      lastMsgTime.value = msg.created_at
    } else {
      errorMsg.value = data.message || t('serviceChat.sendFailed')
    }
  } catch {
    errorMsg.value = t('serviceChat.sendFailed')
  } finally {
    sending.value = false
  }
}

onUnmounted(() => stopPolling())
</script>

<style scoped>
.service-chat-fab { position: fixed; bottom: 24px; right: 16px; z-index: 9999; }

.chat-window {
  position: absolute; bottom: 64px; right: 0;
  width: min(340px, calc(100vw - 32px));
  height: 440px;
  background: #fff; border-radius: 12px;
  border: 1px solid #f0f0f0;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  display: flex; flex-direction: column;
  overflow: hidden;
}

.chat-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;
  color: #fff;
}
.chat-header-left { display: flex; align-items: center; gap: 8px; }
.chat-title { font-weight: 600; font-size: 14px; }
.close-btn { font-size: 24px; background: none; border: none; color: #fff; cursor: pointer; line-height: 1; }

.chat-loading, .chat-empty {
  flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
  color: #999;
}
.spinner { width: 24px; height: 24px; border: 2px solid #667eea; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-icon { font-size: 40px; color: rgba(102,126,234,0.3); }

.messages-container { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 12px; }

.message { display: flex; }
.message.user { justify-content: flex-end; }
.message.assistant { justify-content: flex-start; }

.bubble { max-width: 80%; padding: 10px 14px; border-radius: 16px; font-size: 14px; line-height: 1.5; word-break: break-word; }
.bubble.customer { background: #667eea; color: #fff; border-bottom-right-radius: 4px; }
.bubble.staff { background: #f5f5f5; color: #333; border-bottom-left-radius: 4px; }

.sender-name { font-size: 11px; color: #999; display: block; margin-bottom: 2px; }
.msg-content { white-space: pre-wrap; }
.msg-time { font-size: 10px; color: rgba(255,255,255,0.6); display: block; margin-top: 2px; }
.message.staff .msg-time { color: #999; }

.error-tip { font-size: 11px; color: #f44336; background: rgba(244,67,54,0.1); padding: 4px 12px; }

.input-area { padding: 12px; border-top: 1px solid #f0f0f0; display: flex; gap: 8px; align-items: center; }
.input-area input {
  flex: 1; padding: 10px 14px; border: 1px solid #ddd; border-radius: 20px;
  font-size: 14px; outline: none;
}
.input-area input:focus { border-color: #667eea; }
.send-btn {
  width: 40px; height: 40px; border-radius: 50%;
  background: #e0e0e0; color: #fff; border: none;
  display: flex; align-items: center; justify-content: center;
  cursor: not-allowed; transition: all 0.2s;
}
.send-btn.active { background: #667eea; cursor: pointer; }
.send-btn.active:hover { background: #5a6fd6; }

.fab { border-radius: 28px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
.fab-open { padding: 0 16px; height: 48px; background: #667eea; color: #fff; border: none; gap: 8px; cursor: pointer; }
.fab-open:hover { background: #5a6fd6; }
.fab-close { width: 48px; height: 48px; background: #888; color: #fff; border: none; border-radius: 50%; cursor: pointer; }
.fab-close:hover { background: #666; }
.fab-label { font-size: 14px; font-weight: 500; }

.chat-slide-enter-active { transition: all 0.3s ease-out; }
.chat-slide-leave-active { transition: all 0.2s ease-in; }
.chat-slide-enter-from, .chat-slide-leave-to { opacity: 0; transform: translateY(20px) scale(0.95); }
</style>
