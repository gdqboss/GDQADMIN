import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api.js'

export const useKefuStore = defineStore('kefu', () => {
  const conversations = ref([])
  const messages = ref({})
  const activeConversationId = ref(null)
  const aiAutoReply = ref(false)
  const aiLoading = ref(false)

  const totalUnread = computed(() =>
    conversations.value.reduce((sum, c) => sum + (c.unread || 0), 0)
  )

  const activeConversation = computed(() =>
    conversations.value.find(c => c.id === activeConversationId.value)
  )

  const activeMessages = computed(() =>
    messages.value[activeConversationId.value] || []
  )

  async function fetchConversations() {
    try {
      const res = await api.get('/kefu/conversations')
      if (res.code === 0) {
        conversations.value = res.data
      }
    } catch (err) {
      console.error('获取会话列表失败:', err)
    }
  }

  async function selectConversation(id) {
    activeConversationId.value = id
    try {
      const res = await api.get(`/kefu/messages/${id}`)
      if (res.code === 0) {
        messages.value[id] = res.data
      }
      const conv = conversations.value.find(c => c.id === id)
      if (conv) conv.unread = 0
    } catch (err) {
      console.error('获取消息失败:', err)
    }
  }

  async function sendMsg(text, role = 'customer') {
    if (!activeConversationId.value || !text.trim()) return
    const convId = activeConversationId.value
    
    try {
      // 添加本地消息
      if (!messages.value[convId]) {
        messages.value[convId] = []
      }
      
      // 发送消息
      await api.post('/kefu/messages', { 
        conversation_id: convId, 
        content: text.trim(),
        role 
      })
      
      // 刷新消息
      const res = await api.get(`/kefu/messages/${convId}`)
      if (res.code === 0) {
        messages.value[convId] = res.data
      }
      
      // 更新会话最后消息
      const conv = conversations.value.find(c => c.id === convId)
      if (conv) {
        conv.last_message = text.trim()
        conv.last_time = new Date().toISOString()
      }
    } catch (err) {
      console.error('发送消息失败:', err)
    }
  }

  async function sendWithAi() {
    const msgList = activeMessages.value
    if (!msgList || msgList.length === 0) return

    const context = msgList.slice(-5).map(m => ({
      role: m.role === 'customer' ? 'user' : 'assistant',
      content: m.content
    }))

    const lastMsg = msgList[msgList.length - 1]
    if (!lastMsg) return

    aiLoading.value = true
    let result = ''
    
    try {
      const res = await api.post('/kefu/ai-reply', {
        message: lastMsg.content,
        context
      })
      if (res.code === 0) {
        result = res.data.reply
      } else {
        result = res.message || '生成失败'
      }
    } catch (err) {
      result = `生成失败: ${err.message}`
    } finally {
      aiLoading.value = false
    }
    
    return result
  }

  async function createConversation(userInfo = {}) {
    try {
      const res = await api.post('/kefu/conversations', {
        user_id: userInfo.id || null,
        user_name: userInfo.name || '访客',
        user_type: userInfo.type || 'customer'
      })
      if (res.code === 0) {
        await fetchConversations()
        return res.data
      }
    } catch (err) {
      console.error('创建会话失败:', err)
    }
    return null
  }

  return {
    conversations, messages, activeConversationId, aiAutoReply, aiLoading,
    totalUnread, activeConversation, activeMessages,
    fetchConversations, selectConversation, sendMsg, sendWithAi, createConversation,
  }
})