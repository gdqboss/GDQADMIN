<template>
  <div class="module-page module-attendance-manage">
    <el-card>
      <template #header>
        <div class="card-header">
          <span class="module-title">考勤管理</span>
          <div class="header-actions">
            <el-button type="primary" @click="handleImport">导入考勤</el-button>
            <el-button type="success" @click="handleSync">同步设备</el-button>
            <el-button type="warning" @click="handleExport">导出记录</el-button>
          </div>
        </div>
      </template>

      <!-- 筛选区域 -->
      <div class="filter-section">
        <el-form :inline="true" :model="filterForm">
          <el-form-item label="部门">
            <el-select v-model="filterForm.department" placeholder="请选择部门" clearable style="width: 150px">
              <el-option label="销售部" value="sales" />
              <el-option label="技术部" value="tech" />
              <el-option label="行政部" value="admin" />
            </el-select>
          </el-form-item>
          <el-form-item label="日期">
            <el-date-picker v-model="filterForm.dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" style="width: 240px" />
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="filterForm.status" placeholder="请选择状态" clearable style="width: 120px">
              <el-option label="正常" value="normal" />
              <el-option label="迟到" value="late" />
              <el-option label="早退" value="early" />
              <el-option label="缺勤" value="absent" />
              <el-option label="请假" value="leave" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleSearch">查询</el-button>
            <el-button @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <!-- 统计卡片 -->
      <div class="stats-cards">
        <el-row :gutter="16">
          <el-col :span="6">
            <div class="stat-card normal">
              <div class="stat-value">{{ stats.normal }}</div>
              <div class="stat-label">正常</div>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="stat-card late">
              <div class="stat-value">{{ stats.late }}</div>
              <div class="stat-label">迟到</div>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="stat-card early">
              <div class="stat-value">{{ stats.early }}</div>
              <div class="stat-label">早退</div>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="stat-card absent">
              <div class="stat-value">{{ stats.absent }}</div>
              <div class="stat-label">缺勤</div>
            </div>
          </el-col>
        </el-row>
      </div>

      <!-- 数据表格 -->
      <el-table :data="tableData" stripe style="width: 100%" v-loading="loading">
        <el-table-column prop="employeeName" label="员工姓名" width="100" />
        <el-table-column prop="employeeNo" label="工号" width="100" />
        <el-table-column prop="department" label="部门" width="100" />
        <el-table-column prop="date" label="日期" width="120" />
        <el-table-column prop="checkInTime" label="签到时间" width="100" />
        <el-table-column prop="checkOutTime" label="签退时间" width="100" />
        <el-table-column prop="workingHours" label="工时" width="80" />
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="150" />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleDetail(row)">详情</el-button>
            <el-button link type="warning" size="small" @click="handleEdit(row)">补录</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>

    <!-- 详情对话框 -->
    <el-dialog v-model="detailDialogVisible" title="考勤详情" width="600px">
      <el-descriptions :column="2" border v-if="currentRow">
        <el-descriptions-item label="员工姓名">{{ currentRow.employeeName }}</el-descriptions-item>
        <el-descriptions-item label="工号">{{ currentRow.employeeNo }}</el-descriptions-item>
        <el-descriptions-item label="部门">{{ currentRow.department }}</el-descriptions-item>
        <el-descriptions-item label="日期">{{ currentRow.date }}</el-descriptions-item>
        <el-descriptions-item label="签到时间">{{ currentRow.checkInTime }}</el-descriptions-item>
        <el-descriptions-item label="签退时间">{{ currentRow.checkOutTime }}</el-descriptions-item>
        <el-descriptions-item label="工作时长">{{ currentRow.workingHours }}小时</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="getStatusType(currentRow.status)">{{ getStatusText(currentRow.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="备注" :span="2">{{ currentRow.remark }}</el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'

const loading = ref(false)
const detailDialogVisible = ref(false)
const currentRow = ref(null)

const filterForm = reactive({
  department: '',
  dateRange: [],
  status: ''
})

const stats = reactive({
  normal: 156,
  late: 12,
  early: 8,
  absent: 3
})

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 179
})

const tableData = ref([
  { employeeName: '张三', employeeNo: 'E001', department: '销售部', date: '2026-05-10', checkInTime: '08:55', checkOutTime: '18:05', workingHours: 9.2, status: 'normal', remark: '' },
  { employeeName: '李四', employeeNo: 'E002', department: '技术部', date: '2026-05-10', checkInTime: '09:15', checkOutTime: '18:00', workingHours: 8.8, status: 'late', remark: '早高峰堵车' },
  { employeeName: '王五', employeeNo: 'E003', department: '行政部', date: '2026-05-10', checkInTime: '08:30', checkOutTime: '17:30', workingHours: 9.0, status: 'early', remark: '提前下班开会' },
  { employeeName: '赵六', employeeNo: 'E004', department: '销售部', date: '2026-05-10', checkInTime: '--:--', checkOutTime: '--:--', workingHours: 0, status: 'absent', remark: '请假已批准' }
])

const getStatusType = (status) => {
  const map = { normal: 'success', late: 'warning', early: 'warning', absent: 'danger', leave: 'info' }
  return map[status] || 'info'
}

const getStatusText = (status) => {
  const map = { normal: '正常', late: '迟到', early: '早退', absent: '缺勤', leave: '请假' }
  return map[status] || status
}

const handleSearch = () => {
  loading.value = true
  setTimeout(() => { loading.value = false }, 500)
  ElMessage.success('查询成功')
}

const handleReset = () => {
  filterForm.department = ''
  filterForm.dateRange = []
  filterForm.status = ''
  ElMessage.info('已重置')
}

const handleImport = () => ElMessage.info('导入考勤功能')
const handleSync = () => ElMessage.info('同步设备功能')
const handleExport = () => ElMessage.info('导出记录功能')
const handleDetail = (row) => { currentRow.value = row; detailDialogVisible.value = true }
const handleEdit = (row) => ElMessage.info(`补录 ${row.employeeName} 的考勤`)
const handleSizeChange = (val) => { pagination.pageSize = val; pagination.page = 1 }
const handlePageChange = (val) => { pagination.page = val }
</script>

<style scoped>
.module-page { padding: 20px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.module-title { font-size: 18px; font-weight: 600; }
.header-actions { display: flex; gap: 8px; }
.filter-section { margin-bottom: 20px; padding: 16px; background: #f5f7fa; border-radius: 4px; }
.stats-cards { margin-bottom: 20px; }
.stat-card { padding: 20px; border-radius: 8px; text-align: center; color: #fff; }
.stat-card.normal { background: linear-gradient(135deg, #67c23a, #85ce61); }
.stat-card.late { background: linear-gradient(135deg, #e6a23c, #f5c67a); }
.stat-card.early { background: linear-gradient(135deg, #f56c6c, #f89a9a); }
.stat-card.absent { background: linear-gradient(135deg, #909399, #a6a9ad); }
.stat-value { font-size: 32px; font-weight: bold; }
.stat-label { font-size: 14px; margin-top: 4px; }
.pagination-wrapper { margin-top: 20px; display: flex; justify-content: flex-end; }
</style>
