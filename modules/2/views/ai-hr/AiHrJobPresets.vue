<!--
  AI HR 岗位招聘预设条件配置（gdqadmin）
  - 左列: 岗位列表 + 是否有 preset
  - 右列: 选中岗位后编辑预设
    - preset_questions: 必问的硬性问题（学历/经验/技能/可到岗/期望薪资等）
    - soft_traits: 软素质维度（沟通/逻辑/团队/抗压/学习等）
    - max_questions: 最多问几轮
-->
<template>
  <div class="p-4">
    <PageHeader title="岗位招聘配置" subtitle="为每个岗位配置 AI 招聘助手使用的硬性条件与软素质维度" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- 左侧: 岗位列表 -->
      <div class="bg-white rounded-lg p-3">
        <div class="text-xs text-text-secondary mb-2">选择岗位 ({{ jobs.length }})</div>
        <div v-if="loadingJobs" class="text-center py-4 text-xs text-text-secondary">加载中…</div>
        <div v-else-if="!jobs.length" class="text-center py-4 text-xs text-text-secondary">暂无岗位</div>
        <div v-else class="space-y-1 max-h-[70vh] overflow-y-auto">
          <button v-for="j in jobs" :key="j.id"
            @click="selectJob(j)"
            :class="['w-full text-left px-3 py-2 rounded-md text-sm transition',
                     selectedJobId === j.id ? 'bg-primary text-white' : 'hover:bg-slate-50']">
            <div class="font-medium truncate">{{ j.title }}</div>
            <div :class="['text-xs mt-0.5 flex items-center gap-2',
                          selectedJobId === j.id ? 'opacity-80' : 'text-text-secondary']">
              <span>{{ j.salary_range || '面议' }}</span>
              <span v-if="j.has_preset" :class="['inline-block px-1.5 py-0.5 rounded text-[10px]',
                                                selectedJobId === j.id ? 'bg-white/20' : 'bg-green-100 text-green-700']">
                已配置
              </span>
              <span v-else class="text-[10px] opacity-60">未配置</span>
            </div>
          </button>
        </div>
      </div>

      <!-- 右侧: 编辑器 -->
      <div class="lg:col-span-2 bg-white rounded-lg p-4">
        <div v-if="!selectedJobId" class="text-center py-12 text-text-secondary text-sm">
          请从左侧选择一个岗位
        </div>

        <template v-else>
          <div class="flex items-center justify-between mb-4">
            <div class="text-base font-medium text-text-primary">
              {{ jobs.find(j => j.id === selectedJobId)?.title }}
            </div>
            <div class="flex items-center gap-2">
              <button @click="loadPresets" class="px-3 py-1.5 text-sm border border-slate-200 rounded-md">
                重载
              </button>
              <button @click="save" :disabled="saving"
                class="px-4 py-1.5 text-sm bg-primary text-white rounded-md disabled:opacity-50">
                {{ saving ? '保存中…' : '保存' }}
              </button>
            </div>
          </div>

          <div v-if="loadingPresets" class="text-center py-8 text-sm text-text-secondary">加载中…</div>

          <template v-else>
            <!-- 最大轮数 -->
            <div class="mb-4">
              <label class="block text-xs text-text-secondary mb-1">最大对话轮数</label>
              <input v-model.number="form.max_questions" type="number" min="3" max="20"
                class="border border-slate-200 rounded px-2 py-1.5 text-sm w-24" />
              <span class="ml-2 text-xs text-text-secondary">应聘者超过此轮数 AI 会主动结束（建议 8-12）</span>
            </div>

            <!-- 预设问题 -->
            <div class="mb-4">
              <div class="flex items-center justify-between mb-2">
                <label class="text-xs text-text-secondary">预设硬性问题</label>
                <button @click="addQuestion" class="text-xs text-primary hover:underline">+ 添加问题</button>
              </div>
              <div v-if="!form.preset_questions.length" class="text-xs text-text-secondary italic py-3">
                暂无问题 — AI 会根据对话内容自由发挥（建议至少 3 个关键问题）
              </div>
              <div v-else class="space-y-2">
                <div v-for="(q, idx) in form.preset_questions" :key="idx"
                  class="border border-slate-100 rounded-md p-3 space-y-2 bg-slate-50">
                  <div class="flex items-start gap-2">
                    <input v-model="q.key" placeholder="key (英文,如 education)"
                      class="border border-slate-200 rounded px-2 py-1 text-xs w-40 bg-white" />
                    <input v-model="q.question" placeholder="问题描述 (中文)"
                      class="border border-slate-200 rounded px-2 py-1 text-xs flex-1 bg-white" />
                    <button @click="form.preset_questions.splice(idx, 1)"
                      class="text-xs text-red-500 hover:underline whitespace-nowrap">删除</button>
                  </div>
                  <div class="flex items-center gap-2">
                    <select v-model="q.type"
                      class="border border-slate-200 rounded px-2 py-1 text-xs bg-white">
                      <option value="text">文本</option>
                      <option value="number">数字</option>
                      <option value="select">选择</option>
                    </select>
                    <input v-model="q.requirement" placeholder="要求 / 通过条件（如:本科以上 / 5年+ / Vue熟练）"
                      class="border border-slate-200 rounded px-2 py-1 text-xs flex-1 bg-white" />
                    <label class="text-xs text-text-secondary flex items-center gap-1">
                      <input type="checkbox" v-model="q.required" /> 必问
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- 软素质维度 -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-xs text-text-secondary">软素质评价维度</label>
                <button @click="addTrait" class="text-xs text-primary hover:underline">+ 添加维度</button>
              </div>
              <div v-if="!form.soft_traits.length" class="text-xs text-text-secondary italic py-3">
                暂无维度 — AI 会自动识别对话中的软素质
              </div>
              <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <div v-for="(t, idx) in form.soft_traits" :key="idx"
                  class="border border-slate-100 rounded-md p-2.5 space-y-1.5 bg-slate-50">
                  <div class="flex items-center gap-2">
                    <input v-model="t.key" placeholder="key (如 communication)"
                      class="border border-slate-200 rounded px-2 py-1 text-xs w-40 bg-white" />
                    <input v-model="t.label" placeholder="显示名 (如 沟通表达)"
                      class="border border-slate-200 rounded px-2 py-1 text-xs flex-1 bg-white" />
                    <button @click="form.soft_traits.splice(idx, 1)"
                      class="text-xs text-red-500 hover:underline whitespace-nowrap">删除</button>
                  </div>
                  <input v-model="t.description" placeholder="评估标准 (例: 表达清晰有条理，能准确回答问题)"
                    class="border border-slate-200 rounded px-2 py-1 text-xs w-full bg-white" />
                  <div class="flex items-center gap-2 text-xs">
                    <span class="text-text-secondary">权重</span>
                    <input v-model.number="t.weight" type="number" step="0.05" min="0" max="1"
                      class="border border-slate-200 rounded px-2 py-1 w-20 bg-white" />
                    <span class="text-text-secondary">总权重需 ≤ 1</span>
                  </div>
                </div>
              </div>
              <div class="mt-1.5 text-xs"
                :class="totalWeight > 1 ? 'text-red-500' : 'text-text-secondary'">
                当前总权重: {{ totalWeight.toFixed(2) }}
                <span v-if="totalWeight > 1">⚠ 超出 1.0</span>
              </div>
            </div>
          </template>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const jobs = ref([])
