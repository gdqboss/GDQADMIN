<!--
  机密配方 + AI 创新 (secure-knowledge MVP)
  2026-09-18 江小鱼立
  UI: 机密库列表 + 提问 AI + 调用日志
-->
<template>
  <div class="p-4">
    <PageHeader title="机密配方 AI" subtitle="机密加密存储 · 客户自配 LLM · 老板授权可见">
      <template #actions>
        <button @click="fetchAll" class="px-3 py-1.5 text-sm bg-primary text-white rounded-md">
          刷新
        </button>
      </template>
    </PageHeader>

    <!-- 安全提示 -->
    <div class="bg-rose-50 border border-rose-200 rounded-lg p-3 mb-4 text-xs text-rose-900">
      🔒 <strong>5 层防御</strong>: rbac 权限 · AES-256 加密 · prompt 隔离 · 客户自配 LLM · 老板授权。
      机密原文不进 LLM,仅文档元信息 + 公开知识提供给 AI。
    </div>

    <!-- Tab: 机密库 / AI 创新 / 调用日志 -->
    <div class="flex gap-2 mb-4 border-b border-slate-200">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
        class="px-4 py-2 text-sm border-b-2 -mb-px"
        :class="activeTab === t.key
          ? 'border-primary text-primary font-medium'
          : 'border-transparent text-text-secondary'">
        {{ t.label }}
      </button>
    </div>

    <!-- Tab 1: 机密库 -->
    <div v-if="activeTab === 'docs'">
      <div v-if="loading" class="bg-white rounded-lg p-8 text-center text-text-secondary text-sm">加载中…</div>
      <div v-else-if="!docs.length" class="bg-white rounded-lg p-8 text-center text-text-secondary text-sm">
        暂无机密文档
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div v-for="d in docs" :key="d.id" class="bg-white rounded-lg p-4">
          <div class="flex items-start gap-2">
            <div class="text-2xl">🔒</div>
            <div class="flex-1">
              <div class="font-medium">{{ d.title }}</div>
              <div class="text-xs text-text-secondary mt-1">
                {{ d.category }} · {{ (d.content_size / 1024).toFixed(1) }} KB
              </div>
            </div>
          </div>
          <div class="flex flex-wrap gap-1 mt-2">
            <span v-for="t in parseTags(d.tags)" :key="t"
                  class="text-xs px-2 py-0.5 bg-rose-50 text-rose-700 rounded">{{ t }}</span>
          </div>
          <div class="text-xs text-text-secondary mt-2 pt-2 border-t border-slate-100">
            授权 {{ parseTags(d.authorized_user_ids).length || 0 }} 人 ·
            被 AI 调用 {{ d.access_count }} 次
          </div>
          <div class="mt-2 text-xs font-mono text-slate-400 truncate">
            SHA-256: {{ d.content_hash.substring(0, 32) }}…
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 2: AI 创新 -->
    <div v-if="activeTab === 'innovate'" class="space-y-4">
      <div class="bg-white rounded-lg p-4">
        <div class="text-sm font-medium mb-3">问 AI (基于机密 + 公开知识)</div>
        <div class="space-y-3">
          <div>
            <label class="block text-xs text-text-secondary mb-1">选择机密文档 (可选)</label>
            <select v-model="innovateForm.document_id"
              class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
              <option :value="null">不选文档 (仅公开知识)</option>
              <option v-for="d in docs" :key="d.id" :value="d.id">{{ d.title }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-text-secondary mb-1">问题</label>
            <textarea v-model="innovateForm.question" rows="3"
              class="w-full border border-slate-200 rounded px-3 py-2 text-sm"
              placeholder="例: 如何增加板材的硬度?"></textarea>
          </div>
          <div>
            <label class="block text-xs text-text-secondary mb-1">约束规则 (老板设的)</label>
            <textarea v-model="innovateForm.constraint_rules" rows="2"
              class="w-full border border-slate-200 rounded px-3 py-2 text-sm"
              placeholder="例: 不准透露配方成分 / 仅基于公开知识"></textarea>
          </div>
          <button @click="submitInnovate" :disabled="innovateLoading"
            class="px-4 py-2 bg-primary text-white rounded text-sm disabled:opacity-50">
            {{ innovateLoading ? 'AI 思考中…' : '问 AI' }}
          </button>
        </div>
      </div>

      <!-- AI 回复 -->
      <div v-if="innovateResult" class="bg-white rounded-lg p-4">
        <div class="text-sm font-medium mb-2">AI 回复</div>
        <div class="text-sm whitespace-pre-wrap bg-slate-50 rounded p-3">{{ innovateResult.answer || '(空)' }}</div>
        <div class="text-xs text-text-secondary mt-2">
          {{ innovateResult.provider }} / {{ innovateResult.model }} ·
          {{ innovateResult.latency_ms }}ms
        </div>
        <div v-if="innovateResult.note" class="text-xs text-amber-700 mt-2">
          ⚠ {{ innovateResult.note }}
        </div>
      </div>
      <div v-if="innovateError" class="bg-rose-50 border border-rose-200 rounded-lg p-3 text-sm text-rose-800">
        {{ innovateError }}
      </div>
    </div>

    <!-- Tab 3: 调用日志 -->
    <div v-if="activeTab === 'logs'">
      <div v-if="!logs.length" class="bg-white rounded-lg p-8 text-center text-text-secondary text-sm">
        暂无调用日志
      </div>
      <div v-else class="bg-white rounded-lg overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-xs text-text-secondary">
            <tr>
              <th class="px-3 py-2 text-left font-medium">时间</th>
              <th class="px-3 py-2 text-left font-medium">文档</th>
              <th class="px-3 py-2 text-left font-medium">LLM</th>
              <th class="px-3 py-2 text-center font-medium">Token</th>
              <th class="px-3 py-2 text-center font-medium">耗时</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in logs" :key="l.id" class="border-t border-slate-100">
              <td class="px-3 py-2 text-xs">{{ fmtTime(l.called_at) }}</td>
              <td class="px-3 py-2 text-xs">{{ l.document_id || '-' }}</td>
              <td class="px-3 py-2 text-xs">{{ l.llm_provider }} / {{ l.llm_model }}</td>
              <td class="px-3 py-2 text-center text-xs">{{ l.response_tokens || '-' }}</td>
              <td class="px-3 py-2 text-center text-xs">{{ l.response_latency_ms || '-' }}ms</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import PageHeader from '../../components/PageHeader.vue'

const tabs = [
  { key: 'docs', label: '机密库' },
  { key: 'innovate', label: 'AI 创新' },
  { key: 'logs', label: '调用日志' },
]
const activeTab = ref('docs')

const loading = ref(true)
const docs = ref([])
const logs = ref([])

const innovateForm = ref({ document_id: null, question: '', constraint_rules: '' })
const innovateLoading = ref(false)
const innovateResult = ref(null)
const innovateError = ref('')

const parseTags = (raw) => {
  if (Array.isArray(raw)) return raw
  if (typeof raw === 'string') {
    try { const v = JSON.parse(raw); return Array.isArray(v) ? v : [] } catch { return [] }
  }
  return []
}

const fmtTime = (t) => t ? new Date(t).toLocaleString('zh-CN') : '-'

const fetchAll = async () => {
  loading.value = true
  try {
    const [d, l] = await Promise.all([
      api.get('/api/secure-knowledge/documents'),
      api.get('/api/secure-knowledge/logs'),
    ])
    if (d.code === 0) docs.value = d.data
    if (l.code === 0) logs.value = l.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const submitInnovate = async () => {
  if (!innovateForm.value.question.trim()) {
    innovateError.value = '请输入问题'
    return
  }
  innovateLoading.value = true
  innovateResult.value = null
  innovateError.value = ''
  try {
    const r = await api.post('/api/secure-knowledge/innovate', {
      document_id: innovateForm.value.document_id,
      question: innovateForm.value.question,
      constraint_rules: innovateForm.value.constraint_rules || undefined,
    })
    if (r.code === 0) {
      innovateResult.value = r.data
      await fetchAll()
    } else {
      innovateError.value = r.message || 'AI 调用失败'
    }
  } catch (e) {
    innovateError.value = 'AI 调用异常: ' + (e.message || e)
  } finally {
    innovateLoading.value = false
  }
}

onMounted(fetchAll)
</script>