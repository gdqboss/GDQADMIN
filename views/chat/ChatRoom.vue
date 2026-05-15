<template>
  <div class="h-full flex bg-gray-50">
    <!-- 左侧列表 -->
    <div class="w-64 bg-white border-r flex flex-col">
      <div class="p-4 border-b">
        <h2 class="font-bold">消息</h2>
      </div>
      <div class="flex-1 overflow-y-auto">
        <!-- 大厅 -->
        <div @click="selectChat('hall')" 
          :class="['p-3 cursor-pointer flex items-center gap-2', currentChat === 'hall' ? 'bg-blue-50 border-r-2 border-blue-500' : 'hover:bg-gray-50']">
          <span>📢</span>
          <span>大厅</span>
        </div>
        <!-- 私聊列表 -->
        <div class="px-3 py-2 text-xs text-gray-500 border-t mt-2">私聊</div>
        <div v-for="user in users" :key="user.id" @click="selectChat('u-' + user.id)"
          :class="['p-3 cursor-pointer flex items-center gap-2', currentChat === 'u-' + user.id ? 'bg-blue-50 border-r-2 border-blue-500' : 'hover:bg-gray-50']">
          <span class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center text-xs">
            {{ user.name?.charAt(0) || '?' }}
          </span>
          <span class="text-sm">{{ user.name || '用户' + user.id }}</span>
        </div>
      </div>
    </div>
    
    <!-- 右侧聊天区 -->
    <div class="flex-1 flex flex-col">
      <!-- 标题 -->
      <div class="bg-white px-6 py-4 border-b">
        <h1 class="text-xl font-bold">{{ currentChat === 'hall' ? '📢 大厅' : '👤 私聊' }}</h1>
      </div>
      
      <!-- 消息列表 -->
      <div ref="msgList" class="flex-1 overflow-y-auto p-4 space-y-3">
        <div v-for="(msg, i) in currentMessages" :key="i"
          :class="['max-w-[80%] p-3 rounded-lg', 
            msg.isMine ? 'ml-auto bg-primary text-white' : 'mr-auto bg-white text-gray-800 shadow']">
          <div v-if="currentChat === 'hall' && !msg.isMine" class="text-xs text-gray-500 mb-1">{{ msg.from }}</div>
          <div class="text-sm">{{ msg.content }}</div>
          <div class="text-xs opacity-60 mt-1">{{ formatTime(msg.time) }}</div>
        </div>
        <div v-if="loading" class="text-sm text-gray-400">正在输入...</div>
      </div>
      
      <!-- 输入框 -->
      <div class="bg-white p-4 border-t">
        <div class="flex gap-2">
          <input v-model="input" @keyup.enter="send" placeholder="请输入消息..." 
            class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:border-primary" />
          <button @click="send" :disabled="!input.trim() || loading"
            class="px-6 py-2 bg-primary text-white rounded-lg disabled:opacity-50">
            发送
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import api from '../../services/api.js'

const currentChat = ref('hall')
const input = ref('')
const loading = ref(false)
const msgList = ref(null)
const myId = ref(0)
const myName = ref('')
const users = ref([])

// 大厅消息
const hallMessages = ref([
  { from: '江小鱼', content: '大家好！欢迎来到大厅~', time: new Date().toISOString(), isMine: false }
])

// 私聊消息
const privateMessages = ref({})

const currentMessages = computed(() => {
  return currentChat.value === 'hall' ? hallMessages.value : (privateMessages.value[currentChat.value] || [])
})

function formatTime(time) {
  const d = new Date(time)
  return d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0')
}

function selectChat(id) {
  currentChat.value = id
}

async function loadUsers() {
  try {
    const res = await api.get('/users/list')
    if (res.code === 0 && res.data) {
      users.value = res.data.filter(u => u.id !== myId.value)
    }
  } catch (e) {
    console.error('加载用户失败', e)
  }
}

async function send() {
  const text = input.value.trim()
  if (!text || loading.value) return
  
  const msgKey = currentChat.value === 'hall' ? 'hall' : currentChat.value
  
  const msg = { content: text, time: new Date().toISOString(), isMine: true }
  
  if (currentChat.value === 'hall') {
    hallMessages.value.push(msg)
  } else {
    if (!privateMessages.value[currentChat.value]) {
      privateMessages.value[currentChat.value] = []
    }
    privateMessages.value[currentChat.value].push(msg)
  }
  
  input.value = ''
  loading.value = true
  
  try {
    const userId = currentChat.value === 'hall' ? 'hall' : ('private-' + currentChat.value)
    const res = await fetch('http://10.3.0.14:8080/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, userId: userId })
    })
    const data = await res.json()
    const reply = { content: data.response || data.content || '收到', time: new Date().toISOString(), isMine: false }
    
    if (currentChat.value === 'hall') {
      hallMessages.value.push(reply)
    } else {
      privateMessages.value[currentChat.value].push(reply)
    }
  } catch (e) {
    // 失败不提示
  } finally {
    loading.value = false
    nextTick(() => {
      msgList.value?.scrollTo(0, msgList.value.scrollHeight)
    })
  }
}

onMounted(() => {
  const user = JSON.parse(localStorage.getItem('gdq_user') || '{}')
  myId.value = user.id
  myName.value = user.name
  loadUsers()
})
</script>
