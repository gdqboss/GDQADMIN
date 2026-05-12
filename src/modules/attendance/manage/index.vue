<template>
  <div class="attendance-manage-container">
    <!-- Header -->
    <div class="page-header">
      <h2>考勤管理</h2>
      <div class="header-actions">
        <el-button type="primary" @click="handleClockIn">打卡</el-button>
      </div>
    </div>

    <!-- My Today Card -->
    <el-card class="today-card" v-if="myToday">
      <div class="today-info">
        <div class="today-date">{{ today }}</div>
        <div class="clock-buttons">
          <el-button 
            type="success" 
            size="large" 
            :disabled="!!myToday.clock_in"
            @click="handleClock('in')"
          >
            上班打卡 {{ myToday.clock_in || '' }}
          </el-button>
          <el-button 
            type="warning" 
            size="large" 
            :disabled="!myToday.clock_in || !!myToday.clock_out"
            @click="handleClock('out')"
          >
            下班打卡 {{ myToday.clock_out || '' }}
          </el-button>
        </div>
        <div class="status-badge" v-if="myToday.status !== 'normal'">
          <el-tag :type="myToday.status === 'late' ? 'warning' : 'danger'">
            {{ statusText(myToday.status) }}
          </el-tag>
        </div>
      </div>
    </el-card>

    <!-- Summary Stats -->
    <el-row :gutter="20" class="summary-row">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ summary.should_attend || 0 }}</div>
            <div class="stat-label">应打卡</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ summary.checked_in || 0 }}</div>
            <div class="stat-label">实打卡</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item warning">
            <div class="stat-value">{{ summary.late_count || 0 }}</div>
            <div class="stat-label">迟到</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item danger">
            <div class="stat-value">{{ summary.absent_count || 0 }}</div>
            <div class="stat-label">旷工</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Search Form -->
    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="日期">
          <el-date-picker
            v-model="searchForm.date"
            type="date"
            placeholder="选择日期"
            value-format="YYYY-MM-DD"
            @change="loadData"
          />
        </el-form-item>
        <el-form-item label="员工">
          <el-input v-model="searchForm.user_name" placeholder="员工姓名" clearable @change="loadData" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable @change="loadData">
            <el-option label="正常" value="normal" />
            <el-option label="迟到" value="late" />
            <el-option label="早退" value="early" />
            <el-option label="旷工" value="absent" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadData">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Data Table -->
    <el-card class="data-card">
      <el-table v-loading="loading" :data="list" stripe border>
        <el-table-column prop="date" label="日期" width="120" />
        <el-table-column prop="user_name" label="姓名" width="100" />
        <el-table-column prop="department" label="部门" width="120" />
        <el-table-column prop="clock_in" label="上班打卡" width="120">
          <template #default="{ row }">
            <span :class="row.status === 'late' ? 'text-warning' : ''">{{ row.clock_in || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="clock_out" label="下班打卡" width="120">
          <template #default="{ row }">
            <span :class="row.status === 'early' ? 'text-warning' : ''">{{ row.clock_out || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ statusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="location" label="打卡位置" min-width="150" show-overflow-tooltip />
        <el-table-column prop="is_auto_clock" label="打卡方式" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.is_auto_clock" type="info" size="small">自动</el-tag>
            <span v-else>手动</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleExplain(row)">说明</el-button>
            <el-button link type="danger" size="small" @click="handleApprove(row)" v-if="isAdmin">审批</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.size"
          :page-sizes="[10, 20, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next"
          @size-change="loadData"
          @current-change="loadData"
        />
      </div>
    </el-card>

    <!-- Explain Dialog -->
    <el-dialog v-model="explainDialogVisible" title="提交异常说明" width="500px">
      <el-form :model="explainForm" label-width="80px">
        <el-form-item label="异常原因">
          <el-input v-model="explainForm.reason" type="textarea" rows="3" placeholder="请输入异常说明" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="explainDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitExplain">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  getMyAttendanceToday, 
  getAttendanceTodaySummary, 
  getAttendanceList, 
  explainAttendance,
  approveAttendance,
  clockIn 
} from '@/api/oa'

const loading = ref(false)
const list = ref([])
const myToday = ref(null)
const summary = ref({})
const today = new Date().toLocaleDateString('zh-CN')

const searchForm = reactive({
  date: new Date().toISOString().slice(0, 10),
  user_name: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  size: 10,
  total: 0
})

const explainDialogVisible = ref(false)
const explainForm = reactive({ reason: '', id: null })
const explainLoading = ref(false)

const isAdmin = computed(() => {
  const user = JSON.parse(localStorage.getItem('caimeite_user') || '{}')
  return user.role === 'admin'
})

const statusText = (status) => {
  const map = { normal: '正常', late: '迟到', early: '早退', absent: '旷工' }
  return map[status] || '未知'
}

const getStatusType = (status) => {
  const map = { normal: 'success', late: 'warning', early: 'warning', absent: 'danger' }
  return map[status] || 'info'
}

const loadMyToday = async () => {
  try {
    const data = await getMyAttendanceToday()
    myToday.value = data
  } catch (e) {
    console.error(e)
  }
}

const loadSummary = async () => {
  try {
    const data = await getAttendanceTodaySummary()
    summary.value = data
  } catch (e) {
    console.error(e)
  }
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      ...searchForm,
      page: pagination.page,
      size: pagination.size
    }
    const data = await getAttendanceList(params)
    list.value = data.list || []
    pagination.total = data.total || 0
  } catch (e) {
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

const resetSearch = () => {
  searchForm.date = new Date().toISOString().slice(0, 10)
  searchForm.user_name = ''
  searchForm.status = ''
  pagination.page = 1
  loadData()
}

const handleClock = async (type) => {
  try {
    const data = await clockIn({ type })
    ElMessage.success(data.message || '打卡成功')
    loadMyToday()
    loadSummary()
    loadData()
  } catch (e) {
    ElMessage.error(e.message || '打卡失败')
  }
}

const handleClockIn = () => {
  handleClock('in')
}

const handleExplain = (row) => {
  explainForm.id = row.id
  explainForm.reason = ''
  explainDialogVisible.value = true
}

const submitExplain = async () => {
  if (!explainForm.reason.trim()) {
    ElMessage.warning('请输入异常说明')
    return
  }
  explainLoading.value = true
  try {
    await explainAttendance(explainForm.id, { reason: explainForm.reason })
    ElMessage.success('提交成功')
    explainDialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(e.message || '提交失败')
  } finally {
    explainLoading.value = false
  }
}

const handleApprove = async (row) => {
  try {
    await ElMessageBox.confirm('确认审批通过该考勤记录？', '提示', { type: 'warning' })
    await approveAttendance(row.id, { status: 'normal', approved: true })
    ElMessage.success('审批成功')
    loadData()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '审批失败')
  }
}

onMounted(() => {
  loadMyToday()
  loadSummary()
  loadData()
})
</script>

<style scoped>
.attendance-manage-container { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }

.today-card { margin-bottom: 20px; }
.today-info { display: flex; align-items: center; gap: 30px; }
.today-date { font-size: 18px; font-weight: 500; }
.clock-buttons { display: flex; gap: 10px; }
.status-badge { margin-left: auto; }

.summary-row { margin-bottom: 20px; }
.stat-item { text-align: center; padding: 10px 0; }
.stat-value { font-size: 28px; font-weight: bold; color: #409eff; }
.stat-item.warning .stat-value { color: #e6a23c; }
.stat-item.danger .stat-value { color: #f56c6c; }
.stat-label { font-size: 14px; color: #909399; margin-top: 5px; }

.search-card { margin-bottom: 20px; }
.data-card { margin-bottom: 20px; }

.pagination-container { margin-top: 20px; display: flex; justify-content: flex-end; }
.text-warning { color: #e6a23c; font-weight: 500; }
</style>
