import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { sendMessage } from '../services/glm.js'
import { useChatStore } from './chat.js'
import api from '../services/api.js'

export const useWecomStore = defineStore('wecom', () => {
  const conversations = ref([])
  const messages = ref({})
  const activeConversationId = ref(null)
  const aiAutoReply = ref(false)
  const aiLoading = ref(false)

  const corpId = ref(localStorage.getItem('gdq_wecom_corpid') || '')
  const agentId = ref(localStorage.getItem('gdq_wecom_agentid') || '')
  const secret = ref(localStorage.getItem('gdq_wecom_secret') || '')

  const totalUnread = computed(() =>
    conversations.value.reduce((sum, c) => sum + c.unread, 0)
  )

  const activeConversation = computed(() =>
    conversations.value.find(c => c.id === activeConversationId.value)
  )

  const activeMessages = computed(() =>
    messages.value[activeConversationId.value] || []
  )

  async function fetchConversations() {
    const res = await api.get('/wecom/conversations')
    if (res.code === 0) conversations.value = res.data
  }

  async function selectConversation(id) {
    activeConversationId.value = id
    const res = await api.get(`/wecom/messages/${id}`)
    if (res.code === 0) messages.value[id] = res.data
    const conv = conversations.value.find(c => c.id === id)
    if (conv) conv.unread = 0
  }

  async function sendMsg(text) {
    if (!activeConversationId.value || !text.trim()) return
    const convId = activeConversationId.value
    await api.post('/wecom/messages', { conversation_id: convId, content: text.trim() })
    const res = await api.get(`/wecom/messages/${convId}`)
    if (res.code === 0) messages.value[convId] = res.data
    const conv = conversations.value.find(c => c.id === convId)
    if (conv) {
      conv.lastMessage = text.trim()
      conv.last_time = new Date().toISOString()
    }
  }

  async function sendWithAi() {
    if (!activeConversationId.value) return
    const chatStore = useChatStore()
    if (!chatStore.apiKey) return '请先在系统设置 → AI 助手中配置 GLM API Key'

    const convId = activeConversationId.value
    const msgList = messages.value[convId] || []
    const recentMessages = msgList.slice(-5)
    const conv = activeConversation.value

    const systemPrompt = `你是一个企业微信智能回复助手，帮助用户回复工作消息。根据对话上下文生成简洁、专业的回复建议。直接给出回复内容，不要加任何前缀说明。`
    const contextMsg = recentMessages.map(m =>
      `${m.is_self || m.isSelf ? '我' : m.sender}: ${m.content}`
    ).join('\n')

    const apiMessages = [
      { role: 'system', content: systemPrompt },
      { role: 'user', content: `以下是与「${conv?.name}」的最近对话：\n${contextMsg}\n\n请生成一条合适的回复：` },
    ]

    aiLoading.value = true
    let result = ''
    try {
      await sendMessage(apiMessages, chatStore.apiKey, chatStore.model, (chunk) => {
        result += chunk
      })
    } catch (err) {
      result = `生成失败: ${err.message}`
    } finally {
      aiLoading.value = false
    }
    return result
  }

  function saveConfig(config) {
    corpId.value = config.corpId
    agentId.value = config.agentId
    secret.value = config.secret
    localStorage.setItem('gdq_wecom_corpid', config.corpId)
    localStorage.setItem('gdq_wecom_agentid', config.agentId)
    localStorage.setItem('gdq_wecom_secret', config.secret)
  }

  return {
    conversations, messages, activeConversationId, aiAutoReply, aiLoading,
    corpId, agentId, secret,
    totalUnread, activeConversation, activeMessages,
    fetchConversations, selectConversation, sendMsg, sendWithAi, saveConfig,
  }
})
