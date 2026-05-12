<template>
  <div class="attendance-summary-container">
    <!-- Header -->
    <div class="page-header">
      <h2>考勤汇总</h2>
      <div class="header-actions">
        <el-button type="primary" @click="exportExcel">导出Excel</el-button>
      </div>
    </div>

    <!-- Summary Stats -->
    <el-row :gutter="20" class="stats-row">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.should_attend || 0 }}</div>
            <div class="stat-label">应打卡人数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item success">
            <div class="stat-value">{{ stats.checked_in || 0 }}</div>
            <div class="stat-label">已打卡人数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item warning">
            <div class="stat-value">{{ stats.late_count || 0 }}</div>
            <div class="stat-label">迟到人数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item danger">
            <div class="stat-value">{{ stats.absent_count || 0 }}</div>
            <div class="stat-label">旷工人数</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Search Form -->
    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="开始日期">
          <el-date-picker
            v-model="searchForm.start_date"
            type="date"
            value-format="YYYY-MM-DD"
            placeholder="选择开始日期"
            @change="loadData"
          />
        </el-form-item>
        <el-form-item label="结束日期">
          <el-date-picker
            v-model="searchForm.end_date"
            type="date"
            value-format="YYYY-MM-DD"
            placeholder="选择结束日期"
            @change="loadData"
          />
        </el-form-item>
        <el-form-item label="员工">
          <el-input v-model="searchForm.user_name" placeholder="员工姓名" clearable @change="loadData" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadData">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Summary Table -->
    <el-card class="data-card">
      <el-table v-loading="loading" :data="list" stripe border>
        <el-table-column prop="date" label="日期" width="120" />
        <el-table-column prop="user_name" label="姓名" width="100" />
        <el-table-column prop="department" label="部门" width="120" />
        <el-table-column prop="clock_in" label="上班打卡" width="120" />
        <el-table-column prop="clock_out" label="下班打卡" width="120" />
        <el-table-column prop="work_hours" label="工时" width="80">
          <template #default="{ row }">
            {{ calculateHours(row.clock_in, row.clock_out) }}
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getAttendanceSummary, getAttendanceList } from '@/api/oa'

const loading = ref(false)
const list = ref([])
const stats = ref({})

const searchForm = reactive({
  start_date: '',
  end_date: '',
  user_name: ''
})

const pagination = reactive({
  page: 1,
  size: 10,
  total: 0
})

const statusText = (status) => {
  const map = { normal: '正常', late: '迟到', early: '早退', absent: '旷工' }
  return map[status] || '未知'
}

const getStatusType = (status) => {
  const map = { normal: 'success', late: 'warning', early: 'warning', absent: 'danger' }
  return map[status] || 'info'
}

const calculateHours = (clockIn, clockOut) => {
  if (!clockIn || !clockOut) return '-'
  try {
    const [inH, inM] = clockIn.split(':').map(Number)
    const [outH, outM] = clockOut.split(':').map(Number)
    const minutes = (outH * 60 + outM) - (inH * 60 + inM)
    return (minutes / 60).toFixed(1) + 'h'
  } catch {
    return '-'
  }
}

const loadStats = async () => {
  try {
    const today = new Date().toISOString().slice(0, 10)
    const data = await getAttendanceSummary({ date: today })
    stats.value = data
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
  searchForm.start_date = ''
  searchForm.end_date = ''
  searchForm.user_name = ''
  pagination.page = 1
  loadData()
}

const exportExcel = () => {
  ElMessage.info('导出功能开发中')
}

onMounted(() => {
  loadStats()
  loadData()
})
</script>

<style scoped>
.attendance-summary-container { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }

.stats-row { margin-bottom: 20px; }
.stat-item { text-align: center; padding: 10px 0; }
.stat-value { font-size: 28px; font-weight: bold; color: #409eff; }
.stat-item.success .stat-value { color: #67c23a; }
.stat-item.warning .stat-value { color: #e6a23c; }
.stat-item.danger .stat-value { color: #f56c6c; }
.stat-label { font-size: 14px; color: #909399; margin-top: 5px; }

.search-card { margin-bottom: 20px; }
.data-card { margin-bottom: 20px; }

.pagination-container { margin-top: 20px; display: flex; justify-content: flex-end; }
</style>
