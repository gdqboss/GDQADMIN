<script setup>
import { ref, onMounted, nextTick, computed } from 'vue'
import { marked } from 'marked'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../stores/user'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../services/api.js'

const userStore = useUserStore()
const { t } = useI18n()
const currentUser = computed(() => userStore.user)

// Tab 可见性：知识库管理 / 记忆管理（统一走 userStore.canAccess）
const showKnowledgeTab = computed(() => userStore.canAccess('knowledge-base'))
const showMemoryTab = computed(() => userStore.canAccess('memory-management'))

// ─── Tab ─────────────────────────────────────────────────────────────────────
const activeTab = ref('chat')

// ─── Tab1: AI对话 ──────────────────────────────────────────────────────────────
const messages = ref([])
const inputMessage = ref('')
const sending = ref(false)
const awaitingReply = ref(false) // 等待 AI 回复中提示
const chatContainer = ref(null)
const conversationLoaded = ref(false)
const streamingIndex = ref(null) // index of message being typed

// ==================== 🎤 语音输入（STT）===================
// 三模态自动适配策略（2026-07-26 HK 上线扩展）：
//  1) 浏览器原生 SpeechRecognition（webkitSpeechRecognition / SpeechRecognition）— 首选，零成本零 key
//  2) MediaRecorder → /api/ai-class/asr（服务端 Whisper）— 后端 OPENAI_API_KEY 有真值时才用
//  3) 都不支持时按钮 disabled + tooltip 提示
// 优先级在 onMounted() 里探测一次后定下来（不每次录音切模式，体验跳跃）
const isRecording = ref(false)
const isTranscribing = ref(false)
const mediaRecorder = ref(null)
const audioChunks = ref([])
let recordingStartAt = 0
// 三种语音输入模式
const STT_MODES = { WEB_SPEECH: 'web_speech', MEDIA_RECORDER: 'media_recorder', UNAVAILABLE: 'unavailable' }
const sttMode = ref(STT_MODES.UNAVAILABLE) // 默认 unavailable, onMounted() 里探测
const webSpeechRecognition = ref(null) // SpeechRecognition 实例（如果走 web_speech 模式）

// 探测浏览器能力，给 sttMode 赋值（在组件 onMounted 时跑一次）
const detectSTTMode = () => {
  const RecCtor = window.SpeechRecognition || window.webkitSpeechRecognition
  if (RecCtor) {
    sttMode.value = STT_MODES.WEB_SPEECH
    return
  }
  if (navigator.mediaDevices && window.MediaRecorder) {
    sttMode.value = STT_MODES.MEDIA_RECORDER
    return
  }
  sttMode.value = STT_MODES.UNAVAILABLE
}

const startRecording = async () => {
  if (isRecording.value || isTranscribing.value) return

  // 模式 A: 浏览器原生 Web Speech API（零 key,优先）
  if (sttMode.value === STT_MODES.WEB_SPEECH) {
    const RecCtor = window.SpeechRecognition || window.webkitSpeechRecognition
    if (!RecCtor) return
    const rec = new RecCtor()
    rec.lang = 'zh-CN'
    rec.interimResults = false
    rec.maxAlternatives = 1
    rec.onresult = (e) => {
      const text = e.results[0][0].transcript
      if (text) {
        inputMessage.value = (inputMessage.value ? inputMessage.value + ' ' : '') + text.trim()
        ElMessage.success('识别成功')
      }
    }
    rec.onerror = (e) => {
      console.warn('[AI课堂] Web Speech error:', e.error)
      if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
        ElMessage.error('麦克风权限被拒绝')
      } else if (e.error === 'no-speech') {
        ElMessage.warning('没听到声音，请重试')
      } else {
        ElMessage.warning('语音识别失败：' + (e.error || '未知错误'))
      }
    }
    rec.onend = () => { isRecording.value = false }
    try {
      rec.start()
      webSpeechRecognition.value = rec
      isRecording.value = true
    } catch (err) {
      console.error('[AI课堂] Web Speech start failed:', err)
      ElMessage.error('无法启动语音识别：' + (err.message || '浏览器不支持'))
    }
    return
  }

  // 模式 B: MediaRecorder + 后端 ASR（需要服务端 OPENAI_API_KEY）
  if (sttMode.value === STT_MODES.MEDIA_RECORDER) {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
        ? 'audio/webm;codecs=opus'
        : (MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4' : '')
      mediaRecorder.value = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream)
      audioChunks.value = []
      mediaRecorder.value.ondataavailable = (e) => {
        if (e.data.size > 0) audioChunks.value.push(e.data)
      }
      mediaRecorder.value.onstop = async () => {
        stream.getTracks().forEach(t => t.stop())
        await transcribeRecording()
      }
      recordingStartAt = Date.now()
      mediaRecorder.value.start()
      isRecording.value = true
    } catch (err) {
      console.error('[AI课堂] 麦克风权限失败:', err)
      ElMessage.error('无法访问麦克风：' + (err.message || '请检查浏览器权限'))
    }
    return
  }

  // 模式 C: 啥都不支持
  ElMessage.warning('当前浏览器不支持语音输入，请用 Chrome/Edge/Safari')
}

const stopRecording = () => {
  // 模式 A: Web Speech API
  if (sttMode.value === STT_MODES.WEB_SPEECH && webSpeechRecognition.value) {
    try { webSpeechRecognition.value.stop() } catch (e) { /* noop */ }
    webSpeechRecognition.value = null
    return
  }
  // 模式 B: MediaRecorder
  if (!isRecording.value || !mediaRecorder.value) return
  if (Date.now() - recordingStartAt < 500) {
    setTimeout(() => mediaRecorder.value && mediaRecorder.value.state !== 'inactive' && mediaRecorder.value.stop(), 600)
  } else {
    mediaRecorder.value.stop()
  }
}