const loadingJobs = ref(false)
const selectedJobId = ref(null)
const loadingPresets = ref(false)
const saving = ref(false)
const form = ref({
  preset_questions: [],
  soft_traits: [],
  max_questions: 10
})

const totalWeight = computed(() => {
  return form.value.soft_traits.reduce((s, t) => s + (Number(t.weight) || 0), 0)
})

async function loadJobs() {
  loadingJobs.value = true
  try {
    const res = await api.get('/ai-hr/admin/jobs')
    if (res.code === 0) jobs.value = res.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loadingJobs.value = false
  }
}

async function selectJob(j) {
  selectedJobId.value = j.id
  form.value = {
    preset_questions: [],
    soft_traits: [],
    max_questions: 10
  }
  await loadPresets()
}

async function loadPresets() {
  if (!selectedJobId.value) return
  loadingPresets.value = true
  try {
    const res = await api.get(`/ai-hr/admin/jobs/${selectedJobId.value}/presets`)
    if (res.code === 0 && res.data.presets) {
      form.value = {
        preset_questions: safeParse(res.data.presets.preset_questions, defaultQuestions()),
        soft_traits: safeParse(res.data.presets.soft_traits, defaultTraits()),
        max_questions: res.data.presets.max_questions || 10
      }
    } else {
      // 未配置 — 用模板
      form.value = {
        preset_questions: defaultQuestions(),
        soft_traits: defaultTraits(),
        max_questions: 10
      }
    }
  } catch (e) {
    console.error(e)
    alert('加载失败: ' + (e.message || ''))
  } finally {
    loadingPresets.value = false
  }
}

