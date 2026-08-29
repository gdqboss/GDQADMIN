<!--
  AI HR 招聘报告列表（gdqadmin）
  - 筛选项: 岗位 + recommendation + 最低分
  - 点行进入详情
-->
<template>
  <div class="p-4">
    <PageHeader title="AI 招聘报告" subtitle="查看所有候选人 AI 评价报告">
      <template #actions>
        <button @click="fetchList" class="px-3 py-1.5 text-sm bg-primary text-white rounded-md">
          刷新
        </button>
      </template>
    </PageHeader>

    <!-- 筛选条 -->
    <div class="bg-white rounded-lg p-3 mb-4 flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs text-text-secondary mb-1">岗位</label>
        <select v-model="filterJobId" @change="fetchList"
          class="border border-slate-200 rounded px-2 py-1.5 text-sm min-w-[180px]">
          <option value="">全部</option>
          <option v-for="j in jobs" :key="j.id" :value="j.id">{{ j.title }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-text-secondary mb-1">推荐等级</label>
        <select v-model="filterRec" @change="fetchList"
          class="border border-slate-200 rounded px-2 py-1.5 text-sm">
          <option value="">全部</option>
          <option value="strong">强烈推荐</option>
          <option value="consider">可考虑</option>
          <option value="reject">不推荐</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-text-secondary mb-1">最低分</label>
        <input v-model.number="filterMinScore" @change="fetchList" type="number" min="0" max="100"
          class="border border-slate-200 rounded px-2 py-1.5 text-sm w-20" placeholder="0" />
      </div>
    </div>

    <!-- 报告列表 -->
    <div class="bg-white rounded-lg overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-text-secondary text-sm">加载中…</div>
      <div v-else-if="!list.length" class="p-8 text-center text-text-secondary text-sm">暂无报告</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-slate-50 text-xs text-text-secondary">
          <tr>
            <th class="px-3 py-2 text-left font-medium">候选人</th>
            <th class="px-3 py-2 text-left font-medium">岗位</th>
            <th class="px-3 py-2 text-center font-medium">综合分</th>
            <th class="px-3 py-2 text-center font-medium">推荐</th>
            <th class="px-3 py-2 text-left font-medium">亮点</th>
            <th class="px-3 py-2 text-left font-medium">红黄牌</th>
            <th class="px-3 py-2 text-left font-medium">时间</th>
            <th class="px-3 py-2 text-center font-medium">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in list" :key="r.id" class="border-t border-slate-100 hover:bg-slate-50">
            <td class="px-3 py-2 font-medium text-text-primary">{{ r.candidate_name || '—' }}</td>
            <td class="px-3 py-2 text-text-secondary">{{ r.job_title || '—' }}</td>
            <td class="px-3 py-2 text-center">
              <span class="font-semibold" :class="scoreColor(r.overall_score)">{{ r.overall_score }}</span>
            </td>
            <td class="px-3 py-2 text-center">
              <span :class="['inline-block px-2 py-0.5 rounded-full text-xs', recClass(r.recommendation)]">
                {{ recLabel(r.recommendation) }}
              </span>
            </td>
            <td class="px-3 py-2 text-xs text-text-secondary max-w-[200px] truncate" :title="r.highlights">
              {{ r.highlights }}
            </td>
            <td class="px-3 py-2 text-xs text-red-600 max-w-[150px] truncate" :title="r.red_flags">
              {{ r.red_flags || '—' }}
            </td>
            <td class="px-3 py-2 text-xs text-text-secondary whitespace-nowrap">
              {{ formatTime(r.created_at) }}
            </td>
            <td class="px-3 py-2 text-center">
              <router-link :to="`/ai-hr/reports/${r.id}`"
                class="text-primary text-xs hover:underline">查看</router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const list = ref([])
const jobs = ref([])
const loading = ref(false)
const filterJobId = ref('')
const filterRec = ref('')
const filterMinScore = ref(0)

function recLabel(r) {
  return { strong: '强烈推荐', consider: '可考虑', reject: '不推荐' }[r] || '—'
}

function recClass(r) {
  return {
    strong: 'bg-green-100 text-green-700',
    consider: 'bg-amber-100 text-amber-700',
    reject: 'bg-red-100 text-red-700'
  }[r] || 'bg-slate-100 text-slate-700'
}

function scoreColor(s) {
  if (s >= 80) return 'text-green-600'
  if (s >= 60) return 'text-amber-600'
  return 'text-red-600'
}

function formatTime(t) {
  if (!t) return '—'
  const d = new Date(t)
  return `${d.getMonth() + 1}/${d.getDate()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

async function fetchJobs() {
  try {
    const res = await api.get('/ai-hr/admin/jobs')
    if (res.code === 0) jobs.value = res.data || []
  } catch (e) { console.error('fetchJobs:', e) }
}

async function fetchList() {
  loading.value = true
  try {
    const params = {}
    if (filterJobId.value) params.job_id = filterJobId.value
    if (filterRec.value) params.recommendation = filterRec.value
    if (filterMinScore.value > 0) params.min_score = filterMinScore.value
    const res = await api.get('/ai-hr/evaluations', { params })
    if (res.code === 0) list.value = res.data || []
  } catch (e) {
    console.error('fetchList:', e)
    alert('加载失败: ' + (e.message || ''))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchJobs()
  fetchList()
})
</script>