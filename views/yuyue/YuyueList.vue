<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage, ElMessageBox } from 'element-plus'

const { t } = useI18n()
const router = useRouter()

// ─── Tabs ────────────────────────────────────────────────────────────────────────
const tabs = [
  { key: 'all', label: '全部' },
  { key: 'pending', label: '待确认' },
  { key: 'confirmed', label: '已确认' },
  { key: 'completed', label: '已完成' },
  { key: 'cancelled', label: '已取消' },
  { key: 'no_show', label: '未到店' },
]
const activeTab = ref('all')

// ─── Search ─────────────────────────────────────────────────────────────────────
const searchKeyword = ref('')
const dateRange = ref([])

// ─── Pagination ────────────────────────────────────────────────────────────────
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)

// ─── Table data ────────────────────────────────────────────────────────────────
const yuyueList = ref([])
const loading = ref(false)

// ─── Dialog ─────────────────────────────────────────────────────────────────────
const dialogVisible = ref(false)
const dialogTitle = ref('新增预约')
const isEdit = ref(false)
const currentId = ref(null)
const formData = ref({
  member_name: '',
  member_phone: '',
  service_type: '',
  service_item: '',
  store_name: '',
  staff_name: '',
  yuyue_date: '',
  yuyue_time: '',
  duration: 60,
  remark: ''
})

const statusTypeMap = {
  pending: 'warning',
  confirmed: 'primary',
  completed: 'success',
  cancelled: 'info',
  no_show: 'danger',
}

const statusLabelMap = {
  pending: '待确认',
  confirmed: '已确认',
  completed: '已完成',
  cancelled: '已取消',
  no_show: '未到店',
}

function getStatusType(status) {
  return statusTypeMap[status] || 'info'
}

function getStatusLabel(status) {
  return statusLabelMap[status] || status
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}

function formatTime(t) {
  if (!t) return '-'
  return t.substring(0, 5)
}

// ─── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchYuyue() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      size: pageSize.value,
    }
    if (activeTab.value !== 'all') params.status = activeTab.value
    if (searchKeyword.value) params.keyword = searchKeyword.value
    if (dateRange.value?.length === 2) {
      params.date_start = dateRange.value[0]
      params.date_end = dateRange.value[1]
    }
    const res = await api.get('/yuyue', { params })
    if (res.code === 0) {
      yuyueList.value = res.data.list || res.data
      total.value = res.data.total ?? yuyueList.value.length
    }
  } catch (e) {
    ElMessage.error(e.message || '获取预约列表失败')
  } finally {
    loading.value = false
  }
}

watch([activeTab, searchKeyword, dateRange], () => {
  currentPage.value = 1
  fetchYuyue()
}, { deep: true })
watch(currentPage, fetchYuyue)

onMounted(() => {
  fetchYuyue()
})

// ─── Actions ───────────────────────────────────────────────────────────────────
function goDetail(id) {
  router.push(`/yuyue/${id}`)
}

