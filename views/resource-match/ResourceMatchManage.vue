<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api.js'

const loading = ref(false)
const loadError = ref('')
const list = ref([])
const filterDir = ref('all')    // all / demand / supply
const filterCat = ref('all')    // all / funding / talent / channel / tech / venue / policy
const filterStatus = ref('all') // all / open / matched / closed
const searchKw = ref('')

const selectedPost = ref(null)
const showMatchModal = ref(false)
const matchWithId = ref('')

const DIR_LABELS = { demand: '需求', supply: '供给' }
const CAT_LABELS = {
  funding: '资金', talent: '人才', channel: '渠道',
  tech: '技术', venue: '场地', policy: '政策'
}
const DIR_TABS = [
  { value: 'all', label: '全部' },
  { value: 'demand', label: '需求' },
  { value: 'supply', label: '供给' }
]
const STATUS_TABS = [
  { value: 'all', label: '全部' },
  { value: 'open', label: '开放中' },
  { value: 'matched', label: '已匹配' },
  { value: 'closed', label: '已关闭' }
]

function dirClass(d) {
  return d === 'demand'
    ? 'bg-blue-50 text-blue-700 border-blue-200'
    : 'bg-emerald-50 text-emerald-700 border-emerald-200'
}

function statusClass(s) {
  return {
    open:    'bg-green-50 text-green-700 border-green-200',
    matched: 'bg-purple-50 text-purple-700 border-purple-200',
    closed:  'bg-gray-50 text-gray-600 border-gray-200'
  }[s] || 'bg-gray-50 text-gray-700 border-gray-200'
}

function statusLabel(s) {
  return { open: '开放中', matched: '已匹配', closed: '已关闭' }[s] || s
}

function formatDate(s) {
  if (!s) return '-'
  return s.slice(0, 16).replace('T', ' ')
}

const filtered = computed(() => {
  return list.value.filter(p => {
    if (filterDir.value !== 'all' && p.direction !== filterDir.value) return false
    if (filterCat.value !== 'all' && p.category !== filterCat.value) return false
    if (filterStatus.value !== 'all' && p.status !== filterStatus.value) return false
    if (searchKw.value && !(p.title + ' ' + p.description + ' ' + p.company).includes(searchKw.value)) return false
    return true
  })
})

const stats = computed(() => {
  const s = { total: list.value.length, demand: 0, supply: 0, open: 0, matched: 0 }
  list.value.forEach(p => {
    if (p.direction === 'demand') s.demand++
    if (p.direction === 'supply') s.supply++
    if (p.status === 'open') s.open++
    if (p.status === 'matched') s.matched++
  })
  return s
})

async function loadList() {
  loading.value = true
  loadError.value = ''
  try {
    const res = await api.get('/resource-match', { params: { limit: 100 } })
    list.value = res.data || res.list || []
  } catch (e) {
    loadError.value = e.message || '加载失败'
    list.value = []
  } finally {
    loading.value = false
  }
}

function openMatch(post) {
  selectedPost.value = post
  matchWithId.value = ''
  showMatchModal.value = true
}

async function submitMatch() {
  if (!matchWithId.value) {
    alert('请输入对方记录 ID')
    return
  }
  try {
    await api.put(`/resource-match/${selectedPost.value.id}/match`, {
      matched_with_id: parseInt(matchWithId.value)
    })
    showMatchModal.value = false
    await loadList()
  } catch (e) {
    alert('匹配失败: ' + (e.message || ''))
  }
}

async function closePost(post) {
  if (!confirm(`确认关闭 "${post.title}"?`)) return
  try {
    await api.put(`/resource-match/${post.id}/close`)
    await loadList()
  } catch (e) {
    alert('关闭失败: ' + (e.message || ''))
  }
}

async function deletePost(post) {
  if (!confirm(`确认删除 "${post.title}"?`)) return
  try {
    await api.delete(`/resource-match/${post.id}`)
    await loadList()
  } catch (e) {
    alert('删除失败: ' + (e.message || ''))
  }
}

onMounted(loadList)
</script>

