import { defineStore } from 'pinia'
import { ref } from 'vue'
import { sendMessage } from '../services/glm.js'
import api from '../services/api.js'

export const useChatStore = defineStore('chat', () => {
  const messages = ref([])
  const isOpen = ref(false)
  const loading = ref(false)
  const apiKey = ref(localStorage.getItem('gdq_glm_key') || '')
  const model = ref(localStorage.getItem('gdq_glm_model') || 'glm-4-flash')

  function setApiKey(key) {
    apiKey.value = key
    localStorage.setItem('gdq_glm_key', key)
  }

  function setModel(m) {
    model.value = m
    localStorage.setItem('gdq_glm_model', m)
  }

  function toggleOpen() {
    isOpen.value = !isOpen.value
  }

  function buildSystemPrompt() {
    return `你是 GDQ 智能仓储管理系统的 AI 助手。请根据用户的问题回答关于库存、预警、审批、出入库等问题。回答简洁准确，使用中文。如果需要查询具体数据，请建议用户查看对应的管理页面。`
  }

  async function send(text) {
    if (!apiKey.value) {
      messages.value.push({
        role: 'assistant',
        content: '请先在系统设置 → AI 助手中配置 GLM API Key，才能使用智能对话功能。',
      })
      return
    }

    messages.value.push({ role: 'user', content: text })

    const systemMsg = { role: 'system', content: buildSystemPrompt() }
    const apiMessages = [systemMsg, ...messages.value]

    messages.value.push({ role: 'assistant', content: '' })
    const assistantIdx = messages.value.length - 1
    loading.value = true

    try {
      await sendMessage(apiMessages, apiKey.value, model.value, (chunk) => {
        messages.value[assistantIdx].content += chunk
      })
    } catch (err) {
      messages.value[assistantIdx].content =
        messages.value[assistantIdx].content || `请求失败: ${err.message}`
    } finally {
      loading.value = false
    }
  }

  function clearMessages() {
    messages.value = []
  }

  return {
    messages, isOpen, loading, apiKey, model,
    setApiKey, setModel, toggleOpen, send, clearMessages,
  }
})
