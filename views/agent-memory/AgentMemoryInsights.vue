<!--
  AI 数据中心 - AI 洞察 (周报/月报)
  2026-09-18 江小鱼立
-->
<template>
  <div class="p-4">
    <PageHeader title="AI 洞察报告" subtitle="AI 自动生成 · 员工周报 + 公司月报" />

    <div v-if="loading" class="bg-white rounded-lg p-8 text-center text-text-secondary text-sm">
      加载中…
    </div>
    <div v-else-if="!list.length" class="bg-white rounded-lg p-8 text-center text-text-secondary text-sm">
      暂无洞察报告
    </div>

    <div v-else class="space-y-4">
      <div v-for="i in list" :key="i.id" class="bg-white rounded-lg p-4">
        <!-- 头部 -->
        <div class="flex items-start gap-3 mb-3">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs px-2 py-0.5 rounded"
                    :class="scopeClass(i.scope)">
                {{ scopeLabel(i.scope) }}
              </span>
              <span class="text-xs text-text-secondary">{{ periodLabel(i.period) }}</span>
              <span class="text-xs text-text-secondary">
                {{ i.period_start }} ~ {{ i.period_end }}
              </span>
            </div>
            <div class="font-medium">{{ i.title }}</div>
            <div class="text-sm text-slate-700 mt-1">{{ i.summary }}</div>
          </div>
          <div class="text-xs text-text-secondary text-right">
            <div>{{ i.ai_model }}</div>
            <div>{{ i.ai_tokens_used }} tokens</div>
          </div>
        </div>

        <!-- 三段: key_patterns / recommendations / risks -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
          <div v-if="i.key_patterns" class="bg-blue-50 rounded p-3">
            <div class="text-xs font-medium text-blue-800 mb-1">📊 关键模式</div>
            <ul class="text-blue-900 text-xs space-y-0.5">
              <li v-for="(p, idx) in parseList(i.key_patterns)" :key="idx">• {{ p }}</li>
            </ul>
          </div>
          <div v-if="i.recommendations" class="bg-green-50 rounded p-3">
            <div class="text-xs font-medium text-green-800 mb-1">💡 建议</div>
            <ul class="text-green-900 text-xs space-y-0.5">
              <li v-for="(r, idx) in parseList(i.recommendations)" :key="idx">• {{ r }}</li>
            </ul>
          </div>
          <div v-if="i.risks" class="bg-amber-50 rounded p-3">
            <div class="text-xs font-medium text-amber-800 mb-1">⚠️ 风险</div>
            <ul class="text-amber-900 text-xs space-y-0.5">
              <li v-for="(r, idx) in parseList(i.risks)" :key="idx">• {{ r }}</li>
            </ul>
          </div>
        </div>

        <!-- 评分 -->
        <div v-if="i.user_rating" class="text-xs text-text-secondary mt-3">
          老板评分: {{ '★'.repeat(i.user_rating) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import PageHeader from '../../components/PageHeader.vue'

const loading = ref(true)
const list = ref([])

const scopeMap = { user: '个人', team: '团队', company: '公司' }
const scopeLabel = (s) => scopeMap[s] || s
const scopeClass = (s) => ({
  'bg-blue-100 text-blue-800': s === 'user',
  'bg-purple-100 text-purple-800': s === 'team',
  'bg-primary text-white': s === 'company',
})

const periodMap = { daily: '日报', weekly: '周报', monthly: '月报', quarterly: '季报' }
const periodLabel = (p) => periodMap[p] || p

const parseList = (raw) => {
  if (Array.isArray(raw)) return raw
  if (typeof raw === 'string') {
    try {
      const v = JSON.parse(raw)
      return Array.isArray(v) ? v : [raw]
    } catch { return [raw] }
  }
  return []
}

const fetchList = async () => {
  loading.value = true
  try {
    const r = await api.get('/api/agent-memory/insights')
    if (r.code === 0) list.value = r.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchList)
</script>