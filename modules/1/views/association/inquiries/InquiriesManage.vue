<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">在线咨询</h1>
        <p class="text-sm text-gray-500 mt-1">公众咨询管理 / 回复 / 状态跟踪</p>
      </div>
    </div>

    <!-- 统计卡片 -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-4">
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-gray-500 mb-1">总咨询</div>
        <div class="text-2xl font-bold text-gray-800">{{ stats.total || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-blue-600 mb-1">📩 新留言</div>
        <div class="text-2xl font-bold text-blue-600">{{ stats.new_count || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-yellow-600 mb-1">👀 已读</div>
        <div class="text-2xl font-bold text-yellow-600">{{ stats.read_count || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-green-600 mb-1">✅ 已回复</div>
        <div class="text-2xl font-bold text-green-600">{{ stats.replied_count || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-gray-500 mb-1">🚪 已关闭</div>
        <div class="text-2xl font-bold text-gray-500">{{ stats.closed_count || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-orange-600 mb-1">⭐ 优先</div>
        <div class="text-2xl font-bold text-orange-600">{{ stats.priority_count || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-purple-600 mb-1">近 7 天</div>
        <div class="text-2xl font-bold text-purple-600">{{ stats.last_7_days || 0 }}</div>
      </div>
    </div>

    <!-- 筛选 -->
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3 flex-wrap">
        <input v-model="filter.keyword" placeholder="搜索姓名/电话/邮箱/主题/内容" class="px-3 py-2 border rounded-lg flex-1 min-w-[200px]" @keyup.enter="load" />
        <select v-model="filter.status" class="px-3 py-2 border rounded-lg">
          <option value="">全部状态</option>
          <option value="new">📩 新留言</option>
          <option value="read">👀 已读</option>
          <option value="replied">✅ 已回复</option>
          <option value="closed">🚪 已关闭</option>
          <option value="spam">🚫 垃圾</option>
        </select>
        <select v-model="filter.priority" class="px-3 py-2 border rounded-lg">
          <option value="">全部优先级</option>
          <option value="1">⭐ 优先</option>
          <option value="0">普通</option>
        </select>
        <input v-model="filter.date_from" type="date" class="px-3 py-2 border rounded-lg" />
        <input v-model="filter.date_to" type="date" class="px-3 py-2 border rounded-lg" />
        <button @click="load" class="px-4 py-2 bg-primary text-white rounded-lg">搜索</button>
        <button @click="resetFilter" class="px-4 py-2 border rounded-lg">重置</button>
      </div>
    </div>

    <!-- 列表 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">姓名 / 联系</th>
            <th class="px-4 py-3 text-left">主题</th>
            <th class="px-4 py-3 text-left">内容预览</th>
            <th class="px-4 py-3 text-left">状态</th>
            <th class="px-4 py-3 text-left">优先级</th>
            <th class="px-4 py-3 text-left">提交时间</th>
            <th class="px-4 py-3 text-left">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in list" :key="i.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800">
                <span v-if="i.priority" class="text-orange-500 mr-1">⭐</span>
                {{ i.name }}
              </div>
              <div class="text-xs text-gray-500 mt-1">
                <span v-if="i.phone">📞 {{ i.phone }}</span>
                <span v-if="i.email" class="ml-2">✉ {{ i.email }}</span>
              </div>
              <div class="text-xs text-gray-400 mt-1">IP: {{ i.ip_address || '-' }}</div>
            </td>
            <td class="px-4 py-3 text-sm">{{ i.subject || '(无主题)' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 max-w-xs">
              <div class="line-clamp-2">{{ i.message }}</div>
            </td>
            <td class="px-4 py-3">
              <span :class="['px-2 py-0.5 rounded text-xs', statusClass(i.status)]">{{ statusLabel(i.status) }}</span>
              <div v-if="i.replied_at" class="text-xs text-green-600 mt-1">✓ {{ formatTime(i.replied_at) }}</div>
            </td>
            <td class="px-4 py-3">
              <button @click="togglePriority(i)" class="text-sm hover:underline" :class="i.priority ? 'text-orange-500' : 'text-gray-400'">
                {{ i.priority ? '⭐ 优先' : '普通' }}
              </button>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ formatTime(i.created_at) }}</td>
            <td class="px-4 py-3">
              <button @click="openDetail(i)" class="text-primary text-sm hover:underline mr-2">查看/回复</button>
              <button @click="del(i.id)" class="text-red-500 text-sm hover:underline">删除</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">暂无咨询</div>

      <div v-if="total > pageSize" class="p-4 flex justify-end">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          layout="prev, pager, next"
          @current-change="load"
        />
      </div>
    </div>

    <!-- 详情/回复弹窗 -->
    <el-dialog v-model="dialogVisible" :title="`咨询详情 #${detail?.id || ''}`" width="800px" :close-on-click-modal="false">
      <div v-if="detail" class="space-y-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-500">姓名:</span> <span class="font-medium">{{ detail.name }}</span></div>
          <div><span class="text-gray-500">状态:</span> <span :class="['px-2 py-0.5 rounded text-xs', statusClass(detail.status)]">{{ statusLabel(detail.status) }}</span></div>
          <div><span class="text-gray-500">电话:</span> {{ detail.phone || '-' }}</div>
          <div><span class="text-gray-500">邮箱:</span> {{ detail.email || '-' }}</div>
          <div class="col-span-2"><span class="text-gray-500">主题:</span> {{ detail.subject || '(无主题)' }}</div>
          <div><span class="text-gray-500">IP:</span> <code class="text-xs">{{ detail.ip_address || '-' }}</code></div>
          <div><span class="text-gray-500">提交时间:</span> {{ formatTime(detail.created_at) }}</div>
          <div class="col-span-2">
            <div class="text-gray-500 mb-1">咨询内容:</div>
            <div class="bg-gray-50 p-3 rounded border whitespace-pre-wrap">{{ detail.message }}</div>
          </div>
          <div v-if="detail.reply_message" class="col-span-2">
            <div class="text-gray-500 mb-1">已回复 ({{ formatTime(detail.replied_at) }}):</div>
            <div class="bg-green-50 p-3 rounded border border-green-200 whitespace-pre-wrap">{{ detail.reply_message }}</div>
          </div>
        </div>

        <el-divider />

        <div>
          <div class="text-sm font-medium text-gray-700 mb-2">回复咨询</div>
          <el-input v-model="replyMessage" type="textarea" :rows="5" placeholder="输入回复内容..." />
        </div>

        <div>
          <div class="text-sm font-medium text-gray-700 mb-2">状态变更</div>
          <div class="flex gap-2 flex-wrap">
            <button @click="changeStatus('read')" :disabled="detail.status === 'read'" class="px-3 py-1 border rounded text-sm disabled:opacity-30">标为已读</button>
            <button @click="changeStatus('replied')" :disabled="!!detail.reply_message" class="px-3 py-1 bg-green-500 text-white rounded text-sm disabled:opacity-30">标为已回复</button>
            <button @click="changeStatus('closed')" :disabled="detail.status === 'closed'" class="px-3 py-1 border rounded text-sm disabled:opacity-30">关闭</button>
            <button @click="changeStatus('spam')" class="px-3 py-1 border border-red-300 text-red-500 rounded text-sm">标记垃圾</button>
          </div>
        </div>

        <div>
          <div class="text-sm font-medium text-gray-700 mb-2">内部备注</div>
          <el-input v-model="notes" type="textarea" :rows="2" placeholder="仅管理员可见..." />
        </div>
      </div>
      <template #footer>
        <button @click="dialogVisible = false" class="px-4 py-2 border rounded-lg">关闭</button>
        <button @click="saveDetail" class="px-4 py-2 bg-primary text-white rounded-lg ml-2">保存</button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '@/services/api.js'

const list = ref([])
const stats = ref(null)
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const filter = reactive({ keyword: '', status: '', priority: '', date_from: '', date_to: '' })
const dialogVisible = ref(false)
const detail = ref(null)
const replyMessage = ref('')
const notes = ref('')

async function load() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      size: pageSize.value,
      server_profile_id: 7,
      ...(filter.keyword && { keyword: filter.keyword }),
      ...(filter.status && { status: filter.status }),
      ...(filter.priority !== '' && filter.priority !== null && { priority: filter.priority }),
      ...(filter.date_from && { date_from: filter.date_from + ' 00:00:00' }),
      ...(filter.date_to && { date_to: filter.date_to + ' 23:59:59' })
    }
    const r = await api.get('/association/inquiries/admin', { params })
    if (r.code === 0) {
      list.value = r.data || []
      total.value = r.total || 0
    } else {
      ElMessage.error(r.message)
    }
  } catch (e) {
    ElMessage.error(e.message)
  } finally {
    loading.value = false
  }
}

async function loadStats() {
  try {
    const r = await api.get('/association/inquiries/admin/stats', { params: { server_profile_id: 7 } })
    if (r.code === 0) stats.value = r.data
  } catch (e) {}
}

function resetFilter() {
  filter.keyword = ''
  filter.status = ''
  filter.priority = ''
  filter.date_from = ''
  filter.date_to = ''
  currentPage.value = 1
  load()
}

async function openDetail(i) {
  try {
    const r = await api.get(`/association/inquiries/${i.id}`)
    if (r.code === 0) {
      detail.value = r.data
      replyMessage.value = r.data.reply_message || ''
      notes.value = r.data.notes || ''
      dialogVisible.value = true
      // 自动标记已读 (如果是 new)
      if (r.data.status === 'new') {
        await changeStatus('read', true)
      }
    }
  } catch (e) {
    ElMessage.error(e.message)
  }
}

async function changeStatus(newStatus, silent = false) {
  if (!detail.value) return
  try {
    const r = await api.put(`/association/inquiries/${detail.value.id}`, { status: newStatus })
    if (r.code === 0) {
      detail.value = r.data
      if (!silent) ElMessage.success('状态已更新')
      loadStats()
      load()
    } else {
      ElMessage.error(r.message)
    }
  } catch (e) {
    ElMessage.error(e.message)
  }
}

async function togglePriority(i) {
  try {
    const r = await api.put(`/association/inquiries/${i.id}`, { priority: !i.priority ? 1 : 0 })
    if (r.code === 0) {
      i.priority = r.data.priority
      ElMessage.success('已更新')
      loadStats()
    } else {
      ElMessage.error(r.message)
    }
  } catch (e) {
    ElMessage.error(e.message)
  }
}

async function saveDetail() {
  if (!detail.value) return
  try {
    const payload = { notes: notes.value }
    if (replyMessage.value && replyMessage.value !== detail.value.reply_message) {
      payload.reply_message = replyMessage.value
    }
    const r = await api.put(`/association/inquiries/${detail.value.id}`, payload)
    if (r.code === 0) {
      detail.value = r.data
      ElMessage.success(r.message || '已保存')
      loadStats()
      load()
    } else {
      ElMessage.error(r.message)
    }
  } catch (e) {
    ElMessage.error(e.message)
  }
}

async function del(id) {
  try {
    await ElMessageBox.confirm('确认删除该咨询？删除后无法恢复。', '警告', { type: 'warning' })
  } catch { return }
  try {
    const r = await api.delete(`/association/inquiries/${id}`)
    if (r.code === 0) {
      ElMessage.success('已删除')
      loadStats()
      load()
    } else {
      ElMessage.error(r.message)
    }
  } catch (e) {
    ElMessage.error(e.message)
  }
}

function statusClass(s) {
  return {
    new: 'bg-blue-100 text-blue-700',
    read: 'bg-yellow-100 text-yellow-700',
    replied: 'bg-green-100 text-green-700',
    closed: 'bg-gray-100 text-gray-600',
    spam: 'bg-red-100 text-red-600'
  }[s] || 'bg-gray-100 text-gray-600'
}
function statusLabel(s) {
  return { new: '📩 新', read: '👀 已读', replied: '✅ 已回复', closed: '🚪 关闭', spam: '🚫 垃圾' }[s] || s
}
function formatTime(t) {
  if (!t) return '-'
  return new Date(t).toLocaleString('zh-CN', { hour12: false })
}

onMounted(() => { loadStats(); load() })
watch(currentPage, () => load())
</script>