const transcribeRecording = async () => {
  if (audioChunks.value.length === 0) {
    isRecording.value = false
    return
  }
  isRecording.value = false
  isTranscribing.value = true
  const mimeType = audioChunks.value[0]?.type || 'audio/webm'
  const ext = mimeType.includes('mp4') ? 'm4a' : (mimeType.includes('ogg') ? 'ogg' : 'webm')
  const blob = new Blob(audioChunks.value, { type: mimeType })
  const formData = new FormData()
  formData.append('audio', blob, `recording.${ext}`)
  try {
    const res = await api.post('/ai-class/asr', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    if (res.ok === false && res._fallback === 'web_speech') {
      // 后端没配 OPENAI_API_KEY — 提示用户降级到浏览器原生 API(本次录音已废)
      ElMessage.warning('当前服务端未配置语音识别,请下次用 Chrome/Edge 浏览器并允许麦克风权限')
    } else if (res.code === 0 && res.text) {
      inputMessage.value = (inputMessage.value ? inputMessage.value + ' ' : '') + res.text.trim()
      ElMessage.success('识别成功')
    } else {
      ElMessage.error(res.message || '语音识别失败')
    }
  } catch (err) {
    console.error('[AI课堂] ASR error:', err)
    ElMessage.error('语音识别失败:' + (err.message || '网络错误'))
  } finally {
    isTranscribing.value = false
    audioChunks.value = []
  }
}

// 按钮可见性 + tooltip
const sttAvailable = computed(() => sttMode.value !== STT_MODES.UNAVAILABLE)
const voiceButtonTitle = computed(() => {
  if (isRecording.value) return '松开结束录音'
  if (isTranscribing.value) return '识别中…'
  if (!sttAvailable.value) return '当前浏览器不支持语音输入（请用 Chrome/Edge/Safari）'
  if (sttMode.value === STT_MODES.WEB_SPEECH) return '长按录音（浏览器内置识别，零 key）'
  return '长按录音（服务端识别）'
})

// ==================== 🔊 TTS 语音播放 ====================
const ttsEnabled = ref(true) // 用户可静音
const ttsVoice = ref('zh-CN-XiaoxiaoNeural') // 默认音色
const currentAudio = ref(null) // 当前播放的 audio 元素

const playTTS = async (text) => {
  if (!ttsEnabled.value || !text || !text.trim()) return
  // 跳过太短或纯标点的内容
  const clean = text.replace(/[\s\n\r\p{P}]/gu, '').trim()
  if (clean.length < 2) return
  // 截断到 500 字避免超时
  const safeText = text.length > 500 ? text.slice(0, 500) + '...' : text
  try {
    // 停掉上一段
    if (currentAudio.value) {
      currentAudio.value.pause()
      currentAudio.value = null
    }
    const res = await api.post('/ai-class/tts', {
      text: safeText,
      voice: ttsVoice.value,
      rate: '+0%'
    }, { responseType: 'blob' })
    const url = URL.createObjectURL(res)
    const audio = new Audio(url)
    audio.onended = () => { URL.revokeObjectURL(url); if (currentAudio.value === audio) currentAudio.value = null }
    audio.onerror = () => { URL.revokeObjectURL(url); if (currentAudio.value === audio) currentAudio.value = null }
    currentAudio.value = audio
    await audio.play().catch(() => {
      // autoplay 被浏览器拦截，用户没交互过
      console.warn('[AI课堂] autoplay 被拦截')
    })
  } catch (err) {
    console.error('[AI课堂] TTS error:', err?.response?.data || err.message)
  }
}

const stopTTS = () => {
  if (currentAudio.value) {
    currentAudio.value.pause()
    currentAudio.value = null
  }
}

const toggleTTS = () => {
  ttsEnabled.value = !ttsEnabled.value
  if (!ttsEnabled.value) stopTTS()
}

const SESSION_KEY = 'ai_classroom_session_id'

const getSessionId = () => {
  let sid = localStorage.getItem(SESSION_KEY)
  if (!sid) {
    sid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
      const r = Math.random() * 16 | 0
      return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16)
    })
    localStorage.setItem(SESSION_KEY, sid)
  }
  return sid
}

const sessionId = ref(getSessionId())

const scrollToBottom = () => {
  nextTick(() => {
    if (chatContainer.value) {
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight
    }
  })
}

// 加载历史对话
const loadConversations = async () => {
  if (conversationLoaded.value) return
  try {
    const res = await api.get(`/ai-class/conversations?session_id=${sessionId.value}`)
    if (res.code === 0 && Array.isArray(res.data)) {
      messages.value = res.data.map(m => ({
        role: 'user',
        content: m.query,
        time: m.created_at
      }))
      // 追加 AI 回复
      res.data.forEach(m => {
        messages.value.push({
          role: 'assistant',
          content: m.response,
          time: m.created_at
        })
      })
      scrollToBottom()
    }
  } catch (e) {
    console.error('[AI课堂] 加载对话历史失败:', e)
  } finally {
    conversationLoaded.value = true
  }
}

