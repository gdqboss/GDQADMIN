<!--
  AttendanceManageV2 - gdqadmin 出勤管理 V2 (2026-09-02 立)
  使用 shared/components/attendance/AttendancePanel
-->
<template>
  <div class="space-y-6">
    <PageHeader :title="$t('attendance.title')" :subtitle="$t('attendance.subtitle')">
      <button @click="exportRecords" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg text-sm hover:bg-slate-50">
        导出
      </button>
    </PageHeader>

    <!-- 自己打卡 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-1">
        <AttendancePanel :api="api" adapter="gdqadmin" :user-id="userStore.user?.id" />
      </div>

      <!-- 统计 -->
      <div class="lg:col-span-2 grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-6 shadow-sm">
          <div class="text-sm text-slate-500">本月出勤天数</div>
          <div class="text-3xl font-bold text-slate-900 mt-2">{{ stats.total_days || 0 }}</div>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm">
          <div class="text-sm text-slate-500">迟到次数</div>
          <div class="text-3xl font-bold text-amber-600 mt-2">{{ stats.late_count || 0 }}</div>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm">
          <div class="text-sm text-slate-500">本月工时</div>
          <div class="text-3xl font-bold text-green-600 mt-2">{{ stats.total_hours || 0 }}h</div>
        </div>
      </div>
    </div>

    <!-- 记录列表 -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold">出勤记录</h3>
        <div class="flex gap-2">
          <select v-model="filter.user_id" class="px-3 py-1 border border-slate-200 rounded-lg text-sm">
            <option :value="null">全部人员</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
          <input type="date" v-model="filter.date_from" class="px-3 py-1 border border-slate-200 rounded-lg text-sm" />
          <input type="date" v-model="filter.date_to" class="px-3 py-1 border border-slate-200 rounded-lg text-sm" />
        </div>
      </div>

      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-slate-500 border-b border-slate-100">
            <th class="py-2 px-3">人员</th>
            <th class="py-2 px-3">日期</th>
            <th class="py-2 px-3">上班</th>
            <th class="py-2 px-3">下班</th>
            <th class="py-2 px-3">工时</th>
            <th class="py-2 px-3">状态</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in records" :key="r.id" class="border-b border-slate-50 hover:bg-slate-50">
            <td class="py-2 px-3">{{ getUserName(r.user_id) }}</td>
            <td class="py-2 px-3">{{ formatDate(r.date) }}</td>
            <td class="py-2 px-3">{{ formatTime(r.check_in_at) }}</td>
            <td class="py-2 px-3">{{ formatTime(r.check_out_at) }}</td>
            <td class="py-2 px-3">{{ calcHours(r) }}h</td>
            <td class="py-2 px-3">
              <span :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <Pagination
        v-if="total > pageSize"
        :total="total"
        :page="currentPage"
        :page-size="pageSize"
        @change="onPageChange"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user.js'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import AttendancePanel from '../../shared/components/attendance/AttendancePanel.vue'
import { useAttendance } from '../../shared/composables/useAttendance.js'
import api from '../../services/api.js'

const { t } = useI18n()
const userStore = useUserStore()

const {
  records, stats, loading,
  fetchRecords, fetchStats, calcWorkHours, statusClass, statusLabel
} = useAttendance(api)

const users = ref([])
const currentPage = ref(1)
const pageSize = 20
const total = ref(0)

const filter = reactive({
  user_id: null,
  date_from: '',
  date_to: ''
})

onMounted(async () => {
  await fetchAll()
  const u = await api.get('/users', { params: { pageSize: 1000 } })
  if (u.code === 0) users.value = u.data?.list || []
})

async function fetchAll() {
  loading.value = true
  try {
    const res = await api.get('/oa/attendance', {
      params: { page: currentPage.value, pageSize, ...filter }
    })
    if (res.code === 0) {
      records.value = res.data?.list || []
      total.value = res.data?.total || 0
    }
  } finally {
    loading.value = false
  }
  await fetchStats({ user_id: filter.user_id })
}

function onPageChange(p) {
  currentPage.value = p
  fetchAll()
}

function getUserName(id) {
  return users.value.find(u => u.id === id)?.name || `用户${id}`
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString('zh-CN').slice(0, 10) : '' }
function formatTime(d) { return d ? new Date(d).toLocaleTimeString('zh-CN', { hour12: false }).slice(0, 5) : '' }
function calcHours(r) { return calcWorkHours(r.check_in_at, r.check_out_at) }

function exportRecords() {
  // 触发后端导出 endpoint
  api.get('/oa/attendance/export', { params: filter, responseType: 'blob' })
    .then(res => {
      const url = URL.createObjectURL(new Blob([res.data]))
      const a = document.createElement('a')
      a.href = url
      a.download = `attendance-${Date.now()}.xlsx`
      a.click()
    })
}
</script>