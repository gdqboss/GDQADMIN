<template>
  <div class="module-page module-attendance-summary">
    <el-card>
      <template #header>
        <div class="card-header">
          <span class="module-title">考勤汇总</span>
          <div class="header-actions">
            <el-button type="primary" @click="handleExport">导出报表</el-button>
            <el-button @click="handlePrint">打印</el-button>
          </div>
        </div>
      </template>

      <!-- 月份选择 -->
      <div class="month-selector">
        <el-radio-group v-model="currentMonth" size="large">
          <el-radio-button value="2026-05">2026年5月</el-radio-button>
          <el-radio-button value="2026-04">2026年4月</el-radio-button>
          <el-radio-button value="2026-03">2026年3月</el-radio-button>
        </el-radio-group>
        <el-button style="margin-left: 16px" @click="handleRefresh">刷新数据</el-button>
      </div>

      <!-- 汇总统计 -->
      <div class="summary-stats">
        <el-row :gutter="16">
          <el-col :span="6">
            <div class="summary-card">
              <div class="summary-icon"><el-icon><User /></el-icon></div>
              <div class="summary-content">
                <div class="summary-value">42</div>
                <div class="summary-label">应到人数</div>
              </div>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="summary-card">
              <div class="summary-icon success"><el-icon><Check /></el-icon></div>
              <div class="summary-content">
                <div class="summary-value">38</div>
                <div class="summary-label">出勤人数</div>
              </div>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="summary-card">
              <div class="summary-icon warning"><el-icon><Clock /></el-icon></div>
              <div class="summary-content">
                <div class="summary-value">3</div>
                <div class="summary-label">请假人数</div>
              </div>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="summary-card">
              <div class="summary-icon danger"><el-icon><Close /></el-icon></div>
              <div class="summary-content">
                <div class="summary-value">1</div>
                <div class="summary-label">缺勤人数</div>
              </div>
            </div>
          </el-col>
        </el-row>
      </div>

      <!-- 汇总表格 -->
      <el-table :data="summaryData" stripe style="width: 100%">
        <el-table-column prop="department" label="部门" width="120" />
        <el-table-column prop="employeeName" label="员工姓名" width="100" />
        <el-table-column prop="employeeNo" label="工号" width="100" />
        <el-table-column prop="normalDays" label="正常天数" width="100" />
        <el-table-column prop="lateDays" label="迟到天数" width="100" />
        <el-table-column prop="earlyDays" label="早退天数" width="100" />
        <el-table-column prop="absentDays" label="缺勤天数" width="100" />
        <el-table-column prop="leaveDays" label="请假天数" width="100" />
        <el-table-column prop="totalHours" label="总工时" width="100" />
        <el-table-column prop="attendanceRate" label="出勤率" width="100">
          <template #default="{ row }">
            <el-progress :percentage="row.attendanceRate" :color="getProgressColor(row.attendanceRate)" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleViewDetail(row)">查看明细</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :page-sizes="[10, 20, 50]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { User, Check, Clock, Close } from '@element-plus/icons-vue'

const currentMonth = ref('2026-05')
const loading = ref(false)

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 42
})

const summaryData = ref([
  { department: '销售部', employeeName: '张三', employeeNo: 'E001', normalDays: 22, lateDays: 1, earlyDays: 0, absentDays: 0, leaveDays: 2, totalHours: 176.5, attendanceRate: 95 },
  { department: '销售部', employeeName: '李四', employeeNo: 'E002', normalDays: 20, lateDays: 2, earlyDays: 1, absentDays: 0, leaveDays: 2, totalHours: 168.0, attendanceRate: 87 },
  { department: '技术部', employeeName: '王五', employeeNo: 'E003', normalDays: 23, lateDays: 0, earlyDays: 0, absentDays: 0, leaveDays: 2, totalHours: 184.0, attendanceRate: 100 },
  { department: '技术部', employeeName: '赵六', employeeNo: 'E004', normalDays: 18, lateDays: 0, earlyDays: 0, absentDays: 1, leaveDays: 4, totalHours: 144.0, attendanceRate: 78 },
  { department: '行政部', employeeName: '孙七', employeeNo: 'E005', normalDays: 21, lateDays: 1, earlyDays: 1, absentDays: 0, leaveDays: 2, totalHours: 168.0, attendanceRate: 91 }
])

const getProgressColor = (percentage) => {
  if (percentage >= 95) return '#67c23a'
  if (percentage >= 85) return '#e6a23c'
  return '#f56c6c'
}

const handleExport = () => ElMessage.success('导出成功')
const handlePrint = () => ElMessage.info('打印功能')
const handleRefresh = () => ElMessage.success('数据已刷新')
const handleViewDetail = (row) => ElMessage.info(`查看 ${row.employeeName} 的明细`)
const handleSizeChange = (val) => { pagination.pageSize = val; pagination.page = 1 }
const handlePageChange = (val) => { pagination.page = val }
</script>

<style scoped>
.module-page { padding: 20px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.module-title { font-size: 18px; font-weight: 600; }
.month-selector { margin-bottom: 20px; display: flex; align-items: center; }
.summary-stats { margin-bottom: 20px; }
.summary-card { display: flex; align-items: center; padding: 20px; background: #f5f7fa; border-radius: 8px; }
.summary-icon { width: 48px; height: 48px; border-radius: 8px; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-right: 16px; }
.summary-icon.success { background: #67c23a; }
.summary-icon.warning { background: #e6a23c; }
.summary-icon.danger { background: #f56c6c; }
.summary-value { font-size: 24px; font-weight: bold; color: #303133; }
.summary-label { font-size: 14px; color: #909399; }
.pagination-wrapper { margin-top: 20px; display: flex; justify-content: flex-end; }
</style>