function safeParse(v, fallback) {
  if (Array.isArray(v)) return v
  if (typeof v === 'string') {
    try { const p = JSON.parse(v); return Array.isArray(p) ? p : fallback } catch { return fallback }
  }
  return fallback
}

function defaultQuestions() {
  return [
    { key: 'education', question: '你的最高学历是?', type: 'text', requirement: '本科及以上', required: true },
    { key: 'experience', question: '你有几年的相关工作经验?', type: 'number', requirement: '3年以上', required: true },
    { key: 'skills', question: '你最熟练的技术栈是什么?', type: 'text', requirement: 'Vue / React 熟练', required: true },
    { key: 'available_at', question: '最快什么时候能到岗?', type: 'text', requirement: '2周内', required: false },
    { key: 'salary_expectation', question: '你的期望薪资范围是?', type: 'text', requirement: '在岗位预算内', required: false },
  ]
}

function defaultTraits() {
  return [
    { key: 'communication', label: '沟通表达', description: '表达清晰有条理，能准确回答问题', weight: 0.2 },
    { key: 'logic', label: '逻辑思维', description: '思考问题有层次，能举一反三', weight: 0.2 },
    { key: 'teamwork', label: '团队协作', description: '有团队合作意识，能换位思考', weight: 0.2 },
    { key: 'stability', label: '稳定性', description: '职业规划清晰，跳槽不频繁', weight: 0.2 },
    { key: 'learning_ability', label: '学习意愿', description: '主动学习新技术的意愿和能力', weight: 0.2 },
  ]
}

function addQuestion() {
  form.value.preset_questions.push({ key: '', question: '', type: 'text', requirement: '', required: false })
}

function addTrait() {
  form.value.soft_traits.push({ key: '', label: '', description: '', weight: 0.1 })
}

async function save() {
  if (!selectedJobId.value) return
  // 验证
  const badQ = form.value.preset_questions.find(q => !q.key || !q.question)
  if (badQ) { alert('预设问题的 key 和 question 都不能为空'); return }
  const badT = form.value.soft_traits.find(t => !t.key || !t.label)
  if (badT) { alert('软素质维度的 key 和 label 都不能为空'); return }
  if (totalWeight.value > 1) { alert('权重总和不能超过 1.0'); return }

  saving.value = true
  try {
    const res = await api.put(`/ai-hr/admin/jobs/${selectedJobId.value}/presets`, form.value)
    if (res.code === 0) {
      alert('已保存')
      await loadJobs()  // 刷新"已配置"标记
    } else {
      alert('保存失败: ' + (res.message || ''))
    }
  } catch (e) {
    console.error(e)
    alert('保存失败: ' + (e.message || ''))
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadJobs()
})
</script>