<template>
  <div class="min-h-screen bg-slate-50 p-6">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <span class="material-symbols-outlined text-4xl text-amber-600">temple_buddhist</span>
          <div>
            <h1 class="text-2xl font-bold text-slate-900">寺庙管理</h1>
            <p class="text-sm text-slate-500 mt-1">Temple Management · 信众/牌位/活动/审核一体化</p>
          </div>
        </div>
        <div class="text-xs text-slate-400">数据更新于 {{ lastRefresh }}</div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 mb-6 bg-white rounded-xl p-1 shadow-sm overflow-x-auto">
        <button v-for="t in tabs" :key="t.key"
          @click="activeTab = t.key"
          :class="[
            'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition',
            activeTab === t.key
              ? 'bg-amber-600 text-white shadow-sm'
              : 'text-slate-600 hover:bg-slate-100'
          ]">
          <span class="material-symbols-outlined text-base">{{ t.icon }}</span>
          {{ t.label }}
          <span v-if="t.badge" :class="[
            'ml-1 px-2 py-0.5 rounded-full text-xs',
            activeTab === t.key ? 'bg-white text-amber-700' : 'bg-amber-100 text-amber-700'
          ]">{{ t.badge }}</span>
        </button>
      </div>

      <!-- Tab Content -->
      <div v-if="activeTab === 'dashboard'">
        <!-- Dashboard Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-xl p-5 shadow-sm">
            <div class="text-sm text-slate-500 mb-1">总牌位数</div>
            <div class="text-3xl font-bold text-slate-900">{{ stats.casket_total || 0 }}</div>
            <div class="text-xs text-slate-400 mt-1">已用 {{ stats.casket_used || 0 }} · 空闲 {{ stats.casket_vacant || 0 }}</div>
          </div>
          <div class="bg-white rounded-xl p-5 shadow-sm">
            <div class="text-sm text-slate-500 mb-1">登记逝者</div>
            <div class="text-3xl font-bold text-slate-900">{{ stats.ancestor_total || 0 }}</div>
          </div>
          <div class="bg-white rounded-xl p-5 shadow-sm">
            <div class="text-sm text-slate-500 mb-1">在寺法师</div>
            <div class="text-3xl font-bold text-slate-900">{{ stats.monk_active || 0 }}</div>
          </div>
          <div class="bg-white rounded-xl p-5 shadow-sm">
            <div class="text-sm text-slate-500 mb-1">资深信徒</div>
            <div class="text-3xl font-bold text-amber-600">{{ stats.senior_count || 0 }}</div>
          </div>
        </div>

        <!-- Pending Edit Requests -->
        <div class="bg-white rounded-xl p-6 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-900">待审核编辑请求</h3>
            <button @click="activeTab = 'review'" class="text-sm text-amber-600 hover:underline">查看全部 →</button>
          </div>
          <div v-if="stats.edit_pending > 0" class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
            ⚠️ 当前有 <strong>{{ stats.edit_pending }}</strong> 条待审核请求
          </div>
          <div v-else class="text-sm text-slate-400 text-center py-4">暂无待审核请求</div>
        </div>

        <!-- Recent Activities -->
        <div class="bg-white rounded-xl p-6 shadow-sm mt-4">
          <h3 class="font-semibold text-slate-900 mb-4">近期活动</h3>
          <div class="space-y-3">
            <div v-for="a in activities.slice(0, 3)" :key="a.id"
              class="flex items-start gap-3 p-3 bg-slate-50 rounded-lg">
              <span class="material-symbols-outlined text-amber-600">event</span>
              <div class="flex-1">
                <div class="font-medium text-slate-900">{{ a.title }}</div>
                <div class="text-xs text-slate-500 mt-1">{{ formatDate(a.activity_date) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Caskets Tab -->
      <div v-if="activeTab === 'caskets'">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
          <div class="p-4 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <input v-model="casketSearch" placeholder="搜索牌位编号 / 联系人..."
                class="px-3 py-2 border border-slate-200 rounded-lg text-sm w-64" />
              <select v-model="casketFilter" class="px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <option value="">全部状态</option>
                <option value="occupied">已用</option>
                <option value="vacant">空闲</option>
              </select>
            </div>
            <div class="text-sm text-slate-500">共 {{ filteredCaskets.length }} 条</div>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="text-left px-4 py-3">牌位编号</th>
                <th class="text-left px-4 py-3">位置</th>
                <th class="text-left px-4 py-3">供奉逝者</th>
                <th class="text-left px-4 py-3">联系人</th>
                <th class="text-left px-4 py-3">状态</th>
                <th class="text-left px-4 py-3">下次续费</th>
                <th class="text-left px-4 py-3">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in filteredCaskets" :key="c.id" class="border-t border-slate-100 hover:bg-slate-50">
                <td class="px-4 py-3 font-mono text-amber-700">{{ c.casket_code }}</td>
                <td class="px-4 py-3 text-slate-600">{{ c.hall_name }} {{ c.floor_no }}层-{{ c.row_no }}排-{{ c.position_no }}</td>
                <td class="px-4 py-3">{{ c.ancestor_name || '-' }}</td>
                <td class="px-4 py-3">{{ c.family_contact || '-' }} <span class="text-xs text-slate-400">{{ c.family_phone }}</span></td>
                <td class="px-4 py-3">
                  <span :class="[
                    'px-2 py-1 rounded-full text-xs',
                    c.status === 'occupied' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'
                  ]">{{ c.status === 'occupied' ? '已供奉' : '空闲' }}</span>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ c.next_renewal_date ? formatDate(c.next_renewal_date) : '-' }}</td>
                <td class="px-4 py-3">
                  <button @click="openCasketDetail(c)" class="text-amber-600 hover:underline text-xs">查看</button>
                  <button @click="generateQRCode(c)" class="ml-2 text-amber-600 hover:underline text-xs">生成二维码</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Ancestors Tab -->
      <div v-if="activeTab === 'ancestors'">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
          <div class="p-4 border-b border-slate-200">
            <input v-model="ancestorSearch" placeholder="搜索逝者姓名..."
              class="px-3 py-2 border border-slate-200 rounded-lg text-sm w-64" />
            <span class="ml-3 text-sm text-slate-500">共 {{ filteredAncestors.length }} 条</span>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="text-left px-4 py-3">姓名</th>
                <th class="text-left px-4 py-3">性别</th>
                <th class="text-left px-4 py-3">生卒年</th>
                <th class="text-left px-4 py-3">所属牌位</th>
                <th class="text-left px-4 py-3">家属关系</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in filteredAncestors" :key="a.id" class="border-t border-slate-100 hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-900">{{ a.name }}</td>
                <td class="px-4 py-3">{{ a.gender === 'male' ? '男' : '女' }}</td>
                <td class="px-4 py-3 text-slate-600">
                  {{ a.birth_date ? formatDate(a.birth_date).substring(0, 10) : '-' }}
                  <span class="text-slate-400 mx-1">→</span>
                  {{ a.death_date ? formatDate(a.death_date).substring(0, 10) : '-' }}
                </td>
                <td class="px-4 py-3 font-mono text-amber-700">{{ casketMap[a.id] || '-' }}</td>
                <td class="px-4 py-3 text-slate-600">{{ a.family_relation || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Members Tab -->
      <div v-if="activeTab === 'members'">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
          <div class="p-4 border-b border-slate-200 flex items-center gap-3">
            <input v-model="memberSearch" placeholder="搜索手机号 / 姓名..."
              class="px-3 py-2 border border-slate-200 rounded-lg text-sm w-64" />
            <select v-model="memberFilter" class="px-3 py-2 border border-slate-200 rounded-lg text-sm">
              <option value="">全部</option>
              <option value="1">仅资深信徒</option>
              <option value="0">普通用户</option>
            </select>
            <span class="ml-auto text-sm text-slate-500">共 {{ filteredMembers.length }} 条</span>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="text-left px-4 py-3">姓名</th>
                <th class="text-left px-4 py-3">手机号</th>
                <th class="text-left px-4 py-3">主站角色</th>
                <th class="text-left px-4 py-3">供奉牌位</th>
                <th class="text-left px-4 py-3">资深信徒</th>
                <th class="text-left px-4 py-3">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in filteredMembers" :key="m.id" class="border-t border-slate-100 hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-900">{{ m.name }}</td>
                <td class="px-4 py-3 text-slate-600">{{ m.phone }}</td>
                <td class="px-4 py-3">
                  <span :class="[
                    'px-2 py-1 rounded-full text-xs',
                    m.role === 'admin' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600'
                  ]">{{ m.role }}</span>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ m.casket_count }} 个</td>
                <td class="px-4 py-3">
                  <span v-if="m.temple_level === 1" class="px-2 py-1 rounded-full text-xs bg-amber-100 text-amber-700">✨ 资深</span>
                  <span v-else class="px-2 py-1 rounded-full text-xs bg-slate-100 text-slate-500">普通</span>
                </td>
                <td class="px-4 py-3">
                  <button @click="toggleSenior(m)"
                    :class="[
                      'px-3 py-1 rounded text-xs font-medium',
                      m.temple_level === 1
                        ? 'bg-slate-200 text-slate-700 hover:bg-slate-300'
                        : 'bg-amber-600 text-white hover:bg-amber-700'
                    ]">
                    {{ m.temple_level === 1 ? '降为普通' : '升为资深' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Edit Review Tab -->
      <div v-if="activeTab === 'review'">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
          <div class="p-4 border-b border-slate-200 flex items-center gap-3">
            <select v-model="reviewFilter" class="px-3 py-2 border border-slate-200 rounded-lg text-sm">
              <option value="pending">待审核</option>
              <option value="approved">已通过</option>
              <option value="rejected">已驳回</option>
              <option value="">全部</option>
            </select>
            <span class="text-sm text-slate-500">共 {{ filteredReviews.length }} 条</span>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="text-left px-4 py-3">牌位</th>
                <th class="text-left px-4 py-3">提交人</th>
                <th class="text-left px-4 py-3">类型</th>
                <th class="text-left px-4 py-3">说明</th>
                <th class="text-left px-4 py-3">提交时间</th>
                <th class="text-left px-4 py-3">状态</th>
                <th class="text-left px-4 py-3">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in filteredReviews" :key="r.id" class="border-t border-slate-100">
                <td class="px-4 py-3 font-mono text-amber-700">{{ r.casket_code }}</td>
                <td class="px-4 py-3">{{ r.submitter_name }}</td>
                <td class="px-4 py-3 text-slate-600">{{ r.request_type }}</td>
                <td class="px-4 py-3 text-slate-600">{{ r.description }}</td>
                <td class="px-4 py-3 text-slate-500">{{ formatDate(r.created_at) }}</td>
                <td class="px-4 py-3">
                  <span :class="[
                    'px-2 py-1 rounded-full text-xs',
                    r.status === 'pending' ? 'bg-amber-100 text-amber-700' :
                    r.status === 'approved' ? 'bg-green-100 text-green-700' :
                    'bg-red-100 text-red-700'
                  ]">{{ statusLabel(r.status) }}</span>
                </td>
                <td class="px-4 py-3">
                  <button v-if="r.status === 'pending'" @click="reviewRequest(r, 'approved')"
                    class="text-green-600 hover:underline text-xs">通过</button>
                  <button v-if="r.status === 'pending'" @click="reviewRequest(r, 'rejected')"
                    class="ml-2 text-red-600 hover:underline text-xs">驳回</button>
                </td>
              </tr>
              <tr v-if="filteredReviews.length === 0">
                <td colspan="7" class="text-center py-8 text-slate-400">暂无编辑请求</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Activities Tab -->
      <div v-if="activeTab === 'activities'">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
          <div class="p-4 border-b border-slate-200">
            <span class="text-sm text-slate-500">共 {{ activities.length }} 个活动</span>
            <button @click="activeTab = 'dashboard'" class="ml-3 text-xs text-amber-600 hover:underline">← 返回仪表盘</button>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
            <div v-for="a in activities" :key="a.id"
              class="border border-slate-200 rounded-xl p-4 hover:shadow-md transition">
              <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-amber-600">event</span>
                <div class="flex-1">
                  <div class="font-semibold text-slate-900">{{ a.title }}</div>
                  <div class="text-xs text-slate-500 mt-1">{{ formatDate(a.activity_date) }}</div>
                  <p class="text-sm text-slate-600 mt-2">{{ a.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Casket Detail Modal -->
      <div v-if="selectedCasket" @click.self="selectedCasket = null"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6">
          <div class="flex items-start justify-between mb-4">
            <div>
              <h2 class="text-xl font-bold text-slate-900">牌位详情</h2>
              <p class="text-sm text-slate-500 font-mono">{{ selectedCasket.casket_code }}</p>
            </div>
            <button @click="selectedCasket = null" class="text-slate-400 hover:text-slate-600">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-slate-500">位置:</span> {{ selectedCasket.hall_name }} {{ selectedCasket.floor_no }}层-{{ selectedCasket.row_no }}排-{{ selectedCasket.position_no }}</div>
            <div><span class="text-slate-500">类型:</span> {{ selectedCasket.casket_type }}</div>
            <div><span class="text-slate-500">供奉逝者:</span> {{ selectedCasket.ancestor_name }}</div>
            <div><span class="text-slate-500">状态:</span> {{ selectedCasket.status === 'occupied' ? '已供奉' : '空闲' }}</div>
            <div><span class="text-slate-500">联系人:</span> {{ selectedCasket.family_contact }}</div>
            <div><span class="text-slate-500">联系电话:</span> {{ selectedCasket.family_phone }}</div>
            <div><span class="text-slate-500">供奉日期:</span> {{ formatDate(selectedCasket.installed_at) }}</div>
            <div><span class="text-slate-500">下次续费:</span> {{ selectedCasket.next_renewal_date ? formatDate(selectedCasket.next_renewal_date) : '-' }}</div>
            <div><span class="text-slate-500">管理费:</span> ${{ selectedCasket.management_fee }}/年</div>
            <div><span class="text-slate-500">合同号:</span> {{ selectedCasket.contract_no }}</div>
          </div>
          <div v-if="selectedCasket.ancestor_id" class="mt-4 p-4 bg-slate-50 rounded-lg">
            <div class="text-xs text-slate-500 mb-2">逝者信息</div>
            <div class="text-sm">
              <div>{{ ancestorMap[selectedCasket.ancestor_id]?.name }} · {{ ancestorMap[selectedCasket.ancestor_id]?.gender === 'male' ? '男' : '女' }}</div>
              <div class="text-xs text-slate-500 mt-1">
                {{ formatDate(ancestorMap[selectedCasket.ancestor_id]?.birth_date).substring(0, 10) }} -
                {{ formatDate(ancestorMap[selectedCasket.ancestor_id]?.death_date).substring(0, 10) }}
              </div>
              <div v-if="ancestorMap[selectedCasket.ancestor_id]?.public_epitaph" class="mt-2 text-slate-700 italic">
                "{{ ancestorMap[selectedCasket.ancestor_id].public_epitaph }}"
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Toast -->
      <div v-if="toast" class="fixed bottom-4 right-4 bg-slate-900 text-white px-4 py-2 rounded-lg shadow-lg z-50">
        {{ toast }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const activeTab = ref('dashboard')
const lastRefresh = ref('')

const stats = ref({})
const caskets = ref([])
const ancestors = ref([])
const members = ref([])
const activities = ref([])
const editRequests = ref([])

const casketSearch = ref('')
const casketFilter = ref('')
const ancestorSearch = ref('')
const memberSearch = ref('')
const memberFilter = ref('')
const reviewFilter = ref('pending')

const selectedCasket = ref(null)
const toast = ref('')

const token = () => localStorage.getItem('gdq_token') || ''

const tabs = computed(() => [
  { key: 'dashboard', label: '仪表盘', icon: 'dashboard' },
  { key: 'caskets', label: '牌位管理', icon: 'inventory_2' },
  { key: 'ancestors', label: '逝者档案', icon: 'person' },
  { key: 'members', label: '信众管理', icon: 'groups' },
  { key: 'review', label: '编辑审核', icon: 'rule', badge: stats.value.edit_pending || 0 },
  { key: 'activities', label: '活动管理', icon: 'event' }
])

const casketMap = computed(() => {
  const m = {}
  for (const c of caskets.value) {
    if (c.ancestor_id) m[c.ancestor_id] = c.casket_code
  }
  return m
})

const ancestorMap = computed(() => {
  const m = {}
  for (const a of ancestors.value) m[a.id] = a
  return m
})

const filteredCaskets = computed(() => {
  return caskets.value.filter(c => {
    if (casketFilter.value && c.status !== casketFilter.value) return false
    if (casketSearch.value) {
      const s = casketSearch.value.toLowerCase()
      if (!c.casket_code.toLowerCase().includes(s) &&
          !(c.family_contact || '').toLowerCase().includes(s)) return false
    }
    return true
  })
})

const filteredAncestors = computed(() => {
  if (!ancestorSearch.value) return ancestors.value
  const s = ancestorSearch.value.toLowerCase()
  return ancestors.value.filter(a => (a.name || '').toLowerCase().includes(s))
})

const filteredMembers = computed(() => {
  return members.value.filter(m => {
    if (memberFilter.value !== '' && String(m.temple_level) !== memberFilter.value) return false
    if (memberSearch.value) {
      const s = memberSearch.value.toLowerCase()
      if (!m.name.toLowerCase().includes(s) && !m.phone.includes(s)) return false
    }
    return true
  })
})

const filteredReviews = computed(() => {
  if (!reviewFilter.value) return editRequests.value
  return editRequests.value.filter(r => r.status === reviewFilter.value)
})

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function statusLabel(s) {
  return { pending: '待审核', approved: '已通过', rejected: '已驳回' }[s] || s
}

async function api(path, opts = {}) {
  const res = await fetch(`/api/temple/admin${path}`, {
    ...opts,
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token()}`, ...(opts.headers || {}) }
  })
  const data = await res.json().catch(() => ({}))
  if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`)
  return data
}

async function loadAll() {
  try {
    const [s, c, a, m, er, act] = await Promise.all([
      api('/stats'),
      api('/caskets'),
      api('/ancestors'),
      api('/members'),
      api('/edit-requests'),
      api('/activities')
    ])
    stats.value = s.stats || {}
    caskets.value = c.list || []
    ancestors.value = a.list || []
    members.value = m.list || []
    editRequests.value = er.list || []
    activities.value = act.list || []
    lastRefresh.value = new Date().toLocaleTimeString('zh-CN')
  } catch (e) {
    showToast('加载失败: ' + e.message)
  }
}

async function toggleSenior(m) {
  const newLevel = m.temple_level === 1 ? 0 : 1
  if (!confirm(`${newLevel === 1 ? '升' : '降'} ${m.name} 为${newLevel === 1 ? '资深信徒' : '普通用户'}?`)) return
  try {
    await api(`/members/${m.id}/level`, { method: 'PATCH', body: JSON.stringify({ temple_level: newLevel }) })
    m.temple_level = newLevel
    showToast('操作成功')
    loadAll()
  } catch (e) {
    showToast('操作失败: ' + e.message)
  }
}

async function reviewRequest(r, status) {
  if (!confirm(`${status === 'approved' ? '通过' : '驳回'} 该编辑请求?`)) return
  try {
    await api(`/edit-requests/${r.id}/review`, { method: 'PATCH', body: JSON.stringify({ status }) })
    r.status = status
    showToast('已' + (status === 'approved' ? '通过' : '驳回'))
    loadAll()
  } catch (e) {
    showToast('操作失败: ' + e.message)
  }
}

function openCasketDetail(c) {
  selectedCasket.value = c
}

async function generateQRCode(c) {
  showToast('二维码功能待开发 (Step 7)')
  // TODO: POST /api/temple/admin/caskets/:id/qrcode
}

let toastTimer
function showToast(msg) {
  toast.value = msg
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => toast.value = '', 3000)
}

onMounted(() => {
  loadAll()
})
</script>