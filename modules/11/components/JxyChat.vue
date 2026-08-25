<template>
  <div class="fixed bottom-20 right-4 z-50">
    <button v-if="!isOpen" @click="isOpen = true" class="w-14 h-14 bg-primary rounded-full shadow-lg flex items-center justify-center text-2xl hover:scale-110 transition-transform">💬</button>
    <div v-else class="w-80 h-96 bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden">
      <div class="bg-primary px-4 py-3 flex items-center gap-2">
        <span class="text-xl">🐟</span>
        <span class="text-white font-medium">客服小江</span>
        <button @click="isOpen = false" class="ml-auto text-white/80 hover:text-white">✕</button>
      </div>
      <div ref="msgList" class="flex-1 overflow-y-auto p-3 space-y-2 bg-gray-50">
        <div v-for="(msg, i) in messages" :key="i" :class="['max-w-[80%] p-2 rounded-lg text-sm', msg.role === 'user' ? 'ml-auto bg-primary text-white' : 'mr-auto bg-gray-200 text-gray-800']">{{ msg.content }}</div>
        <div v-if="loading" class="text-xs text-gray-400">正在输入...</div>
      </div>
      <div class="p-3 border-t bg-white flex gap-2">
        <input v-model="input" @keyup.enter="send" placeholder="请输入..." class="flex-1 px-3 py-2 border rounded-lg text-sm focus:outline-none focus:border-primary" />
        <button @click="send" :disabled="!input.trim() || loading" class="px-4 py-2 bg-primary text-white rounded-lg text-sm disabled:opacity-50">发送</button>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, nextTick } from 'vue'
const isOpen = ref(false)
const input = ref('')
const messages = ref([{ role: 'assistant', content: '你好！有什么可以帮你的？' }])
const loading = ref(false)
const msgList = ref(null)
async function send() {
  const text = input.value.trim()
  if (!text || loading.value) return
  messages.value.push({ role: 'user', content: text })
  input.value = ''
  loading.value = true
  try {
    const res = await fetch('http://10.3.0.14:8080/chat', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ message: text, userId: 'scan-' + Date.now() }) })
    const data = await res.json()
    messages.value.push({ role: 'assistant', content: data.response || data.content || '抱歉，请稍后再试' })
  } catch (e) { messages.value.push({ role: 'assistant', content: '连接超时，请稍后再试' }) }
  finally { loading.value = false; nextTick(() => { msgList.value?.scrollTo(0, msgList.value.scrollHeight) }) }
}
</script>
