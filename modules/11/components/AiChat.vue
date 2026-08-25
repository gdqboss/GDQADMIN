<script setup>
import { ref, nextTick, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useChatStore } from '../stores/chat.js'

const { t } = useI18n()
const chat = useChatStore()
const input = ref('')
const messagesContainer = ref(null)

const quickActions = computed(() => [
  { label: t('aiChat.stockAlerts'), text: t('aiChat.stockAlertsQuery') },
  { label: t('aiChat.pendingApprovals'), text: t('aiChat.pendingApprovalsQuery') },
  { label: t('aiChat.todayInOut'), text: t('aiChat.todayInOutQuery') },
  { label: t('aiChat.warehouseOverview'), text: t('aiChat.warehouseOverviewQuery') },
])

function handleSend() {
  const text = input.value.trim()
  if (!text || chat.loading) return
  input.value = ''
  chat.send(text)
}

function handleQuickAction(action) {
  if (chat.loading) return
  chat.send(action.text)
}

function scrollToBottom() {
  nextTick(() => {
    const el = messagesContainer.value
    if (el) el.scrollTop = el.scrollHeight
  })
}

watch(() => chat.messages.length, scrollToBottom)
watch(() => chat.messages[chat.messages.length - 1]?.content, scrollToBottom)
</script>

<template>
  <div class="fixed bottom-6 right-6 z-50">
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
        v-if="chat.isOpen"
        class="absolute bottom-16 right-0 w-[400px] h-[560px] bg-white rounded-xl border border-gray-100 shadow-card-hover flex flex-col overflow-hidden"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-white">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[22px]">smart_toy</span>
            <span class="font-bold text-text-primary text-sm">{{ $t('aiChat.title') }}</span>
          </div>
          <div class="flex items-center gap-1">
            <button
              @click="chat.clearMessages()"
              class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors text-text-secondary"
              :title="$t('aiChat.clearChat')"
            >
              <span class="material-symbols-outlined text-[18px]">delete_sweep</span>
            </button>
            <button
              @click="chat.toggleOpen()"
              class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors text-text-secondary"
              :title="$t('aiChat.minimize')"
            >
              <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
          </div>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
          <!-- Welcome message when empty -->
          <div v-if="chat.messages.length === 0" class="flex flex-col items-center justify-center h-full text-center px-4">
            <span class="material-symbols-outlined text-primary/30 text-[48px] mb-3">smart_toy</span>
            <p class="text-sm font-medium text-text-primary mb-1">{{ $t('aiChat.welcomeTitle') }}</p>
            <p class="text-xs text-text-secondary">{{ $t('aiChat.welcomeSubtitle') }}</p>
          </div>

          <!-- Message bubbles -->
          <div
            v-for="(msg, idx) in chat.messages"
            :key="idx"
            :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']"
          >
            <div
              :class="[
                'max-w-[85%] px-3 py-2 text-sm leading-relaxed whitespace-pre-wrap break-words',
                msg.role === 'user'
                  ? 'bg-primary text-white rounded-2xl rounded-br-sm'
                  : 'bg-gray-50 text-text-primary rounded-2xl rounded-bl-sm'
              ]"
            >
              {{ msg.content }}
              <span
                v-if="msg.role === 'assistant' && chat.loading && idx === chat.messages.length - 1 && !msg.content"
                class="inline-block w-1.5 h-4 bg-text-secondary/40 rounded-sm animate-pulse ml-0.5 align-middle"
              />
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div v-if="chat.messages.length === 0" class="px-4 pb-2">
          <div class="flex gap-2 overflow-x-auto pb-1 custom-scrollbar">
            <button
              v-for="action in quickActions"
              :key="action.label"
              @click="handleQuickAction(action)"
              class="bg-primary/10 text-primary hover:bg-primary hover:text-white rounded-full px-3 py-1 text-xs font-medium transition-colors whitespace-nowrap shrink-0"
            >
              {{ action.label }}
            </button>
          </div>
        </div>

        <!-- Input -->
        <div class="px-4 py-3 border-t border-gray-100 bg-white">
          <div class="flex items-center gap-2">
            <input
              v-model="input"
              @keydown.enter="handleSend"
              type="text"
              :placeholder="$t('aiChat.inputPlaceholder')"
              class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              :disabled="chat.loading"
            />
            <button
              @click="handleSend"
              :disabled="!input.trim() || chat.loading"
              :class="[
                'p-2 rounded-lg transition-colors',
                input.trim() && !chat.loading
                  ? 'bg-primary hover:bg-primary-hover text-white'
                  : 'bg-gray-100 text-text-secondary cursor-not-allowed'
              ]"
            >
              <span class="material-symbols-outlined text-[20px]">send</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- FAB Button -->
    <button
      @click="chat.toggleOpen()"
      :class="[
        'w-12 h-12 rounded-full shadow-card-hover flex items-center justify-center transition-all duration-300',
        chat.isOpen
          ? 'bg-gray-500 hover:bg-gray-600 rotate-0'
          : 'bg-primary hover:bg-primary-hover rotate-0'
      ]"
    >
      <span class="material-symbols-outlined text-white text-[24px]">
        {{ chat.isOpen ? 'close' : 'smart_toy' }}
      </span>
    </button>
  </div>
</template>
