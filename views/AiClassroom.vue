<script setup>
import { ref, onMounted, nextTick, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../stores/user'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../services/api.js'

const userStore = useUserStore()
const { t } = useI18n()
const currentUser = computed(() => userStore.user)
const userRole = computed(() => userStore.userRole)
const userPermissions = computed(() => userStore.user?.permissions || null)

// admin 始终有全部权限；其他角色按 permissions 数组判断
const isAdmin = computed(() => userRole.value === 'admin')

const canAccess = (key) => {
  if (isAdmin.value) return true
  const perms = userPermissions.value
  return Array.isArray(perms) && perms.includes(key)
}

// Tab 可见性：知识库管理 / 记忆管理（admin 始终可见，member 按角色权限）
const showKnowledgeTab = computed(() => isAdmin.value || canAccess('knowledge-base'))
const showMemoryTab = computed(() => isAdmin.value || canAccess('memory-management'))

// ─── Tab ─────────────────────────────────────────────────────────────────────
const activeTab = ref('chat')

// ─── Tab1: AI对话 ──────────────────────────────────────────────────────────────
const messages = ref([])
const inputMessage = ref('')
const sending = ref(false)
const chatContainer = ref(null)
const conversationLoaded = ref(false)

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
  try {
    const res = await api.post('/ai-class/chat', { message: msg, session_id: sessionId.value })
    if (res.code === 0) {
      messages.value.push({ role: 'assistant', content: res.data?.reply || '' })
    } else {
      ElMessage.error(res.message || 'AI 响应失败')
      messages.value.push({ role: 'assistant', content: '抱歉，发生了错误。' })
    }
  } catch (e) {
    ElMessage.error(e.message || $t('aiClassroom.sendFailed'))
    messages.value.push({ role: 'assistant', content: '抱歉，发生了错误。' })
  } finally {
    sending.value = false
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
                清空对话
              </button>
            </div>

            <!-- Messages -->
            <div class="chat-messages" ref="chatContainer">
              <div v-if="messages.length === 0" class="chat-empty">
                <span class="material-symbols-outlined">psychology</span>
                <p>开始对话吧</p>
              </div>
              <div
                v-for="(msg, i) in messages"
                :key="i"
                :class="['message-row', msg.role]"
              >
                <div class="message-bubble">
                  <span class="message-avatar">
                    <span class="material-symbols-outlined">{{ msg.role === 'user' ? 'person' : 'smart_toy' }}</span>
                  </span>
                  <div class="message-content">{{ msg.content }}</div>
                </div>
              </div>
              <div v-if="sending" class="message-row assistant">
                <div class="message-bubble">
                  <span class="message-avatar"><span class="material-symbols-outlined">smart_toy</span></span>
                  <div class="message-content typing">思考中...</div>
                </div>
              </div>
            </div>

            <!-- Input -->
            <div class="chat-input-bar">
              <input
                v-model="inputMessage"
                class="chat-input"
                placeholder="输入消息..."
                :disabled="sending"
                @keyup.enter="sendMessage"
              />
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
                  placeholder="搜索标题/内容"
                  class="search-input"
                  clearable
                  @keyup.enter="onKnowledgeSearch"
                />
                <el-select v-model="knowledgeQuery.doc_type" placeholder="类型" class="type-select" clearable>
                  <el-option v-for="opt in docTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
                <el-button type="primary" @click="onKnowledgeSearch">搜索</el-button>
              </div>
              <el-button type="primary" @click="openKnowledgeAdd">
                <span class="material-symbols-outlined">add</span> 新增
              </el-button>
            </div>

            <!-- Table -->
            <el-table :data="knowledgeList" v-loading="knowledgeLoading" stripe class="ai-table">
              <el-table-column prop="title" label="标题" min-width="150" show-overflow-tooltip />
              <el-table-column prop="doc_type" label="类型" width="100">
                <template #default="{ row }">
                  <span class="type-tag">{{ docTypeOptions.find(o => o.value === row.doc_type)?.label || row.doc_type }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="is_public" label="是否公开" width="90">
                <template #default="{ row }">
                  <span :class="row.is_public ? 'text-success' : 'text-muted'">{{ row.is_public ? '是' : '否' }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="created_at" label="创建时间" width="160" />
              <el-table-column label="操作" width="120" fixed="right">
                <template #default="{ row }">
                  <div class="action-btns">
                    <button class="btn-text" @click="openKnowledgeEdit(row)">编辑</button>
                    <button class="btn-text text-danger" @click="deleteKnowledge(row)">删除</button>
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
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
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

.message-row {
  display: flex;
}

.message-row.user {
  justify-content: flex-end;
}

.message-row.assistant {
  justify-content: flex-start;
}

.message-bubble {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  max-width: 75%;
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
  border-radius: 12px;
  padding: 10px 16px;
  font-size: 14px;
  color: #333;
  line-height: 1.6;
  word-break: break-word;
}

.message-row.user .message-content {
  background: #3b82f6;
  color: #fff;
}

.message-content.typing {
  color: #999;
  font-style: italic;
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
</style>