const sendMessage = async () => {
  const msg = inputMessage.value.trim()
  if (!msg || sending.value) return

  // user message
  messages.value.push({ role: 'user', content: msg })
  inputMessage.value = ''
  scrollToBottom()

  sending.value = true
  awaitingReply.value = true
  try {
    const res = await api.post('/ai-class/chat', { message: msg, session_id: sessionId.value })
    if (res.code === 0) {
      awaitingReply.value = false
      const reply = res.data?.reply || ''
      // Push empty message first for typewriter effect
      messages.value.push({ role: 'assistant', content: '' })
      const msgIndex = messages.value.length - 1
      streamingIndex.value = msgIndex
      // Typewriter: append ~1-2 chars every ~30ms
      let charIndex = 0
      const typeInterval = setInterval(() => {
        if (charIndex < reply.length) {
          const chunkSize = Math.min(2, reply.length - charIndex)
          messages.value[msgIndex].content += reply.slice(charIndex, charIndex + chunkSize)
          charIndex += chunkSize
          scrollToBottom()
        } else {
          clearInterval(typeInterval)
          streamingIndex.value = null
          sending.value = false
          // ✨ AI 回复完成 → 自动语音播报（ttsEnabled + 内容非空）
          if (reply && reply.trim()) playTTS(reply)
        }
      }, 30)
      return
    } else {
      ElMessage.error(res.message || 'AI 响应失败')
      messages.value.push({ role: 'assistant', content: '抱歉，发生了错误。' })
    }
  } catch (e) {
    ElMessage.error(e.message || $t('aiClassroom.sendFailed'))
    messages.value.push({ role: 'assistant', content: '抱歉，发生了错误。' })
  } finally {
    awaitingReply.value = false
    if (streamingIndex.value === null) {
      sending.value = false
    }
    scrollToBottom()
  }
}

const clearMessages = () => {
  messages.value = []
  conversationLoaded.value = false
  // generate new session
  const newSid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
    const r = Math.random() * 16 | 0
    return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16)
  })
  sessionId.value = newSid
  localStorage.setItem(SESSION_KEY, newSid)
  ElMessage.success($t('aiClassroom.chatCleared'))
}

// ─── Tab2: 知识库管理 ─────────────────────────────────────────────────────────
const knowledgeList = ref([])
const knowledgeLoading = ref(false)
const knowledgeTotal = ref(0)
const knowledgeQuery = ref({ search: '', doc_type: '', page: 1, pageSize: 10 })
const knowledgeDialogVisible = ref(false)
const knowledgeForm = ref({ id: null, title: '', content: '', doc_type: 'general', is_public: false })
const knowledgeFormRef = ref(null)
const knowledgeSaving = ref(false)

const docTypeOptions = computed(() => [
  { value: 'general', label: t('aiClassroom.general') },
  { value: 'product', label: t('aiClassroom.product') },
  { value: 'manual', label: t('aiClassroom.manual') },
])

const knowledgeRules = {
  title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
  content: [{ required: true, message: '请输入内容', trigger: 'blur' }],
}

const loadKnowledge = async () => {
  knowledgeLoading.value = true
  try {
    const params = {
      search: knowledgeQuery.value.search,
      doc_type: knowledgeQuery.value.doc_type,
      page: knowledgeQuery.value.page,
      pageSize: knowledgeQuery.value.pageSize,
    }
    const res = await api.get('/ai-class/knowledge', { params })
    if (res.code === 0) {
      knowledgeList.value = res.data.list || []
      knowledgeTotal.value = res.data.total || 0
    }
  } catch (e) {
    ElMessage.error($t('aiClassroom.loadKnowledgeFailed'))
  } finally {
    knowledgeLoading.value = false
  }
}

const onKnowledgeSearch = () => {
  knowledgeQuery.value.page = 1
  loadKnowledge()
}

const openKnowledgeAdd = () => {
  knowledgeForm.value = { id: null, title: '', content: '', doc_type: 'general', is_public: false }
  knowledgeDialogVisible.value = true
}

const openKnowledgeEdit = (row) => {
  knowledgeForm.value = { id: row.id, title: row.title, content: row.content, doc_type: row.doc_type, is_public: !!row.is_public }
  knowledgeDialogVisible.value = true
}

const saveKnowledge = async () => {
  if (!knowledgeFormRef.value) return
  await knowledgeFormRef.value.validate(async (valid) => {
    if (!valid) return
    knowledgeSaving.value = true
    try {
      const payload = {
        title: knowledgeForm.value.title,
        content: knowledgeForm.value.content,
        doc_type: knowledgeForm.value.doc_type,
        is_public: knowledgeForm.value.is_public,
      }
      let res
      if (knowledgeForm.value.id) {
        res = await api.put(`/ai-class/knowledge/${knowledgeForm.value.id}`, payload)
      } else {
        res = await api.post('/ai-class/knowledge', payload)
      }
      if (res.code === 0) {
        ElMessage.success($t('aiClassroom.saveSuccess'))
        knowledgeDialogVisible.value = false
        loadKnowledge()
      } else {
        ElMessage.error(res.message || $t('aiClassroom.saveFailed'))
      }
    } catch (e) {
      ElMessage.error(e.message || '保存失败')
    } finally {
      knowledgeSaving.value = false
    }
  })
}

const deleteKnowledge = async (row) => {
  try {
    await ElMessageBox.confirm($t('aiClassroom.confirmDeleteKnowledge'), $t('aiClassroom.confirm'), { type: 'warning' })
    const res = await api.delete(`/ai-class/knowledge/${row.id}`)
    if (res.code === 0) {
      ElMessage.success($t('aiClassroom.deleteSuccess'))
      loadKnowledge()
    } else {
      ElMessage.error(res.message || '删除失败')
    }
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '删除失败')
  }
}