<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="bg-white border-b px-4 py-3">
      <h1 class="text-lg font-bold text-gray-800">资源对接管理</h1>
      <p class="text-xs text-gray-500 mt-1">管理企业间供需匹配 (资金/人才/渠道/技术/场地/政策)</p>
    </div>

    <!-- 统计 -->
    <div class="grid grid-cols-5 gap-2 p-4">
      <div class="bg-white rounded-lg p-3 text-center border">
        <div class="text-2xl font-bold text-gray-800">{{ stats.total }}</div>
        <div class="text-xs text-gray-500 mt-1">总数</div>
      </div>
      <div class="bg-white rounded-lg p-3 text-center border">
        <div class="text-2xl font-bold text-blue-600">{{ stats.demand }}</div>
        <div class="text-xs text-gray-500 mt-1">需求</div>
      </div>
      <div class="bg-white rounded-lg p-3 text-center border">
        <div class="text-2xl font-bold text-emerald-600">{{ stats.supply }}</div>
        <div class="text-xs text-gray-500 mt-1">供给</div>
      </div>
      <div class="bg-white rounded-lg p-3 text-center border">
        <div class="text-2xl font-bold text-green-600">{{ stats.open }}</div>
        <div class="text-xs text-gray-500 mt-1">开放</div>
      </div>
      <div class="bg-white rounded-lg p-3 text-center border">
        <div class="text-2xl font-bold text-purple-600">{{ stats.matched }}</div>
        <div class="text-xs text-gray-500 mt-1">已匹配</div>
      </div>
    </div>

    <!-- 搜索 -->
    <div class="px-4 pb-3">
      <input v-model="searchKw" type="text" placeholder="搜索标题/描述/公司…"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>

    <!-- 方向筛选 -->
    <div class="bg-white border-b">
      <div class="flex">
        <button v-for="tab in DIR_TABS" :key="tab.value" @click="filterDir = tab.value"
          :class="['flex-1 px-4 py-3 text-sm font-medium border-b-2',
                   filterDir === tab.value ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500']">
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- 状态筛选 -->
    <div class="bg-white border-b sticky top-0 z-10">
      <div class="flex overflow-x-auto">
        <button v-for="tab in STATUS_TABS" :key="tab.value" @click="filterStatus = tab.value"
          :class="['px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2',
                   filterStatus === tab.value ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500']">
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- 列表 -->
    <div class="p-4 space-y-3">
      <div v-if="loading" class="text-center py-12 text-gray-400">加载中…</div>
      <div v-else-if="loadError" class="text-center py-12">
        <div class="text-red-500 mb-2">{{ loadError }}</div>
        <button @click="loadList" class="text-blue-600 text-sm">重试</button>
      </div>
      <div v-else-if="filtered.length === 0" class="text-center py-12 text-gray-400">暂无记录</div>

      <div v-for="post in filtered" :key="post.id" class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-start justify-between mb-2">
          <div class="font-medium text-gray-800 flex-1">{{ post.title }}</div>
          <div class="flex gap-1">
            <span :class="['px-2 py-0.5 text-xs rounded-full border', dirClass(post.direction)]">
              {{ DIR_LABELS[post.direction] }}
            </span>
            <span :class="['px-2 py-0.5 text-xs rounded-full border', statusClass(post.status)]">
              {{ statusLabel(post.status) }}
            </span>
          </div>
        </div>

        <div class="text-sm text-gray-600 mb-3">{{ post.description || '(无描述)' }}</div>

        <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 mb-3">
          <div>📂 {{ CAT_LABELS[post.category] || post.category }}</div>
          <div v-if="post.company">🏢 {{ post.company }}</div>
          <div>👤 {{ post.user_name }} (ID: {{ post.user_id }})</div>
          <div v-if="post.contact_phone">📞 {{ post.contact_phone }}</div>
          <div class="col-span-2 text-gray-400">📅 {{ formatDate(post.created_at) }}</div>
        </div>

        <div class="flex gap-2 border-t pt-3">
          <button v-if="post.status === 'open'" @click="openMatch(post)" class="flex-1 bg-purple-600 text-white py-2 rounded-lg text-sm">标记匹配</button>
          <button v-if="post.status === 'open'" @click="closePost(post)" class="flex-1 bg-gray-600 text-white py-2 rounded-lg text-sm">关闭</button>
          <button @click="deletePost(post)" class="px-4 bg-red-50 text-red-600 border border-red-200 py-2 rounded-lg text-sm">删除</button>
        </div>
      </div>
    </div>

    <!-- 匹配弹窗 -->
    <div v-if="showMatchModal" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center" @click.self="showMatchModal = false">
      <div class="bg-white rounded-t-2xl sm:rounded-xl w-full sm:max-w-md">
        <div class="border-b px-4 py-3 flex justify-between items-center">
          <h3 class="font-bold">标记匹配</h3>
          <button @click="showMatchModal = false" class="text-gray-400">✕</button>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-sm text-gray-600">记录: {{ selectedPost?.title }}</div>
          <div>
            <label class="block text-sm font-medium mb-1">对方记录 ID</label>
            <input v-model="matchWithId" type="number" placeholder="如: 1 (另一条 resource_posts 的 id)"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <div class="text-xs text-gray-500 mt-1">在「资源对接列表」中查看对方记录的 ID</div>
          </div>
          <button @click="submitMatch" class="w-full bg-purple-600 text-white py-2.5 rounded-lg text-sm font-medium">确认匹配</button>
        </div>
      </div>
    </div>
  </div>
</template>
