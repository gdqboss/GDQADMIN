<!--
  AI 招聘对话页（应聘者视角）
  - 进入页面: 选岗位 → 创建 session → 进入对话
  - 对话: 多轮聊天 + 底部输入框
  - 结束: 显示评价报告
-->
<template>
  <div class="min-h-screen bg-slate-50 flex flex-col" style="height:100vh">
    <!-- 顶部栏 -->
    <div class="sticky top-0 bg-white border-b border-slate-100 px-4 py-3 z-20 flex items-center gap-3">
      <button @click="goBack" class="flex-shrink-0">
        <span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span>
      </button>
      <div class="flex-1">
        <div class="text-base font-medium text-slate-800">{{ currentJob?.title || 'AI 招聘' }}</div>
        <div v-if="currentJob" class="text-[11px] text-slate-400 mt-0.5">
          {{ currentJob.salary_range || '面议' }} · {{ currentJob.location || '' }}
          <span v-if="sessionId" class="ml-2">· 进度 {{ messageCount }}/{{ maxQuestions }}</span>
        </div>
      </div>
    </div>

    <!-- 阶段 1: 选岗位 -->
    <div v-if="!sessionId" class="flex-1 overflow-y-auto p-4">
      <h2 class="text-lg font-medium text-slate-700 mb-3">请选择你要应聘的岗位</h2>
      <div v-if="loadingJobs" class="text-center py-8 text-slate-400">加载中…</div>
      <div v-else-if="!jobs.length" class="text-center py-8 text-slate-400">暂无开放岗位</div>
      <div v-else class="space-y-3">
        <div v-for="job in jobs" :key="job.id"
          @click="selectJob(job)"
          class="bg-white rounded-xl p-4 border border-slate-100 hover:border-primary/40 hover:shadow-sm transition cursor-pointer">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="font-medium text-slate-800">{{ job.title }}</div>
              <div class="text-xs text-slate-400 mt-1">
                <span>{{ job.department || '—' }}</span>
                <span class="mx-1.5">·</span>
                <span>{{ job.location || '—' }}</span>
              </div>
              <div v-if="job.description" class="text-xs text-slate-500 mt-2 line-clamp-2">
                {{ job.description }}
              </div>
            </div>
            <div class="ml-3 text-right flex-shrink-0">
              <div class="text-sm text-primary font-medium">{{ job.salary_range || '面议' }}</div>
              <div v-if="job.headcount" class="text-[10px] text-slate-400 mt-1">{{ job.headcount }} 人</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 阶段 2: 对话 -->
    <div v-else-if="!finished" class="flex-1 flex flex-col overflow-hidden">
      <!-- 消息列表 -->
      <div ref="msgListRef" class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
        <div v-for="(m, i) in messages" :key="i"
          :class="['flex', m.role === 'user' ? 'justify-end' : 'justify-start']">
          <div :class="['max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed',
                       m.role === 'user'
                         ? 'bg-primary text-white rounded-tr-sm'
                         : 'bg-white text-slate-700 rounded-tl-sm border border-slate-100']">
            <div v-if="m.role === 'assistant'" class="flex items-center gap-1.5 mb-1.5">
              <span class="material-symbols-outlined text-primary text-base">smart_toy</span>
              <span class="text-[11px] text-slate-400">AI 招聘助手</span>
            </div>
            <div class="whitespace-pre-wrap">{{ m.content }}</div>
          </div>
        </div>

        <div v-if="loading" class="flex justify-start">
          <div class="bg-white border border-slate-100 rounded-2xl rounded-tl-sm px-4 py-3 flex items-center gap-2">
            <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-pulse"></span>
            <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-pulse" style="animation-delay:0.2s"></span>
            <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-pulse" style="animation-delay:0.4s"></span>
          </div>
        </div>
      </div>

      <!-- 输入框 -->
      <div class="border-t border-slate-100 bg-white px-3 py-2.5 flex items-end gap-2">
        <textarea
          v-model="inputText"
          @keydown.enter.exact.prevent="sendMessage"
          :disabled="loading"
          rows="1"
          placeholder="输入回复…"
          class="flex-1 resize-none bg-slate-100 rounded-2xl px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 max-h-24"
        ></textarea>
        <button @click="sendMessage" :disabled="!inputText.trim() || loading"
          class="bg-primary text-white rounded-full w-10 h-10 flex items-center justify-center disabled:opacity-40 flex-shrink-0">
          <span class="material-symbols-outlined text-xl">send</span>
        </button>
      </div>

      <!-- 底部动作栏 -->
      <div class="border-t border-slate-100 bg-slate-50 px-4 py-2 flex items-center justify-between">
        <span class="text-[11px] text-slate-400">输入"结束"或点击下方按钮结束对话</span>
        <button @click="finishDialog" :disabled="loading || messageCount < 2"
          class="text-xs text-slate-500 hover:text-primary disabled:opacity-40">
          结束对话
        </button>
      </div>
    </div>

    <!-- 阶段 3: 报告 -->
    <div v-else class="flex-1 overflow-y-auto p-4 space-y-4">
      <div class="bg-gradient-to-br from-primary to-primary/70 text-white rounded-2xl p-5 shadow-md">
        <div class="text-xs opacity-80">AI 评价已完成</div>
        <div class="flex items-end justify-between mt-2">
          <div>
            <div class="text-3xl font-bold">{{ evaluation?.overall_score ?? '—' }}</div>
            <div class="text-xs opacity-80">综合评分 / 100</div>
          </div>
          <div :class="['text-sm px-3 py-1 rounded-full',
                       recClass(evaluation?.recommendation)]">
            {{ recLabel(evaluation?.recommendation) }}
          </div>
        </div>
        <div v-if="evaluation?.summary" class="text-sm mt-3 opacity-95">
          {{ evaluation.summary }}
        </div>
      </div>

      <!-- 预设条件 -->
      <div v-if="evaluation?.preset_results?.length" class="bg-white rounded-xl p-4 border border-slate-100">
        <div class="text-sm font-medium text-slate-700 mb-3">硬性条件</div>
        <div class="space-y-2">
          <div v-for="(p, i) in evaluation.preset_results" :key="i"
            class="flex items-start gap-2 text-xs">
            <span :class="['material-symbols-outlined text-base mt-0.5',
                          p.passed ? 'text-green-500' : 'text-red-500']">
              {{ p.passed ? 'check_circle' : 'cancel' }}
            </span>
            <div class="flex-1">
              <div class="text-slate-700">{{ p.question }}</div>
              <div class="text-slate-400 mt-0.5">回答: {{ p.answer || '未回答' }} · 要求: {{ p.requirement }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 软素质 -->
      <div v-if="evaluation?.soft_scores?.length" class="bg-white rounded-xl p-4 border border-slate-100">
        <div class="text-sm font-medium text-slate-700 mb-3">软素质评价</div>
        <div class="space-y-3">
          <div v-for="(s, i) in evaluation.soft_scores" :key="i">
            <div class="flex items-center justify-between text-xs mb-1">
              <span class="text-slate-600">{{ s.label }}</span>
              <span class="text-slate-800 font-medium">{{ s.score }}/10</span>
            </div>
            <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-primary transition-all" :style="{ width: (s.score * 10) + '%' }"></div>
            </div>
            <div v-if="s.evidence" class="text-[11px] text-slate-400 mt-1.5 italic">"{{ s.evidence }}"</div>
          </div>
        </div>
      </div>

      <!-- 红黄牌 + 亮点 -->
      <div class="grid grid-cols-2 gap-3">
        <div v-if="evaluation?.red_flags?.length" class="bg-red-50 rounded-xl p-3 border border-red-100">
          <div class="text-xs font-medium text-red-700 mb-2 flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">flag</span>
            红黄牌 ({{ evaluation.red_flags.length }})
          </div>
          <div class="space-y-1">
            <div v-for="(r, i) in evaluation.red_flags" :key="i" class="text-[11px] text-red-600">· {{ r }}</div>
          </div>
        </div>
        <div v-if="evaluation?.highlights?.length" class="bg-green-50 rounded-xl p-3 border border-green-100">
          <div class="text-xs font-medium text-green-700 mb-2 flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">star</span>
            亮点 ({{ evaluation.highlights.length }})
          </div>
          <div class="space-y-1">
            <div v-for="(h, i) in evaluation.highlights" :key="i" class="text-[11px] text-green-600">· {{ h }}</div>
          </div>
        </div>
      </div>

      <div class="text-center text-[11px] text-slate-400 pt-2">
        我们会在 3 个工作日内联系你
      </div>

      <button @click="goBack" class="w-full bg-primary text-white rounded-full py-3 text-sm font-medium">
        完成
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import axios from 'axios'

const jobs = ref([])
const loadingJobs = ref(false)
const sessionId = ref('')
const currentJob = ref(null)
const messages = ref([])
const inputText = ref('')
const loading = ref(false)
const finished = ref(false)
const evaluation = ref(null)
const maxQuestions = ref(12)
const msgListRef = ref(null)

const messageCount = computed(() => messages.value.length)

const API_BASE = '/api/ai-hr'

function goBack() {
  if (window.history.length > 1) window.history.back()
  else window.location.href = '/'
}

function recLabel(r) {
  return { strong: '强烈推荐', consider: '可考虑', reject: '不推荐' }[r] || '—'
}

function recClass(r) {
  return {
    strong: 'bg-white text-green-700',
    consider: 'bg-white text-amber-700',
    reject: 'bg-white text-red-700'
  }[r] || 'bg-white/20 text-white'
}

async function loadJobs() {
  loadingJobs.value = true
  try {
    const { data } = await axios.get(`${API_BASE}/jobs`)
    if (data.code === 0) {
      jobs.value = data.data || []
    }
  } catch (e) {
    console.error('load jobs error:', e)
  } finally {
    loadingJobs.value = false
  }
}

async function selectJob(job) {
  currentJob.value = job
  try {
    const { data: ps } = await axios.get(`${API_BASE}/jobs/${job.id}/presets`)
    if (ps.code === 0 && ps.data.presets) {
      maxQuestions.value = ps.data.presets.max_questions || 12
    }
  } catch (e) { console.error('load presets error:', e) }

  try {
    const { data } = await axios.post(`${API_BASE}/sessions`, {
      job_id: job.id,
      candidate_name: ''
    })
    if (data.code === 0) {
      sessionId.value = data.data.session_id
      messages.value = []
      finished.value = false
      evaluation.value = null
    }
  } catch (e) {
    console.error('create session error:', e)
    alert('创建对话失败，请稍后再试')
  }
}

async function sendMessage() {
  const text = inputText.value.trim()
  if (!text || loading.value) return
  messages.value.push({ role: 'user', content: text, ts: new Date().toISOString() })
  inputText.value = ''
  loading.value = true
  await nextTick(); scrollToBottom()

  try {
    const { data } = await axios.post(`${API_BASE}/chat`, {
      session_id: sessionId.value,
      user_message: text
    })
    if (data.code === 0) {
      const aiMsg = data.data.assistant_message
      messages.value.push(aiMsg)
    } else {
      messages.value.push({ role: 'assistant', content: '【错误】' + (data.message || 'AI 服务异常') })
    }
  } catch (e) {
    console.error('chat error:', e)
    messages.value.push({ role: 'assistant', content: '【错误】网络异常，请稍后再试' })
  } finally {
    loading.value = false
    await nextTick(); scrollToBottom()
  }
}

async function finishDialog() {
  if (loading.value) return
  if (!confirm('确认结束对话并生成评价报告？')) return

  loading.value = true
  try {
    const { data } = await axios.post(`${API_BASE}/sessions/${sessionId.value}/finish`, {})
    if (data.code === 0) {
      evaluation.value = data.data
      finished.value = true
    } else {
      alert('生成评价失败: ' + (data.message || ''))
    }
  } catch (e) {
    console.error('finish error:', e)
    alert('网络异常，请稍后再试')
  } finally {
    loading.value = false
  }
}

function scrollToBottom() {
  if (msgListRef.value) {
    msgListRef.value.scrollTop = msgListRef.value.scrollHeight
  }
}

onMounted(() => {
  loadJobs()
})
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>