// ─── Tab3: 记忆管理 ────────────────────────────────────────────────────────────
const publicMemories = ref([])
const privateMemories = ref([])
const memoryLoading = ref(false)
const memoryDialogVisible = ref(false)
const memoryForm = ref({ content: '', memory_type: 'fact' })
const memoryFormRef = ref(null)
const memorySaving = ref(false)

const memoryTypeOptions = computed(() => [
  { value: 'fact', label: t('aiClassroom.fact') },
  { value: 'preference', label: t('aiClassroom.preference') },
  { value: 'context', label: t('aiClassroom.context') },
  { value: 'summary', label: t('aiClassroom.summary') },
])

const memoryRules = {
  content: [{ required: true, message: '请输入记忆内容', trigger: 'blur' }],
}

const loadMemories = async () => {
  memoryLoading.value = true
  try {
    const [publicRes, privateRes] = await Promise.all([
      api.get('/ai-class/memory', { params: { type: 'public' } }),
      api.get('/ai-class/memory', { params: { type: 'private' } }),
    ])
    if (publicRes.code === 0) publicMemories.value = publicRes.data || []
    if (privateRes.code === 0) privateMemories.value = privateRes.data || []
  } catch (e) {
    ElMessage.error($t('aiClassroom.loadMemoryFailed'))
  } finally {
    memoryLoading.value = false
  }
}

const openMemoryAdd = () => {
  memoryForm.value = { content: '', memory_type: 'fact' }
  memoryDialogVisible.value = true
}

const saveMemory = async () => {
  if (!memoryFormRef.value) return
  await memoryFormRef.value.validate(async (valid) => {
    if (!valid) return
    memorySaving.value = true
    try {
      const res = await api.post('/ai-class/memory', {
        memory_type: memoryForm.value.memory_type,
        content: memoryForm.value.content,
      })
      if (res.code === 0) {
        ElMessage.success($t('aiClassroom.addSuccess'))
        memoryDialogVisible.value = false
        loadMemories()
      } else {
        ElMessage.error(res.message || '添加失败')
      }
    } catch (e) {
      ElMessage.error(e.message || '添加失败')
    } finally {
      memorySaving.value = false
    }
  })
}

const deleteMemory = async (row) => {
  try {
    await ElMessageBox.confirm($t('aiClassroom.confirmDeleteMemory'), $t('aiClassroom.confirm'), { type: 'warning' })
    const res = await api.delete(`/ai-class/memory/${row.id}`)
    if (res.code === 0) {
      ElMessage.success($t('aiClassroom.deleteSuccess'))
      loadMemories()
    } else {
      ElMessage.error(res.message || '删除失败')
    }
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '删除失败')
  }
}

const getMemoryTypeLabel = (type) => {
  const map = { fact: t('aiClassroom.fact'), preference: t('aiClassroom.preference'), context: t('aiClassroom.context'), summary: t('aiClassroom.summary') }
  return map[type] || type
}

// ─── Mounted ───────────────────────────────────────────────────────────────────
onMounted(() => {
  detectSTTMode()
  loadKnowledge()
  loadMemories()
  loadConversations()
})
</script>

