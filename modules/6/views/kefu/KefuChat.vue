<script setup>
import { ref, nextTick, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useKefuStore } from '../../stores/kefu.js'

const { t } = useI18n()
const kefu = useKefuStore()
const input = ref('')
const search = ref('')
const messagesContainer = ref(null)

const filteredConversations = ref([])

watch(() => [kefu.conversations, search.value], () => {
  const q = search.value.trim().toLowerCase()
  filteredConversations.value = q
    ? kefu.conversations.filter(c => (c.user_name || '').toLowerCase().includes(q))
    : kefu.conversations
}, { immediate: true, deep: true })

function selectConv(id) {
  kefu.selectConversation(id)
  scrollToBottom()
}

function handleSend() {
  const text = input.value.trim()
  if (!text) return
  kefu.sendMsg(text, 'customer')
  input.value = ''
  scrollToBottom()
}

async function handleAiReply() {
  if (kefu.aiLoading) return
  const reply = await kefu.sendWithAi()
  if (reply) input.value = reply
}

function scrollToBottom() {
  nextTick(() => {
    const el = messagesContainer.value
    if (el) el.scrollTop = el.scrollHeight
  })
}

watch(() => kefu.activeMessages.length, scrollToBottom)

function formatTime(time) {
  if (!time) return ''
  const d = new Date(time)
  const now = new Date()
  const diff = now - d
  if (diff < 60000) return '刚刚'
  if (diff < 3600000) return Math.floor(diff / 60000) + '分钟前'
  if (diff < 86400000) return d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0')
  return d.getMonth() + 1 + '/' + d.getDate()
}

onMounted(() => {
  kefu.fetchConversations()
})
</script>

