<script setup>
import { ref, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useWecomStore } from '../../stores/wecom.js'

const { t } = useI18n()
const wecom = useWecomStore()
const input = ref('')
const search = ref('')
const messagesContainer = ref(null)

const filteredConversations = ref([])
watch(() => [wecom.conversations, search.value], () => {
  const q = search.value.trim().toLowerCase()
  filteredConversations.value = q
    ? wecom.conversations.filter(c => c.name.toLowerCase().includes(q))
    : wecom.conversations
}, { immediate: true, deep: true })

function selectConv(id) {
  wecom.selectConversation(id)
  scrollToBottom()
}

function handleSend() {
  const text = input.value.trim()
  if (!text) return
  wecom.sendMsg(text)
  input.value = ''
  scrollToBottom()
}

async function handleAiReply() {
  if (wecom.aiLoading) return
  const reply = await wecom.sendWithAi()
  if (reply) input.value = reply
}

function scrollToBottom() {
  nextTick(() => {
    const el = messagesContainer.value
    if (el) el.scrollTop = el.scrollHeight
  })
}

watch(() => wecom.activeMessages.length, scrollToBottom)
</script>

<template>
  <div class="flex h-[calc(100vh-64px)]">
    <!-- Left: Conversation List -->
    <div class="w-80 border-r border-gray-100 flex flex-col bg-white shrink-0">
      <!-- Search -->
      <div class="p-3 border-b border-gray-100">
        <div class="relative">
          <span class="material-symbols-outlined text-[18px] text-text-secondary absolute left-3 top-1/2 -translate-y-1/2">search</span>
          <input
            v-model="search"
            type="text"
            :placeholder="$t('wecom.searchContact')"
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
            'flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors border-l-2',
            wecom.activeConversationId === conv.id
              ? 'bg-primary/5 border-primary'
              : 'border-transparent hover:bg-gray-50'
          ]"
        >
          <!-- Avatar -->
          <div :class="[
            'size-10 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0',
            conv.type === 'group' ? 'bg-green-500' : 'bg-primary'
          ]">
            {{ conv.name.charAt(0) }}
          </div>
          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-text-primary truncate">{{ conv.name }}</span>
              <span class="text-[10px] text-text-secondary shrink-0 ml-2">{{ conv.lastTime }}</span>
            </div>
            <div class="flex items-center justify-between mt-0.5">
              <span class="text-xs text-text-secondary truncate">{{ conv.lastMessage }}</span>
              <span
                v-if="conv.unread > 0"
                class="bg-danger text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none shrink-0 ml-2"
              >{{ conv.unread }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: Chat Area -->
    <div class="flex-1 flex flex-col bg-gray-50/50">
      <!-- Empty State -->
      <div v-if="!wecom.activeConversationId" class="flex-1 flex flex-col items-center justify-center text-center px-4">
        <span class="material-symbols-outlined text-gray-200 text-[64px] mb-4">chat</span>
        <p class="text-text-secondary text-sm">{{ $t('wecom.selectConversation') }}</p>
        <p class="text-text-secondary/60 text-xs mt-1">{{ $t('wecom.messagesHere') }}</p>
      </div>

      <!-- Active Chat -->
      <template v-else>
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-3 border-b border-gray-100 bg-white">
          <div class="flex items-center gap-2">
            <span class="font-bold text-text-primary">{{ wecom.activeConversation?.name }}</span>
            <span v-if="wecom.activeConversation?.type === 'group'" class="text-[10px] text-text-secondary bg-gray-100 px-1.5 py-0.5 rounded">{{ $t('wecom.groupChat') }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs text-text-secondary">{{ $t('wecom.aiAutoReply') }}</span>
            <button
              @click="wecom.aiAutoReply = !wecom.aiAutoReply"
              :class="[
                'relative w-9 h-5 rounded-full transition-colors',
                wecom.aiAutoReply ? 'bg-primary' : 'bg-gray-300'
              ]"
            >
              <span :class="[
                'absolute top-0.5 size-4 bg-white rounded-full shadow transition-transform',
                wecom.aiAutoReply ? 'translate-x-4.5' : 'translate-x-0.5'
              ]" />
            </button>
          </div>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
          <div v-for="msg in wecom.activeMessages" :key="msg.id">
            <div :class="['flex', msg.isSelf ? 'justify-end' : 'justify-start']">
              <div class="flex items-end gap-2 max-w-[70%]" :class="msg.isSelf ? 'flex-row-reverse' : ''">
                <div :class="[
                  'size-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0',
                  msg.isSelf ? 'bg-primary' : 'bg-green-500'
                ]">
                  {{ msg.sender.charAt(0) }}
                </div>
                <div>
                  <div v-if="!msg.isSelf" class="text-[10px] text-text-secondary mb-1">{{ msg.sender }}</div>
                  <div :class="[
                    'px-3 py-2 text-sm leading-relaxed break-words',
                    msg.isSelf
                      ? 'bg-primary text-white rounded-2xl rounded-br-sm'
                      : 'bg-white text-text-primary rounded-2xl rounded-bl-sm shadow-card'
                  ]">
                    {{ msg.content }}
                  </div>
                  <div :class="['text-[10px] text-text-secondary mt-1', msg.isSelf ? 'text-right' : '']">{{ msg.time }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Input Area -->
        <div class="px-6 py-3 border-t border-gray-100 bg-white">
          <div class="flex items-end gap-2">
            <textarea
              v-model="input"
              @keydown.enter.exact.prevent="handleSend"
              :placeholder="$t('wecom.inputMessage')"
              rows="2"
              class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"
            />
            <div class="flex flex-col gap-1.5">
              <button
                @click="handleAiReply"
                :disabled="wecom.aiLoading"
                :class="[
                  'flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                  wecom.aiLoading
                    ? 'bg-gray-100 text-text-secondary cursor-not-allowed'
                    : 'bg-amber-50 text-amber-600 hover:bg-amber-100'
                ]"
              >
                <span class="material-symbols-outlined text-[14px]">smart_toy</span>
                {{ wecom.aiLoading ? $t('wecom.generating') : $t('wecom.aiReply') }}
              </button>
              <button
                @click="handleSend"
                :disabled="!input.trim()"
                :class="[
                  'flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                  input.trim()
                    ? 'bg-primary hover:bg-primary-hover text-white'
                    : 'bg-gray-100 text-text-secondary cursor-not-allowed'
                ]"
              >
                <span class="material-symbols-outlined text-[14px]">send</span>
                {{ $t('common.send') }}
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