<template>
  <div class="ai-classroom">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">{{ $t('nav.aiClassroom') }}</h2>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tab-container">
      <el-tabs v-model="activeTab" class="ai-tabs">
        <el-tab-pane :label="$t('aiClassroom.chatTab')" name="chat">
          <div class="chat-panel">
            <!-- Chat Header -->
            <div class="chat-header">
              <div class="user-info">
                <span class="material-symbols-outlined">person</span>
                <span>{{ currentUser?.name || $t('aiClassroom.notLoggedIn') }}</span>
              </div>
              <button class="btn-clear" @click="clearMessages">
                <span class="material-symbols-outlined">delete_sweep</span>
                {{ $t('aiClassroom.clearChat') }}
              </button>
            </div>

            <!-- Messages -->
            <div class="chat-messages" ref="chatContainer">
              <div v-if="messages.length === 0" class="chat-empty">
                <span class="material-symbols-outlined">psychology</span>
                <p>{{ $t('aiClassroom.chatPlaceholder') }}</p>
              </div>
              <div
                v-for="(msg, i) in messages"
                :key="i"
                :class="['message-row', msg.role, { streaming: streamingIndex === i }]"
              >
                <div class="message-bubble">
                  <span class="message-avatar">
                    <span class="material-symbols-outlined">{{ msg.role === 'user' ? 'person' : 'smart_toy' }}</span>
                  </span>
                  <div class="message-content" v-html="marked.parse(msg.content)"></div>
                </div>
              </div>
              <div v-if="streamingIndex !== null" class="message-row assistant">
                <div class="message-bubble">
                  <span class="message-avatar"><span class="material-symbols-outlined">smart_toy</span></span>
                  <div class="message-content"><span class="typing-indicator"><span></span><span></span><span></span></span></div>
                </div>
              </div>
              <!-- 等待 AI 回复 -->
              <div v-if="awaitingReply && streamingIndex === null" class="message-row assistant">
                <div class="message-bubble awaiting-reply">
                  <span class="message-avatar"><span class="material-symbols-outlined">smart_toy</span></span>
                  <div class="message-content">
                    <span>{{ $t('aiClassroom.awaitingReply') }}</span>
                    <span class="bounce-dots">
                      <span>.</span><span>.</span><span>.</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Input -->
            <div class="chat-input-bar">
              <input
                v-model="inputMessage"
                class="chat-input"
                :placeholder="$t('aiClassroom.inputPlaceholder')"
                :disabled="sending"
                @keyup.enter="sendMessage"
              />
              <!-- 🎤 语音输入按钮（长按录音；三模态自适应：webkitSpeech / MediaRecorder / disabled）-->
              <button
                class="btn-voice"
                :class="{ recording: isRecording, transcribing: isTranscribing, unavailable: !sttAvailable }"
                :disabled="sending || isTranscribing || !sttAvailable"
                :title="voiceButtonTitle"
                @mousedown.prevent="sttAvailable && startRecording"
                @mouseup.prevent="sttAvailable && stopRecording"
                @mouseleave.prevent="sttAvailable && stopRecording"
                @touchstart.prevent="sttAvailable && startRecording"
                @touchend.prevent="sttAvailable && stopRecording"
              >
                <span class="material-symbols-outlined">{{ isTranscribing ? 'hourglass_empty' : (isRecording ? 'mic' : 'mic_none') }}</span>
                <span v-if="isRecording" class="rec-dot"></span>
              </button>
              <!-- 🔊 TTS 开关 -->
              <button
                class="btn-tts"
                :class="{ active: ttsEnabled }"
                :title="ttsEnabled ? '关闭语音播报' : '开启语音播报'"
                @click="toggleTTS"
              >
                <span class="material-symbols-outlined">{{ ttsEnabled ? 'volume_up' : 'volume_off' }}</span>
              </button>
              <button class="btn-send" @click="sendMessage" :disabled="sending || !inputMessage.trim()">
                <span class="material-symbols-outlined">send</span>
              </button>
            </div>
          </div>
        </el-tab-pane>

        <!-- Tab2: 知识库管理 -->
        <el-tab-pane v-if="showKnowledgeTab" :label="$t('aiClassroom.knowledgeTab')" name="knowledge">
          <div class="knowledge-panel">
            <!-- Toolbar -->
            <div class="panel-toolbar">
              <div class="search-row">
                <el-input
                  v-model="knowledgeQuery.search"
                  :placeholder="$t('aiClassroom.searchKnowledge')"
                  class="search-input"
                  clearable
                  @keyup.enter="onKnowledgeSearch"
                />
                <el-select v-model="knowledgeQuery.doc_type" :placeholder="$t('aiClassroom.type')" class="type-select" clearable>
                  <el-option v-for="opt in docTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
                <el-button type="primary" @click="onKnowledgeSearch">{{ $t('aiClassroom.search') }}</el-button>
              </div>
              <el-button type="primary" @click="openKnowledgeAdd">
                <span class="material-symbols-outlined">add</span> {{ $t('aiClassroom.add') }}
              </el-button>
            </div>

            <!-- Table -->
            <el-table :data="knowledgeList" v-loading="knowledgeLoading" stripe class="ai-table">
              <el-table-column prop="title" :label="$t('aiClassroom.title_field')" min-width="150" show-overflow-tooltip />
              <el-table-column prop="doc_type" :label="$t('aiClassroom.type')" width="100">
                <template #default="{ row }">
                  <span class="type-tag">{{ docTypeOptions.find(o => o.value === row.doc_type)?.label || row.doc_type }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="is_public" :label="$t('aiClassroom.isPublic')" width="90">
                <template #default="{ row }">
                  <span :class="row.is_public ? 'text-success' : 'text-muted'">{{ row.is_public ? $t('aiClassroom.yes') : $t('aiClassroom.no') }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="created_at" :label="$t('aiClassroom.createdAt')" width="160" />
              <el-table-column :label="$t('aiClassroom.action')" width="120" fixed="right">
                <template #default="{ row }">
                  <div class="action-btns">
                    <button class="btn-text" @click="openKnowledgeEdit(row)">{{ $t('aiClassroom.edit') }}</button>
                    <button class="btn-text text-danger" @click="deleteKnowledge(row)">{{ $t('aiClassroom.delete') }}</button>
                  </div>
                </template>
              </el-table-column>
            </el-table>

            <!-- Pagination -->
            <div class="pagination-wrap">
              <el-pagination
                v-model:current-page="knowledgeQuery.page"
                v-model:page-size="knowledgeQuery.pageSize"
                :total="knowledgeTotal"
                layout="prev, pager, next"
                @current-change="loadKnowledge"
              />
            </div>
          </div>
        </el-tab-pane>

        <!-- Tab3: 记忆管理 -->
        <el-tab-pane v-if="showMemoryTab" :label="$t('aiClassroom.memoryTab')" name="memory">
          <div class="memory-panel">
            <!-- Toolbar -->
            <div class="panel-toolbar">
              <h4 class="section-title">{{ $t('aiClassroom.memoryManagement') }}</h4>
              <el-button type="primary" @click="openMemoryAdd">
                <span class="material-symbols-outlined">add</span> {{ $t('aiClassroom.addMemory') }}
              </el-button>
            </div>

            <!-- Two columns -->
            <div class="memory-columns">
              <!-- Public -->
              <div class="memory-col">
                <div class="col-header">
                  <span class="material-symbols-outlined">public</span>
                  {{ $t('aiClassroom.publicMemory') }}
                </div>
                <div v-loading="memoryLoading" class="memory-list">
                  <div v-if="publicMemories.length === 0" class="memory-empty">{{ $t('aiClassroom.noPublicMemory') }}</div>
                  <div v-for="item in publicMemories" :key="item.id" class="memory-card">
                    <div class="memory-card-header">
                      <span class="memory-type-tag">{{ getMemoryTypeLabel(item.memory_type) }}</span>
                      <button class="btn-icon text-danger" @click="deleteMemory(item)">
                        <span class="material-symbols-outlined">delete</span>
                      </button>
                    </div>
                    <p class="memory-content">{{ item.content }}</p>
                  </div>
                </div>
              </div>

              <!-- Private -->
              <div class="memory-col">
                <div class="col-header">
                  <span class="material-symbols-outlined">person</span>
                  {{ $t('aiClassroom.privateMemory') }}
                </div>
                <div v-loading="memoryLoading" class="memory-list">
                  <div v-if="privateMemories.length === 0" class="memory-empty">{{ $t('aiClassroom.noPrivateMemory') }}</div>
                  <div v-for="item in privateMemories" :key="item.id" class="memory-card">
                    <div class="memory-card-header">
                      <span class="memory-type-tag">{{ getMemoryTypeLabel(item.memory_type) }}</span>
                      <button class="btn-icon text-danger" @click="deleteMemory(item)">
                        <span class="material-symbols-outlined">delete</span>
                      </button>
                    </div>
                    <p class="memory-content">{{ item.content }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </el-tab-pane>
      </el-tabs>
    </div>

    <!-- 知识库弹窗 -->
    <el-dialog v-model="knowledgeDialogVisible" :title="knowledgeForm.id ? $t('aiClassroom.editKnowledge') : $t('aiClassroom.addKnowledge')" width="560px" destroy-on-close>
      <el-form ref="knowledgeFormRef" :model="knowledgeForm" :rules="knowledgeRules" label-width="80px" class="ai-form">
        <el-form-item :label="$t('aiClassroom.title_field')" prop="title">
          <el-input v-model="knowledgeForm.title" :placeholder="$t('aiClassroom.enterTitle')" />
        </el-form-item>
        <el-form-item :label="$t('aiClassroom.content')" prop="content">
          <el-input v-model="knowledgeForm.content" type="textarea" :rows="4" :placeholder="$t('aiClassroom.enterContent')" />
        </el-form-item>
        <el-form-item :label="$t('aiClassroom.type')" prop="doc_type">
          <el-select v-model="knowledgeForm.doc_type" class="full-width">
            <el-option v-for="opt in docTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('aiClassroom.isPublic')" prop="is_public">
          <el-switch v-model="knowledgeForm.is_public" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="knowledgeDialogVisible = false">{{ $t('aiClassroom.cancel') }}</el-button>
        <el-button type="primary" :loading="knowledgeSaving" @click="saveKnowledge">{{ $t('aiClassroom.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 记忆弹窗 -->
    <el-dialog v-model="memoryDialogVisible" :title="$t('aiClassroom.addMemory')" width="500px" destroy-on-close>
      <el-form ref="memoryFormRef" :model="memoryForm" :rules="memoryRules" label-width="80px" class="ai-form">
        <el-form-item :label="$t('aiClassroom.memoryType')" prop="memory_type">
          <el-select v-model="memoryForm.memory_type" class="full-width">
            <el-option v-for="opt in memoryTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('aiClassroom.content')" prop="content">
          <el-input v-model="memoryForm.content" type="textarea" :rows="4" :placeholder="$t('aiClassroom.enterMemoryContent')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="memoryDialogVisible = false">{{ $t('aiClassroom.cancel') }}</el-button>
        <el-button type="primary" :loading="memorySaving" @click="saveMemory">{{ $t('aiClassroom.save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.ai-classroom {
  padding: 24px;
}

@media (max-width: 768px) {
  .ai-classroom {
    padding: 8px;  /* 手机端减小 padding */
  }
  .page-title {
    font-size: 18px;  /* 手机端标题缩小 */
  }
  .page-header {
    margin-bottom: 12px;
  }
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0;
}

.tab-container {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

/* Tabs */
.ai-tabs :deep(.el-tabs__header) {
  margin: 0;
  background: #fafafa;
  border-bottom: 1px solid #f0f0f0;
}

.ai-tabs :deep(.el-tabs__nav-wrap::after) {
  display: none;
}

.ai-tabs :deep(.el-tabs__item) {
  font-size: 14px;
  font-weight: 500;
  color: #666;
  height: 48px;
  line-height: 48px;
  padding: 0 24px;
}

.ai-tabs :deep(.el-tabs__item.is-active) {
  color: #3b82f6;
  font-weight: 600;
}

.ai-tabs :deep(.el-tabs__active-bar) {
  height: 3px;
  border-radius: 3px 3px 0 0;
}

/* Chat Panel */
.chat-panel {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 260px);
  min-height: 500px;
}

.chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  border-bottom: 1px solid #f0f0f0;
  background: #fafafa;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #444;
}

.user-info .material-symbols-outlined {
  font-size: 20px;
  color: #888;
}

.btn-clear {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 6px 14px;
  font-size: 13px;
  color: #888;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-clear:hover {
  color: #ef4444;
  border-color: #ef4444;
  background: #fef2f2;
}

.btn-clear .material-symbols-outlined {
  font-size: 18px;
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  scroll-behavior: smooth;
}

.chat-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #aaa;
  gap: 12px;
}

.chat-empty .material-symbols-outlined {
  font-size: 48px;
}

.chat-empty p {
  font-size: 15px;
  margin: 0;
}

@keyframes messageEnter {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.message-row {
  display: flex;
  animation: messageEnter 0.2s ease-out;
}

.message-row.user {
  flex-direction: row-reverse;
  justify-content: flex-start;
}

.message-row.assistant {
  justify-content: flex-start;
}

.message-row + .message-row {
  margin-top: 4px;
}

.message-row.user + .message-row.user,
.message-row.assistant + .message-row.assistant {
  margin-top: 12px;
}

.message-bubble {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  max-width: 70%;
  min-width: 0;  /* 允许 flex item 收缩 */
}

.message-avatar {
  flex-shrink: 0;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #f0f0f0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.message-avatar .material-symbols-outlined {
  font-size: 18px;
  color: #888;
}

.message-row.user .message-avatar {
  background: #3b82f6;
}

.message-row.user .message-avatar .material-symbols-outlined {
  color: #fff;
}

.message-row.assistant .message-avatar {
  background: #f5f5f5;
}

.message-row.assistant .message-avatar .material-symbols-outlined {
  color: #3b82f6;
}

.message-content {
  background: #f3f4f6;
  border-radius: 14px;
  padding: 10px 14px;
  font-size: 15px;
  color: #333;
  line-height: 1.65;
  word-break: break-word;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.message-row.user .message-content {
  background: #3b82f6;
  color: #fff;
}

.message-content.typing {
  color: #999;
  font-style: italic;
}

/* Typing indicator dots */
.typing-indicator {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 0;
}

.typing-indicator span {
  display: block;
  width: 6px;
  height: 6px;
  background: #999;
  border-radius: 50%;
  animation: typingDot 1.4s infinite ease-in-out;
}

.typing-indicator span:nth-child(1) { animation-delay: 0s; }
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typingDot {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 0.4;
  }
  30% {
    transform: translateY(-4px);
    opacity: 1;
  }
}

/* Streaming message cursor */
.message-row.streaming .message-content::after {
  content: '|';
  display: inline-block;
  margin-left: 2px;
  animation: blink 0.8s infinite;
  color: #3b82f6;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0; }
}

.message-time {
  font-size: 11px;
  color: #bbb;
  margin-top: 4px;
  text-align: right;
  display: none;
}

@media (max-width: 768px) {
  .chat-panel {
    height: calc(100vh - 160px);
    min-height: 400px;
  }

  .chat-header {
    padding: 8px 12px;
    flex-wrap: wrap;
    gap: 8px;
  }

  .user-info {
    font-size: 12px;
  }

  .btn-clear {
    padding: 4px 8px;
    font-size: 11px;
  }

  .chat-messages {
    padding: 8px 8px;
    gap: 10px;
  }

  .message-bubble {
    max-width: 92%;
    gap: 6px;
  }

  .message-avatar {
    width: 26px;
    height: 26px;
  }

  .message-avatar .material-symbols-outlined {
    font-size: 14px;
  }

  /* 输入栏手机端适配 */
  .chat-input-bar {
    padding: 8px;
    gap: 6px;
  }
  .chat-input {
    font-size: 14px;
    padding: 8px 12px;
  }
  .btn-send {
    width: 36px;
    height: 36px;
  }

  .message-content {
    padding: 8px 12px;
    font-size: 14px;
    border-radius: 12px;
  }

  .message-row.assistant .message-avatar {
    display: flex;
  }

  .message-row.user .message-avatar {
    display: flex;
  }

  .chat-input-bar {
    padding: 10px 12px;
    gap: 8px;
  }

  .btn-clear {
    padding: 5px 10px;
    font-size: 12px;
  }

  /* 表格内容溢出处理 */
  .message-content table {
    display: block;
    overflow-x: auto;
    white-space: nowrap;
    font-size: 12px;
  }
  /* el-tabs 适配 */
  :deep(.el-tabs__header) {
    margin: 0 0 8px 0;
  }
  :deep(.el-tabs__nav-wrap.is-scrollable) {
    padding: 0;
  }
  :deep(.el-tabs__item) {
    padding: 0 12px !important;
    font-size: 14px;
  }
}

.chat-input-bar {
  display: flex;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid #f0f0f0;
  background: #fafafa;
}

.chat-input {
  flex: 1;
  height: 42px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 0 16px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}

.chat-input:focus {
  border-color: #3b82f6;
}

.btn-send {
  width: 42px;
  height: 42px;
  border-radius: 8px;
  background: #3b82f6;
  border: none;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.btn-send:hover:not(:disabled) {
  background: #2563eb;
}

.btn-send:disabled {
  background: #93c5fd;
  cursor: not-allowed;
}

/* 🎤 语音输入按钮 */
.btn-voice {
  width: 42px;
  height: 42px;
  border-radius: 8px;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  color: #6b7280;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  position: relative;
  user-select: none;
  -webkit-user-select: none;
}
.btn-voice:hover:not(:disabled) {
  background: #e5e7eb;
  color: #374151;
}
.btn-voice:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.btn-voice.recording {
  background: #ef4444;
  border-color: #dc2626;
  color: #fff;
  animation: pulse-rec 1.2s ease-in-out infinite;
}
.btn-voice.transcribing {
  background: #fbbf24;
  border-color: #f59e0b;
  color: #fff;
}
.btn-voice .rec-dot {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #fff;
  animation: pulse-dot 0.8s ease-in-out infinite;
}
@keyframes pulse-rec {
  0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5); }
  50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
}
@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(1.3); }
}

/* 🔊 TTS 开关按钮 */
.btn-tts {
  width: 42px;
  height: 42px;
  border-radius: 8px;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  color: #9ca3af;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.btn-tts:hover {
  background: #e5e7eb;
  color: #6b7280;
}
.btn-tts.active {
  background: #dbeafe;
  border-color: #93c5fd;
  color: #2563eb;
}

.btn-send .material-symbols-outlined {
  font-size: 20px;
}

/* Knowledge Panel */
.knowledge-panel {
  padding: 20px;
}

.panel-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
}

.search-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.search-input {
  width: 220px;
}

.type-select {
  width: 140px;
}

.ai-table {
  border-radius: 8px;
  overflow: hidden;
}

.ai-table :deep(.el-table__header th) {
  background: #fafafa;
  color: #555;
  font-size: 13px;
  font-weight: 600;
}

.ai-table :deep(.el-table__row:hover > td) {
  background: #f9fafb;
}

.type-tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  background: #eff6ff;
  color: #3b82f6;
}

.action-btns {
  display: flex;
  gap: 8px;
}

.btn-text {
  background: none;
  border: none;
  padding: 0;
  font-size: 13px;
  cursor: pointer;
  color: #3b82f6;
  transition: color 0.2s;
}

.btn-text:hover {
  color: #1d4ed8;
}

.btn-text.text-danger {
  color: #ef4444;
}

.btn-text.text-danger:hover {
  color: #dc2626;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

/* Memory Panel */
.memory-panel {
  padding: 20px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  margin: 0;
}

.memory-columns {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-top: 16px;
}

.memory-col {
  background: #fafafa;
  border-radius: 10px;
  border: 1px solid #f0f0f0;
  overflow: hidden;
}

.col-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #fff;
  border-bottom: 1px solid #f0f0f0;
  font-size: 14px;
  font-weight: 600;
  color: #333;
}

.col-header .material-symbols-outlined {
  font-size: 20px;
  color: #3b82f6;
}

.memory-list {
  padding: 12px;
  min-height: 200px;
  max-height: 500px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.memory-empty {
  text-align: center;
  color: #aaa;
  padding: 40px 0;
  font-size: 14px;
}

.memory-card {
  background: #fff;
  border: 1px solid #f0f0f0;
  border-radius: 8px;
  padding: 12px;
}

.memory-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.memory-type-tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  background: #f0f9ff;
  color: #0284c7;
}

.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  color: #888;
  display: flex;
  align-items: center;
  transition: all 0.2s;
}

.btn-icon:hover {
  background: #f5f5f5;
}

.btn-icon.text-danger:hover {
  background: #fef2f2;
  color: #ef4444;
}

.btn-icon .material-symbols-outlined {
  font-size: 18px;
}

.memory-content {
  font-size: 13px;
  color: #444;
  line-height: 1.6;
  margin: 0;
  word-break: break-word;
}

/* Form */
.ai-form :deep(.el-form-item__label) {
  font-weight: 500;
  color: #333;
}

.full-width {
  width: 100%;
}

/* Util */
.text-success { color: #22c55e; }
.text-danger { color: #ef4444; }
.text-muted { color: #999; }

/* Markdown rendered content */
.message-content pre {
  background: #1e1e1e;
  color: #d4d4d4;
  border-radius: 8px;
  padding: 12px 16px;
  font-size: 13px;
  font-family: 'Fira Code', 'Consolas', monospace;
  overflow-x: auto;
  margin: 8px 0;
  line-height: 1.5;
}

.message-content code {
  background: #f0f0f0;
  color: #c7254e;
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 13px;
  font-family: 'Fira Code', 'Consolas', monospace;
}

.message-row.user .message-content code {
  background: rgba(255,255,255,0.2);
  color: #fff;
}

.message-content p {
  margin: 4px 0;
}

.message-content p:first-child {
  margin-top: 0;
}

.message-content p:last-child {
  margin-bottom: 0;
}

.message-content strong {
  font-weight: 700;
  color: inherit;
}

.message-content em {
  font-style: italic;
}

.message-content ul, .message-content ol {
  margin: 6px 0;
  padding-left: 20px;
}

.message-content li {
  margin: 3px 0;
}

.message-content blockquote {
  border-left: 3px solid #3b82f6;
  margin: 8px 0;
  padding: 4px 12px;
  color: #666;
  background: rgba(59, 130, 246, 0.05);
  border-radius: 0 4px 4px 0;
}

.message-content table {
  border-collapse: collapse;
  width: 100%;
  margin: 8px 0;
  font-size: 13px;
}

.message-content table th,
.message-content table td {
  border: 1px solid #e0e0e0;
  padding: 6px 12px;
  text-align: left;
}

.message-content table th {
  background: #f5f5f5;
  font-weight: 600;
}

.message-content a {
  color: #3b82f6;
  text-decoration: none;
}

.message-content a:hover {
  text-decoration: underline;
}

.message-content h1,
.message-content h2,
.message-content h3,
.message-content h4 {
  margin: 8px 0 4px;
  font-weight: 600;
}

.message-content h1 { font-size: 18px; }
.message-content h2 { font-size: 16px; }
.message-content h3 { font-size: 15px; }
.message-content h4 { font-size: 14px; }

.message-content hr {
  border: none;
  border-top: 1px solid #e0e0e0;
  margin: 10px 0;
}

/* ── 等待 AI 回复 ── */
.message-bubble.awaiting-reply .message-content {
  display: flex;
  align-items: center;
  gap: 2px;
  font-size: 13px;
  color: #888;
}

.bounce-dots {
  display: inline-flex;
  align-items: center;
  margin-left: 1px;
}

.bounce-dots span {
  display: inline-block;
  font-size: 18px;
  font-weight: 700;
  line-height: 1;
  color: #888;
  animation: bounceDot 1.4s infinite ease-in-out;
}

.bounce-dots span:nth-child(1) { animation-delay: 0ms; }
.bounce-dots span:nth-child(2) { animation-delay: 150ms; }
.bounce-dots span:nth-child(3) { animation-delay: 300ms; }

@keyframes bounceDot {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
  30% { transform: translateY(-4px); opacity: 1; }
}
</style>