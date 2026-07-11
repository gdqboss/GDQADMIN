<template>
  <div class="crm-root">
    <header class="crm-header">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-purple-500 grid place-items-center text-white">📊</div>
        <div>
          <h1 class="text-lg font-semibold text-white">客户线索看板</h1>
          <p class="text-xs text-white/80">CRM Pipeline · 实时同步</p>
        </div>
      </div>
      <div class="text-xs text-white/80">{{ now }}</div>
    </header>

    <!-- 6 阶段卡片 -->
    <section class="kpi-grid">
      <div v-for="(card, i) in kpiCards" :key="i" class="kpi-card" :class="card.tone">
        <div class="kpi-label">{{ card.label }}</div>
        <div class="kpi-value">{{ card.value }}</div>
        <div class="kpi-sub">{{ card.sub }}</div>
      </div>
    </section>

    <!-- 最新跟进 -->
    <section class="rounded-2xl bg-white shadow-sm p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-slate-800">最新 5 个线索</h2>
        <span class="text-xs text-slate-400">实时刷新</span>
      </div>
      <div v-if="loading" class="text-sm text-slate-400 py-12 text-center">加载中...</div>
      <div v-else-if="!recent.length" class="text-sm text-slate-400 py-12 text-center">暂无客户线索</div>
      <div v-else class="space-y-3">
        <div v-for="lead in recent" :key="lead.id" class="border border-slate-100 rounded-xl p-3 hover:border-violet-200 transition-colors">
          <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="font-medium text-slate-800 truncate">{{ lead.name }}</span>
                <span :class="['px-2 py-0.5 text-xs rounded-full', stageClass(lead.stage)]">{{ stageLabel(lead.stage) }}</span>
              </div>
              <div class="text-xs text-slate-500 mt-1 truncate">{{ lead.company }}</div>
              <div class="text-xs text-violet-600 mt-1">📞 {{ lead.phone }} · 兴趣：{{ lead.interest || '未填写' }}</div>
            </div>
            <div class="text-right text-xs text-slate-400 ml-3">
              <div>{{ lead.followup_count }} 次跟进</div>
              <div class="mt-1">{{ formatTime(lead.updated_at) }}</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 全部线索列表 -->
    <section class="rounded-2xl bg-white shadow-sm p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-slate-800">全部线索</h2>
        <span class="text-xs text-slate-400">{{ total }} 个</span>
      </div>
      <div class="stage-tabs">
        <button v-for="tab in tabs" :key="tab.value" @click="filter = tab.value"
          :class="['tab-btn', filter === tab.value ? 'active' : '']">
          {{ tab.label }} <span v-if="tab.count" class="ml-1 text-xs">({{ tab.count }})</span>
        </button>
      </div>
      <div class="space-y-2 mt-3">
        <div v-for="lead in filtered" :key="lead.id" class="flex items-center gap-3 py-2 border-b border-slate-100 last:border-0">
          <div class="w-8 h-8 rounded-full grid place-items-center text-white text-xs font-medium" :class="avatarColor(lead.id)">
            {{ lead.name.slice(0, 1) }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-800 truncate">{{ lead.name }}</div>
            <div class="text-xs text-slate-500 truncate">{{ lead.company }} · {{ lead.interest }}</div>
          </div>
          <span :class="['px-2 py-0.5 text-xs rounded-full', stageClass(lead.stage)]">{{ stageLabel(lead.stage) }}</span>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const loading = ref(true)
const stats = ref({})
const recent = ref([])
const leads = ref([])
const total = ref(0)
const filter = ref('')
const now = ref('')

const tabs = computed(() => [
  { label: '全部', value: '', count: total.value },
  { label: '新线索', value: 'new', count: stats.value.new_count || 0 },
  { label: '已触达', value: 'contacted', count: stats.value.contacted || 0 },
  { label: '演示', value: 'demo', count: stats.value.demo_count || 0 },
  { label: '谈判', value: 'negotiation', count: stats.value.negotiation || 0 },
  { label: '成交', value: 'won', count: stats.value.won || 0 },
])

const kpiCards = computed(() => [
  { label: '总线索数', value: total.value, sub: '累计', tone: 'tone-violet' },
  { label: '本月新增', value: stats.value.new_count || 0, sub: '本周', tone: 'tone-blue' },
  { label: '演示中', value: stats.value.demo_count || 0, sub: '推进中', tone: 'tone-amber' },
  { label: '已成交', value: stats.value.won || 0, sub: '本月', tone: 'tone-emerald' },
  { label: '转化率', value: total.value ? Math.round((stats.value.won || 0) / total.value * 100) + '%' : '0%', sub: 'lead → 客户', tone: 'tone-pink' },
  { label: '跟进次数', value: leads.value.reduce((s, l) => s + (l.followup_count || 0), 0), sub: '累计', tone: 'tone-slate' },
])

const filtered = computed(() => filter.value ? leads.value.filter(l => l.stage === filter.value) : leads.value)

function stageClass(s) {
  const m = {
    new: 'bg-slate-100 text-slate-600',
    contacted: 'bg-blue-100 text-blue-700',
    demo: 'bg-amber-100 text-amber-700',
    negotiation: 'bg-purple-100 text-purple-700',
    won: 'bg-emerald-100 text-emerald-700',
    lost: 'bg-rose-100 text-rose-700',
  }
  return m[s] || m.new
}

function stageLabel(s) {
  return ({ new: '新线索', contacted: '已触达', demo: '演示', negotiation: '谈判', won: '已成交', lost: '流失' })[s] || s
}

function avatarColor(id) {
  const colors = ['bg-violet-500', 'bg-pink-500', 'bg-blue-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500']
  return colors[id % colors.length]
}

function formatTime(t) {
  if (!t) return ''
  const d = new Date(t)
  return `${d.getMonth() + 1}-${d.getDate()} ${d.getHours()}:${String(d.getMinutes()).padStart(2, '0')}`
}

async function load() {
  try {
    const [dashRes, listRes] = await Promise.all([
      fetch('/api/hqh5/crm/dashboard'),
      fetch('/api/hqh5/crm/leads?pageSize=50')
    ])
    const dash = await dashRes.json()
    const list = await listRes.json()
    if (dash.code === 0) {
      stats.value = dash.data.stats || {}
      recent.value = dash.data.recent || []
    }
    if (list.code === 0) {
      leads.value = list.data.list || []
      total.value = list.data.total || 0
    }
  } catch (e) {
    console.error('CRM load fail:', e)
  } finally {
    loading.value = false
  }
}

function tick() {
  const d = new Date()
  now.value = `${d.getHours()}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`
}

onMounted(() => {
  load()
  tick()
  setInterval(tick, 1000)
  setInterval(load, 30000) // 30s 刷新
})
</script>

<style scoped>
.crm-root { @apply p-4 pb-32 space-y-4 min-h-screen bg-slate-50; }
.crm-header { @apply bg-gradient-to-r from-violet-500 to-purple-500 rounded-2xl p-4 flex items-center justify-between shadow-md; }
.kpi-grid { @apply grid grid-cols-3 gap-3; }
.kpi-card { @apply rounded-2xl p-4 shadow-sm; }
.kpi-label { @apply text-xs text-slate-500; }
.kpi-value { @apply text-2xl font-bold text-slate-800 mt-1; }
.kpi-sub { @apply text-xs text-slate-400 mt-1; }
.tone-violet { @apply bg-gradient-to-br from-violet-50 to-violet-100; }
.tone-blue { @apply bg-gradient-to-br from-blue-50 to-blue-100; }
.tone-amber { @apply bg-gradient-to-br from-amber-50 to-amber-100; }
.tone-emerald { @apply bg-gradient-to-br from-emerald-50 to-emerald-100; }
.tone-pink { @apply bg-gradient-to-br from-pink-50 to-pink-100; }
.tone-slate { @apply bg-gradient-to-br from-slate-50 to-slate-100; }
.stage-tabs { @apply flex gap-2 overflow-x-auto pb-2; }
.tab-btn { @apply px-3 py-1.5 text-xs text-slate-600 rounded-full border border-slate-200 hover:bg-slate-50 whitespace-nowrap; }
.tab-btn.active { @apply bg-violet-500 text-white border-violet-500; }
</style>
