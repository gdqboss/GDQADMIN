<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('association.inquiries.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('association.inquiries.subtitle') }}</p>
      </div>
    </div>

    <!-- 统计卡片 -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-4">
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-gray-500 mb-1">{{ $t('association.inquiries.total') }}</div>
        <div class="text-2xl font-bold text-gray-800">{{ stats.total || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-blue-600 mb-1">{{ $t("association.inquiries.newMsg") }}</div>
        <div class="text-2xl font-bold text-blue-600">{{ stats.new_count || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-yellow-600 mb-1">{{ $t("association.inquiries.read") }}</div>
        <div class="text-2xl font-bold text-yellow-600">{{ stats.read_count || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-green-600 mb-1">{{ $t("association.inquiries.replied") }}</div>
        <div class="text-2xl font-bold text-green-600">{{ stats.replied_count || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-gray-500 mb-1">{{ $t("association.inquiries.closed") }}</div>
        <div class="text-2xl font-bold text-gray-500">{{ stats.closed_count || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-orange-600 mb-1">{{ $t("association.inquiries.priority") }}</div>
        <div class="text-2xl font-bold text-orange-600">{{ stats.priority_count || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100">
        <div class="text-xs text-purple-600 mb-1">{{ $t('association.inquiries.last7Days') }}</div>
        <div class="text-2xl font-bold text-purple-600">{{ stats.last_7_days || 0 }}</div>
      </div>
    </div>

    <!-- 筛选 -->
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3 flex-wrap">
        <input v-model="filter.keyword" :placeholder="$t('association.inquiries.searchPlaceholder')" class="px-3 py-2 border rounded-lg flex-1 min-w-[200px]" @keyup.enter="load" />
        <select v-model="filter.status" class="px-3 py-2 border rounded-lg">
          <option value="">{{ $t('association.activities.allStatus') }}</option>
          <option value="new">{{ $t("association.inquiries.newMsg") }}</option>
          <option value="read">{{ $t("association.inquiries.read") }}</option>
          <option value="replied">{{ $t("association.inquiries.replied") }}</option>
          <option value="closed">{{ $t("association.inquiries.closed") }}</option>
          <option value="spam">{{ $t("association.inquiries.spam") }}</option>
        </select>
        <select v-model="filter.priority" class="px-3 py-2 border rounded-lg">
          <option value="">{{ $t('association.inquiries.allPriorities') }}</option>
          <option value="1">{{ $t("association.inquiries.priority") }}</option>
          <option value="0">{{ $t('association.common.ordinary') }}</option>
        </select>
        <input v-model="filter.date_from" type="date" class="px-3 py-2 border rounded-lg" />
        <input v-model="filter.date_to" type="date" class="px-3 py-2 border rounded-lg" />
        <button @click="load" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('association.common.search') }}</button>
        <button @click="resetFilter" class="px-4 py-2 border rounded-lg">{{ $t('association.inquiries.reset') }}</button>
      </div>
    </div>

    <!-- 列表 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">{{ $t("association.inquiries.nameContact") }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.inquiries.subject') }}</th>
            <th class="px-4 py-3 text-left">{{ $t("association.inquiries.preview") }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.status') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.inquiries.priorityLabel') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.inquiries.submitTime') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.operations') }}</th>
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
            <td class="px-4 py-3 text-sm">{{ i.subject || $t('association.inquiries.noSubject') }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 max-w-xs">
              <div class="line-clamp-2">{{ i.message }}</div>
            </td>
            <td class="px-4 py-3">
              <span :class="['px-2 py-0.5 rounded text-xs', statusClass(i.status)]">{{ statusLabel(i.status) }}</span>
              <div v-if="i.replied_at" class="text-xs text-green-600 mt-1">✓ {{ formatTime(i.replied_at) }}</div>
            </td>
            <td class="px-4 py-3">
              <button @click="togglePriority(i)" class="text-sm hover:underline" :class="i.priority ? 'text-orange-500' : 'text-gray-400'">
                {{ i.priority ? $t('association.inquiries.priority') : $t('association.common.ordinary') }}
              </button>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ formatTime(i.created_at) }}</td>
            <td class="px-4 py-3">
              <button @click="openDetail(i)" class="text-primary text-sm hover:underline mr-2">{{ $t("association.inquiries.viewReply") }}</button>
              <button @click="del(i.id)" class="text-red-500 text-sm hover:underline">{{ $t('association.common.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">{{ $t('association.inquiries.noData') }}</div>

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
    <el-dialog v-model="dialogVisible" :title="`${$t('association.inquiries.detailTitle')} #${detail?.id || ''}`" width="800px" :close-on-click-modal="false">
      <div v-if="detail" class="space-y-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-500">{{ $t("association.inquiries.nameColon") }}</span> <span class="font-medium">{{ detail.name }}</span></div>
          <div><span class="text-gray-500">{{ $t("association.inquiries.statusColon") }}</span> <span :class="['px-2 py-0.5 rounded text-xs', statusClass(detail.status)]">{{ statusLabel(detail.status) }}</span></div>
          <div><span class="text-gray-500">{{ $t("association.inquiries.phoneColon") }}</span> {{ detail.phone || '-' }}</div>
          <div><span class="text-gray-500">{{ $t("association.inquiries.emailColon") }}</span> {{ detail.email || '-' }}</div>
          <div class="col-span-2"><span class="text-gray-500">{{ $t("association.inquiries.subjectColon") }}</span> {{ detail.subject || $t('association.inquiries.noSubject') }}</div>
          <div><span class="text-gray-500">IP:</span> <code class="text-xs">{{ detail.ip_address || '-' }}</code></div>
          <div><span class="text-gray-500">{{ $t("association.inquiries.submitTimeColon") }}</span> {{ formatTime(detail.created_at) }}</div>
          <div class="col-span-2">
            <div class="text-gray-500 mb-1">{{ $t("association.inquiries.contentColon") }}</div>
            <div class="bg-gray-50 p-3 rounded border whitespace-pre-wrap">{{ detail.message }}</div>
          </div>
          <div v-if="detail.reply_message" class="col-span-2">
            <div class="text-gray-500 mb-1">已回复 ({{ formatTime(detail.replied_at) }}):</div>
            <div class="bg-green-50 p-3 rounded border border-green-200 whitespace-pre-wrap">{{ detail.reply_message }}</div>
          </div>
        </div>

        <el-divider />

        <div>
          <div class="text-sm font-medium text-gray-700 mb-2">{{ $t("association.inquiries.reply") }}</div>
          <el-input v-model="replyMessage" type="textarea" :rows="5" :placeholder="$t('association.inquiries.replyPlaceholder')" />
        </div>

        <div>
          <div class="text-sm font-medium text-gray-700 mb-2">{{ $t('association.inquiries.statusChange') }}</div>
          <div class="flex gap-2 flex-wrap">
            <button @click="changeStatus('read')" :disabled="detail.status === 'read'" class="px-3 py-1 border rounded text-sm disabled:opacity-30">{{ $t("association.inquiries.markRead") }}</button>
            <button @click="changeStatus('replied')" :disabled="!!detail.reply_message" class="px-3 py-1 bg-green-500 text-white rounded text-sm disabled:opacity-30">{{ $t("association.inquiries.markReplied") }}</button>
            <button @click="changeStatus('closed')" :disabled="detail.status === 'closed'" class="px-3 py-1 border rounded text-sm disabled:opacity-30">{{ $t('association.inquiries.close') }}</button>
            <button @click="changeStatus('spam')" class="px-3 py-1 border border-red-300 text-red-500 rounded text-sm">{{ $t('association.inquiries.markSpam') }}</button>
          </div>
        </div>

        <div>
          <div class="text-sm font-medium text-gray-700 mb-2">{{ $t("association.inquiries.internalNote") }}</div>
          <el-input v-model="notes" type="textarea" :rows="2" :placeholder="$t('association.inquiries.notePlaceholder')" />
        </div>
      </div>
      <template #footer>
        <button @click="dialogVisible = false" class="px-4 py-2 border rounded-lg">{{ $t('association.inquiries.close') }}</button>
        <button @click="saveDetail" class="px-4 py-2 bg-primary text-white rounded-lg ml-2">{{ $t('association.common.save') }}</button>
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
      if (!silent) ElMessage.success($t('association.common.statusUpdated'))
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
      ElMessage.success($t('association.common.updated'))
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
      ElMessage.success(r.message || $t('association.common.saved'))
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
    await ElMessageBox.confirm($t('association.inquiries.confirmDelete'), $t('association.common.warning'), { type: 'warning' })
  } catch { return }
  try {
    const r = await api.delete(`/association/inquiries/${id}`)
    if (r.code === 0) {
      ElMessage.success($t('association.common.deleted'))
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
  return { new: '📩 新', read: $t('association.inquiries.read'), replied: $t('association.inquiries.replied'), closed: '🚪 关闭', spam: $t('association.inquiries.spam') }[s] || s
}
function formatTime(t) {
  if (!t) return '-'
  return new Date(t).toLocaleString('zh-CN', { hour12: false })
}

onMounted(() => { loadStats(); load() })
watch(currentPage, () => load())
</script>