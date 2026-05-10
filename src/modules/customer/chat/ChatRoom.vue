<template>
  <div class="chat-room">
    <!-- 头部 -->
    <div class="chat-header">
      <div class="header-avatar">🐟</div>
      <div class="header-info">
        <h1>江小鱼客服</h1>
        <p>在线</p>
      </div>
    </div>

    <!-- 登录提示 -->
    <div v-if="!userId && !guestSession" class="login-prompt">
      <h2>欢迎使用彩美特客服</h2>
      <p class="subtitle">登录后可查看聊天记录</p>
      <button class="login-btn" @click="goLogin">立即登录</button>
    </div>

    <div v-else>
      <!-- 游客提示 -->
      <div v-if="!userId" class="guest-tip">游客模式 · 登录后可查看历史记录</div>

      <!-- 消息列表 -->
      <div ref="msgList" class="messages">
        <div v-for="(msg, i) in messages" :key="i" :class="['message', msg.role]">
          <div class="avatar">{{ msg.role === 'user' ? '👤' : '🐟' }}</div>
          <div class="bubble">{{ msg.content }}</div>
        </div>
        <div v-if="loading" class="typing">正在输入...</div>
      </div>

      <!-- 输入区 -->
      <div class="input-area">
        <input v-model="inputText" @keyup.enter="send" placeholder="请输入消息..." />
        <button @click="send" :disabled="!inputText.trim() || loading">➤</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const userId = ref(null)
const guestSession = ref(localStorage.getItem('guest_session') || null)
const messages = ref([
  { role: 'assistant', content: '你好！我是江小鱼，彩美特智能客服~ 有什么可以帮你的？' }
])
const inputText = ref('')
const loading = ref(false)
const msgList = ref(null)
const roomId = ref(null)

const h5User = JSON.parse(localStorage.getItem('h5_user') || 'null')
if (h5User?.id) userId.value = h5User.id

if (!guestSession.value) {
  guestSession.value = 'guest_' + Date.now()
  localStorage.setItem('guest_session', guestSession.value)
}

function goLogin() {
  router.push('/h5/login?redirect=/chat/')
}

async function initRoom() {
  if (!userId.value && !guestSession.value) return
  try {
    const res = await fetch('/api/customer-chat/room', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ userId: userId.value, sessionId: guestSession.value })
    })
    const data = await res.json()
    if (data.code === 0) {
      roomId.value = data.data.id
      loadHistory()
    }
  } catch (e) { console.error(e) }
}

async function loadHistory() {
  if (!roomId.value) return
  try {
    const res = await fetch(`/api/customer-chat/messages/${roomId.value}`)
    const data = await res.json()
    if (data.code === 0 && data.data) {
      messages.value = data.data.map(m => ({ role: m.role, content: m.content, time: m.created_at }))
    }
  } catch (e) { console.error(e) }
}

async function send() {
  const text = inputText.value.trim()
  if (!text || loading.value || !roomId.value) return

  messages.value.push({ role: 'user', content: text })
  inputText.value = ''
  loading.value = true

  try {
    // 保存用户消息
    await fetch('/api/customer-chat/messages', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        roomId: roomId.value,
        userId: userId.value,
        sessionId: guestSession.value,
        role: 'user',
        content: text
      })
    })

    // 调用江小鱼
    const res = await fetch('http://10.3.0.14:8080/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, userId: userId.value || guestSession.value })
    })
    const data = await res.json()
    const reply = data.response || data.content || '抱歉，请稍后再试'

    messages.value.push({ role: 'assistant', content: reply })

    // 保存助手消息
    await fetch('/api/customer-chat/messages', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        roomId: roomId.value,
        userId: userId.value,
        sessionId: guestSession.value,
        role: 'assistant',
        content: reply
      })
    })
  } catch (e) {
    messages.value.push({ role: 'assistant', content: '连接失败，请稍后重试' })
  } finally {
    loading.value = false
    nextTick(() => {
      if (msgList.value) msgList.value.scrollTop = msgList.value.scrollHeight
    })
  }
}

onMounted(() => {
  initRoom()
})
</script>

<style scoped>
.chat-room { height: 100vh; display: flex; flex-direction: column; background: #f5f5f5; }

.chat-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff; padding: 16px; display: flex; align-items: center; gap: 12px;
}
.header-avatar { width: 40px; height: 40px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 24px; }
.header-info h1 { font-size: 16px; font-weight: 600; }
.header-info p { font-size: 12px; opacity: 0.8; }

.login-prompt { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; text-align: center; }
.login-prompt h2 { font-size: 20px; color: #333; }
.subtitle { color: #666; margin-top: 8px; }
.login-btn { background: #667eea; color: #fff; border: none; padding: 12px 32px; border-radius: 24px; font-size: 14px; cursor: pointer; margin-top: 16px; }
.login-btn:hover { background: #5a6fd6; }

.guest-tip { background: #fff3cd; color: #856404; font-size: 12px; padding: 8px 16px; text-align: center; }

.messages { flex: 1; overflow-y: auto; padding: 16px; padding-bottom: 80px; display: flex; flex-direction: column; gap: 16px; }

.message { display: flex; align-items: flex-end; }
.message.user { flex-direction: row-reverse; }
.avatar { width: 36px; height: 36px; border-radius: 50%; background: #667eea; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; margin: 0 8px; }
.message.user .avatar { background: #4CAF50; order: 1; margin: 0; }

.bubble { max-width: 75%; padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.5; word-wrap: break-word; }
.message.assistant .bubble { background: #fff; color: #333; border-bottom-left-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.message.user .bubble { background: #667eea; color: #fff; border-bottom-right-radius: 4px; }

.typing { color: #999; font-size: 12px; padding: 0 16px; }

.input-area {
  position: fixed; bottom: 0; left: 0; right: 0;
  background: #fff; padding: 12px 16px; border-top: 1px solid #eee;
  display: flex; gap: 8px; align-items: center;
}
.input-area input {
  flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 24px;
  outline: none; font-size: 14px;
}
.input-area input:focus { border-color: #667eea; }
.input-area button { width: 44px; height: 44px; border-radius: 50%; background: #667eea; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.input-area button:disabled { background: #ccc; cursor: not-allowed; }
</style>
