<!--
  AI 数据中心 - 总览 (agent-memory MVP)
  2026-09-18 江小鱼立
  SGP dogfooding demo 数据展示
-->
<template>
  <div class="p-4">
    <PageHeader title="AI 数据中心" subtitle="公司全员智慧沉淀 · AI 自动分析 · 老板决策传承">
      <template #actions>
        <button @click="fetchAll" class="px-3 py-1.5 text-sm bg-primary text-white rounded-md">
          刷新
        </button>
      </template>
    </PageHeader>

    <!-- 加载中 -->
    <div v-if="loading" class="bg-white rounded-lg p-8 text-center text-text-secondary text-sm">
      加载中…
    </div>

    <!-- 总览统计卡片 (5 个) -->
    <div v-else class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
      <div class="bg-white rounded-lg p-4">
        <div class="text-xs text-text-secondary mb-1">累计事件</div>
        <div class="text-2xl font-semibold text-primary">{{ totals.total_events || 0 }}</div>
        <div class="text-xs text-text-secondary mt-1">任务/出勤/日志/agent 交流</div>
      </div>
      <div class="bg-white rounded-lg p-4">
        <div class="text-xs text-text-secondary mb-1">知识财富</div>
        <div class="text-2xl font-semibold text-primary">{{ totals.total_wisdom_entries || 0 }}</div>
        <div class="text-xs text-text-secondary mt-1">可复用优秀思维</div>
      </div>
      <div class="bg-white rounded-lg p-4">
        <div class="text-xs text-text-secondary mb-1">员工画像</div>
        <div class="text-2xl font-semibold text-primary">{{ totals.total_profiles || 0 }}</div>
        <div class="text-xs text-text-secondary mt-1">AI 自动打分</div>
      </div>
      <div class="bg-white rounded-lg p-4">
        <div class="text-xs text-text-secondary mb-1">AI 洞察</div>
        <div class="text-2xl font-semibold text-primary">{{ totals.total_insights || 0 }}</div>
        <div class="text-xs text-text-secondary mt-1">周报/月报</div>
      </div>
      <div class="bg-white rounded-lg p-4">
        <div class="text-xs text-text-secondary mb-1">机密库</div>
        <div class="text-2xl font-semibold text-primary">{{ secureCount || 0 }}</div>
        <div class="text-xs text-text-secondary mt-1">加密 · 仅授权可见</div>
      </div>
    </div>

    <!-- 双栏: 数据源分布 + Top 员工 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
      <!-- 数据源分布 -->
      <div class="bg-white rounded-lg p-4">
        <div class="text-sm font-medium mb-3">数据源分布</div>
        <div v-if="!sourceBreakdown.length" class="text-text-secondary text-sm">暂无数据</div>
        <div v-else class="space-y-2">
          <div v-for="s in sourceBreakdown" :key="s.source" class="flex items-center gap-3">
            <div class="w-24 text-xs text-text-secondary">{{ sourceLabel(s.source) }}</div>
            <div class="flex-1 bg-slate-100 rounded h-4 relative">
              <div class="bg-primary rounded h-4"
                   :style="{width: sourceBar(s.count) + '%'}"></div>
            </div>
            <div class="w-10 text-right text-sm font-medium">{{ s.count }}</div>
          </div>
        </div>
      </div>

      <!-- Top 员工 (按 reliability) -->
      <div class="bg-white rounded-lg p-4">
        <div class="text-sm font-medium mb-3">员工画像 Top (按可靠度)</div>
        <div v-if="!topProfiles.length" class="text-text-secondary text-sm">暂无数据</div>
        <div v-else class="space-y-2">
          <div v-for="p in topProfiles" :key="p.user_id"
               class="flex items-center justify-between p-2 bg-slate-50 rounded">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-full bg-primary text-white text-xs flex items-center justify-center">
                {{ p.user_id }}
              </div>
              <div>
                <div class="text-sm font-medium">{{ styleLabel(p.thinking_style) }}</div>
                <div class="text-xs text-text-secondary">可靠 {{ (p.reliability_score * 100).toFixed(0) }}% · {{ p.total_events }} 事件</div>
              </div>
            </div>
            <router-link to="/agent-memory/profiles" class="text-xs text-primary">详情</router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- 业务说明 -->
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm">
      <div class="font-medium text-amber-900 mb-1">📌 这是 AI 数据中心 demo</div>
      <ul class="text-amber-800 text-xs space-y-1">
        <li>• 真实环境: 员工每天在软件里的所有行为 → 自动累积到 AI 数据中心</li>
        <li>• 6 个月后 AI 才有显著价值 (现在 demo 数据为开发期样本)</li>
        <li>• 老板的机密配方 / 客户关系 → 加密存 (5 层防御)</li>
        <li>• 员工走了, 他的优秀思维还在公司</li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import PageHeader from '../../components/PageHeader.vue'

const loading = ref(true)
const totals = ref({})
const sourceBreakdown = ref([])
const topProfiles = ref([])
const secureCount = ref(0)

const sourceMap = {
  task: '任务完成',
  work_log: '工作日志',
  attendance: '出勤',
  agent_chat: 'AI 交流',
  oa_flow: 'OA 决策',
  manual: '手动录入',
}
const sourceLabel = (s) => sourceMap[s] || s

const styleMap = {
  relationship_first: '关系型员工',
  data_driven: '数据型员工',
  quick_executor: '执行型员工',
  mentor_type: '导师型员工',
}
const styleLabel = (s) => styleMap[s] || s || '未分类'

const sourceBar = (count) => {
  const max = Math.max(...sourceBreakdown.value.map(s => s.count), 1)
  return (count / max * 100).toFixed(0)
}

const fetchAll = async () => {
  loading.value = true
  try {
    const [overview, profiles, secure] = await Promise.all([
      api.get('/api/agent-memory/overview'),
      api.get('/api/agent-memory/profiles'),
      api.get('/api/secure-knowledge/documents'),
    ])
    if (overview.code === 0) {
      totals.value = overview.data.totals
      sourceBreakdown.value = overview.data.source_breakdown || []
      topProfiles.value = overview.data.top_profiles || []
    }
    if (profiles.code === 0) {
      topProfiles.value = profiles.data.slice(0, 5)
    }
    if (secure.code === 0) {
      secureCount.value = secure.data.length
    }
  } catch (e) {
    console.error('overview fetch fail', e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchAll)
</script>