async function confirmYuyue(row) {
  if (!confirm(`确定确认预约 ${row.yuyue_no}？`)) return
  try {
    const res = await api.put(`/yuyue/${row.id}/status`, { action: 'confirm' })
    if (res.code === 0) {
      ElMessage.success('预约已确认')
      fetchYuyue()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function completeYuyue(row) {
  if (!confirm(`确定完成预约 ${row.yuyue_no}？`)) return
  try {
    const res = await api.put(`/yuyue/${row.id}/status`, { action: 'complete' })
    if (res.code === 0) {
      ElMessage.success('预约已完成')
      fetchYuyue()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function cancelYuyue(row) {
  if (!confirm(`确定取消预约 ${row.yuyue_no}？`)) return
  try {
    const res = await api.put(`/yuyue/${row.id}/status`, { action: 'cancel' })
    if (res.code === 0) {
      ElMessage.success('预约已取消')
      fetchYuyue()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function noShowYuyue(row) {
  if (!confirm(`确定标记 ${row.yuyue_no} 为未到店？`)) return
  try {
    const res = await api.put(`/yuyue/${row.id}/status`, { action: 'no_show' })
    if (res.code === 0) {
      ElMessage.success('已标记为未到店')
      fetchYuyue()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function deleteYuyue(row) {
  if (!confirm(`确定删除预约 ${row.yuyue_no}？此操作不可恢复。`)) return
  try {
    const res = await api.delete(`/yuyue/${row.id}`)
    if (res.code === 0) {
      ElMessage.success('预约已删除')
      fetchYuyue()
    } else {
      ElMessage.error(res.message || '删除失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '删除失败')
  }
}

// ─── Dialog ───────────────────────────────────────────────────────────────────
function openNewDialog() {
  isEdit.value = false
  dialogTitle.value = '新增预约'
  currentId.value = null
  formData.value = {
    member_name: '',
    member_phone: '',
    service_type: '',
    service_item: '',
    store_name: '',
    staff_name: '',
    yuyue_date: '',
    yuyue_time: '',
    duration: 60,
    remark: ''
  }
  dialogVisible.value = true
}

function openEditDialog(row) {
  isEdit.value = true
  dialogTitle.value = '编辑预约'
  currentId.value = row.id
  formData.value = {
    member_name: row.member_name || '',
    member_phone: row.member_phone || '',
    service_type: row.service_type || '',
    service_item: row.service_item || '',
    store_name: row.store_name || '',
    staff_name: row.staff_name || '',
    yuyue_date: row.yuyue_date || '',
    yuyue_time: row.yuyue_time ? row.yuyue_time.substring(0, 5) : '',
    duration: row.duration || 60,
    remark: row.remark || ''
  }
  dialogVisible.value = true
}

async function submitForm() {
  if (!formData.value.yuyue_date || !formData.value.yuyue_time) {
    ElMessage.warning('请填写预约日期和时间')
    return
  }
  try {
    let res
    if (isEdit.value) {
      res = await api.put(`/yuyue/${currentId.value}`, formData.value)
    } else {
      res = await api.post('/yuyue', formData.value)
    }
    if (res.code === 0) {
      ElMessage.success(isEdit.value ? '预约已更新' : '预约已创建')
      dialogVisible.value = false
      fetchYuyue()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

// ─── Status helpers ─────────────────────────────────────────────────────────────
function canShowConfirm(status) { return status === 'pending' }
function canShowComplete(status) { return status === 'confirmed' }
function canShowCancel(status) { return status === 'pending' || status === 'confirmed' }
function canShowNoShow(status) { return status === 'confirmed' }
function canShowEdit(status) { return status === 'pending' }
function canShowDelete(status) { return status === 'pending' }
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="预约管理" subtitle="预约服务列表" />

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
      <div class="flex overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="px-6 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
          :class="activeTab === tab.key
            ? 'border-blue-600 text-blue-600 bg-blue-50'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Search bar -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="flex flex-wrap gap-4 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">关键词搜索</label>
          <el-input
            v-model="searchKeyword"
            placeholder="预约号 / 姓名 / 电话 / 员工"
            clearable
            class="!w-64"
          />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">日期范围</label>
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            class="!w-72"
          />
        </div>
        <div class="ml-auto">
          <el-button type="primary" @click="openNewDialog">新增预约</el-button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table
        v-loading="loading"
        :data="yuyueList"
        stripe
        class="w-full"
        empty-text="暂无预约数据"
      >
        <el-table-column label="预约号" prop="yuyue_no" min-width="160" />
        <el-table-column label="预约信息" min-width="200">
          <template #default="{ row }">
            <div class="text-sm">
              <div>{{ row.service_item || row.service_type || '-' }}</div>
              <div class="text-gray-400 text-xs">{{ row.member_name }} {{ row.member_phone }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="门店/员工" min-width="140">
          <template #default="{ row }">
            <div class="text-sm">
              <div>{{ row.store_name || '-' }}</div>
              <div class="text-gray-400 text-xs">{{ row.staff_name || '' }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="预约时间" width="160">
          <template #default="{ row }">
            <div class="text-sm">
              <div>{{ row.yuyue_date }}</div>
              <div class="text-gray-400 text-xs">{{ formatTime(row.yuyue_time) }} ({{ row.duration }}分钟)</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">
            <span class="text-sm text-gray-500">{{ formatDate(row.created_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <el-button size="small" link type="primary" @click="goDetail(row.id)">查看</el-button>
              <el-button v-if="canShowEdit(row.status)" size="small" link type="primary" @click="openEditDialog(row)">编辑</el-button>
              <el-button v-if="canShowConfirm(row.status)" size="small" link type="success" @click="confirmYuyue(row)">确认</el-button>
              <el-button v-if="canShowComplete(row.status)" size="small" link type="success" @click="completeYuyue(row)">完成</el-button>
              <el-button v-if="canShowNoShow(row.status)" size="small" link type="warning" @click="noShowYuyue(row)">未到店</el-button>
              <el-button v-if="canShowCancel(row.status)" size="small" link type="warning" @click="cancelYuyue(row)">取消</el-button>
              <el-button v-if="canShowDelete(row.status)" size="small" link type="danger" @click="deleteYuyue(row)">删除</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <!-- Pagination -->
      <div class="flex justify-end p-4 border-t">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next"
          background
        />
      </div>
    </div>

    <!-- Dialog -->
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" :close-on-click-modal="false">
      <el-form :model="formData" label-width="100px">
        <el-form-item label="预约人姓名">
          <el-input v-model="formData.member_name" placeholder="请输入预约人姓名" />
        </el-form-item>
        <el-form-item label="预约人电话">
          <el-input v-model="formData.member_phone" placeholder="请输入预约人电话" />
        </el-form-item>
        <el-form-item label="服务类型">
          <el-input v-model="formData.service_type" placeholder="如：美容、美发、按摩等" />
        </el-form-item>
        <el-form-item label="预约项目">
          <el-input v-model="formData.service_item" placeholder="具体服务项目" />
        </el-form-item>
        <el-form-item label="门店名称">
          <el-input v-model="formData.store_name" placeholder="门店名称" />
        </el-form-item>
        <el-form-item label="服务员工">
          <el-input v-model="formData.staff_name" placeholder="员工姓名" />
        </el-form-item>
        <el-form-item label="预约日期" required>
          <el-date-picker
            v-model="formData.yuyue_date"
            type="date"
            placeholder="选择日期"
            value-format="YYYY-MM-DD"
            class="!w-full"
          />
        </el-form-item>
        <el-form-item label="预约时间" required>
          <el-time-picker
            v-model="formData.yuyue_time"
            placeholder="选择时间"
            format="HH:mm"
            value-format="HH:mm:ss"
            class="!w-full"
          />
        </el-form-item>
        <el-form-item label="预计时长">
          <el-input-number v-model="formData.duration" :min="15" :max="480" :step="15" /> 分钟
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="formData.remark" type="textarea" :rows="3" placeholder="备注信息" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>