<template>
  <div class="flex h-[calc(100vh-64px)]">
    <!-- Left: Conversation List -->
    <div class="w-80 border-r border-gray-100 flex flex-col bg-white shrink-0">
      <!-- Header -->
      <div class="p-4 border-b border-gray-100">
        <h2 class="font-bold text-lg text-gray-800">客服消息</h2>
      </div>
      
      <!-- Search -->
      <div class="p-3 border-b border-gray-100">
        <div class="relative">
          <span class="material-symbols-outlined text-[18px] text-gray-400 absolute left-3 top-1/2 -translate-y-1/2">search</span>
          <input
            v-model="search"
            type="text"
            placeholder="搜索客户..."
            class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
          />
        </div>
      </div>
      
      <!-- List -->
      <div class="flex-1 overflow-y-auto custom-scrollbar">
        <div
          v-for="conv in filteredConversations"
          :key="conv.id"
          @click="selectConv(conv.id)"
          :class="[
            'flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors border-l-3',
            kefu.activeConversationId === conv.id
              ? 'bg-blue-50 border-primary'
              : 'border-transparent hover:bg-gray-50'
          ]"
        >
          <!-- Avatar -->
          <div class="size-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-sm shrink-0">
            {{ (conv.user_name || '访').charAt(0) }}
          </div>
          
          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-gray-800 truncate">{{ conv.user_name || '访客' }}</span>
              <span class="text-[10px] text-gray-400 shrink-0 ml-2">{{ formatTime(conv.last_time) }}</span>
            </div>
            <div class="flex items-center justify-between mt-0.5">
              <span class="text-xs text-gray-500 truncate">{{ conv.last_message || '暂无消息' }}</span>
              <span
                v-if="conv.unread > 0"
                class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none shrink-0 ml-2"
              >{{ conv.unread }}</span>
            </div>
          </div>
        </div>
        
        <!-- Empty state -->
        <div v-if="filteredConversations.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-400">
          <span class="material-symbols-outlined text-[48px] mb-2">inbox</span>
          <p class="text-sm">暂无会话</p>
        </div>
      </div>
    </div>

    <!-- Right: Chat Area -->
    <div class="flex-1 flex flex-col bg-gray-50/50">
      <!-- Empty State -->
      <div v-if="!kefu.activeConversationId" class="flex-1 flex flex-col items-center justify-center text-center px-4">
        <span class="material-symbols-outlined text-gray-200 text-[64px] mb-4">chat</span>
        <p class="text-gray-400 text-sm">选择一个会话开始聊天</p>
        <p class="text-gray-300 text-xs mt-1">或创建新的客户会话</p>
      </div>

      <!-- Active Chat -->
      <template v-else>
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-3 border-b border-gray-100 bg-white">
          <div class="flex items-center gap-3">
            <div class="size-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-sm">
              {{ (kefu.activeConversation?.user_name || '访').charAt(0) }}
            </div>
            <div>
              <div class="font-medium text-gray-800">{{ kefu.activeConversation?.user_name || '访客' }}</div>
              <div class="text-xs text-gray-400">
                <span v-if="kefu.activeConversation?.user_type === 'logged'" class="text-blue-500">已登录</span>
                <span v-else class="text-gray-400">游客</span>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500">AI 智能回复</span>
            <button
              @click="kefu.aiAutoReply = !kefu.aiAutoReply"
              :class="[
                'relative w-9 h-5 rounded-full transition-colors',
                kefu.aiAutoReply ? 'bg-blue-500' : 'bg-gray-300'
              ]"
            >
              <span :class="[
                'absolute top-0.5 size-4 bg-white rounded-full shadow transition-transform',
                kefu.aiAutoReply ? 'translate-x-4.5' : 'translate-x-0.5'
              ]" />
            </button>
          </div>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
          <div v-for="msg in kefu.activeMessages" :key="msg.id">
            <div :class="['flex', msg.role === 'agent' ? 'justify-end' : 'justify-start']">
              <div class="flex items-end gap-2 max-w-[70%]" :class="msg.role === 'agent' ? 'flex-row-reverse' : ''">
                <div :class="[
                  'size-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0',
                  msg.role === 'agent' ? 'bg-blue-500' : 'bg-gray-400'
                ]">
                  {{ msg.role === 'agent' ? '客' : (kefu.activeConversation?.user_name || '访').charAt(0) }}
                </div>
                <div>
                  <div v-if="msg.role !== 'agent'" class="text-[10px] text-gray-400 mb-1">{{ kefu.activeConversation?.user_name }}</div>
                  <div :class="[
                    'px-4 py-2.5 text-sm leading-relaxed break-words',
                    msg.role === 'agent'
                      ? 'bg-blue-500 text-white rounded-2xl rounded-br-sm'
                      : 'bg-white text-gray-800 rounded-2xl rounded-bl-sm shadow-sm'
                  ]">
                    {{ msg.content }}
                  </div>
                  <div :class="['text-[10px] text-gray-400 mt-1', msg.role === 'agent' ? 'text-right' : '']">{{ formatTime(msg.created_at) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Input Area -->
        <div class="px-6 py-4 border-t border-gray-100 bg-white">
          <div class="flex items-end gap-3">
            <textarea
              v-model="input"
              @keydown.enter.exact.prevent="handleSend"
              placeholder="输入消息..."
              rows="2"
              class="flex-1 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none resize-none"
            />
            <div class="flex flex-col gap-2">
              <button
                @click="handleAiReply"
                :disabled="kefu.aiLoading"
                :class="[
                  'flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                  kefu.aiLoading
                    ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                    : 'bg-amber-50 text-amber-600 hover:bg-amber-100'
                ]"
              >
                <span class="material-symbols-outlined text-[16px]">smart_toy</span>
                {{ kefu.aiLoading ? '生成中...' : 'AI 助手' }}
              </button>
              <button
                @click="handleSend"
                :disabled="!input.trim()"
                :class="[
                  'flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                  input.trim()
                    ? 'bg-blue-500 hover:bg-blue-600 text-white'
                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                ]"
              >
                <span class="material-symbols-outlined text-[16px]">send</span>
                发送
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.border-l-3 {
  border-left-width: 3px;
}
</style>