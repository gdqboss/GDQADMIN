<template>
  <div class="leave-container">
    <!-- Header -->
    <div class="page-header">
      <h2>请假管理</h2>
      <div class="header-actions">
        <el-button type="primary" @click="handleAdd">新建请假</el-button>
      </div>
    </div>

    <!-- My Leave Today -->
    <el-card class="info-card" v-if="myLeave">
      <div class="leave-info">
        <span class="label">今日请假状态：</span>
        <el-tag :type="getStatusType(myLeave.status)">{{ getStatusText(myLeave.status) }}</el-tag>
        <span class="ml-4">{{ myLeave.leave_type }} - {{ myLeave.start_date }} 至 {{ myLeave.end_date }}</span>
      </div>
    </el-card>

    <!-- Search Form -->
    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="申请人">
          <el-input v-model="searchForm.user_name" placeholder="申请人姓名" clearable @change="loadData" />
        </el-form-item>
        <el-form-item label="请假类型">
          <el-select v-model="searchForm.type" placeholder="全部" clearable @change="loadData">
            <el-option label="年假" value="annual" />
            <el-option label="病假" value="sick" />
            <el-option label="事假" value="personal" />
            <el-option label="婚假" value="marriage" />
            <el-option label="产假" value="maternity" />
            <el-option label="丧假" value="bereavement" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable @change="loadData">
            <el-option label="待审批" value="pending" />
            <el-option label="已批准" value="approved" />
            <el-option label="已拒绝" value="rejected" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadData">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Leave List -->
    <el-card class="data-card">
      <el-table v-loading="loading" :data="list" stripe border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="user_name" label="申请人" width="100" />
        <el-table-column prop="department" label="部门" width="120" />
        <el-table-column prop="leave_type" label="请假类型" width="100">
          <template #default="{ row }">
            {{ getLeaveTypeText(row.leave_type) }}
          </template>
        </el-table-column>
        <el-table-column prop="start_date" label="开始日期" width="120" />
        <el-table-column prop="end_date" label="结束日期" width="120" />
        <el-table-column prop="days" label="天数" width="80" />
        <el-table-column prop="reason" label="请假原因" min-width="150" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleView(row)">查看</el-button>
            <el-button link type="success" size="small" @click="handleApprove(row)" v-if="row.status === 'pending' && isApprover">审批</el-button>
            <el-button link type="danger" size="small" @click="handleDelete(row)" v-if="row.is_mine && row.status === 'pending'">撤回</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.size"
          :page-sizes="[10, 20, 50]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next"
          @size-change="loadData"
          @current-change="loadData"
        />
      </div>
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑请假' : '新建请假'" width="600px">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="请假类型" prop="leave_type">
          <el-select v-model="form.leave_type" placeholder="请选择">
            <el-option label="年假" value="annual" />
            <el-option label="病假" value="sick" />
            <el-option label="事假" value="personal" />
            <el-option label="婚假" value="marriage" />
            <el-option label="产假" value="maternity" />
            <el-option label="丧假" value="bereavement" />
          </el-select>
        </el-form-item>
        <el-form-item label="开始日期" prop="start_date">
          <el-date-picker v-model="form.start_date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" />
        </el-form-item>
        <el-form-item label="结束日期" prop="end_date">
          <el-date-picker v-model="form.end_date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" />
        </el-form-item>
        <el-form-item label="天数" prop="days">
          <el-input-number v-model="form.days" :min="0.5" :max="30" :step="0.5" />
        </el-form-item>
        <el-form-item label="请假原因" prop="reason">
          <el-input v-model="form.reason" type="textarea" rows="3" placeholder="请输入请假原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm" :loading="submitting">提交</el-button>
      </template>
    </el-dialog>

    <!-- Detail Dialog -->
    <el-dialog v-model="detailVisible" title="请假详情" width="500px">
      <el-descriptions :column="2" border v-if="currentRow">
        <el-descriptions-item label="申请人">{{ currentRow.user_name }}</el-descriptions-item>
        <el-descriptions-item label="部门">{{ currentRow.department }}</el-descriptions-item>
        <el-descriptions-item label="请假类型">{{ getLeaveTypeText(currentRow.leave_type) }}</el-descriptions-item>
        <el-descriptions-item label="天数">{{ currentRow.days }}</el-descriptions-item>
        <el-descriptions-item label="开始日期">{{ currentRow.start_date }}</el-descriptions-item>
        <el-descriptions-item label="结束日期">{{ currentRow.end_date }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="getStatusType(currentRow.status)">{{ getStatusText(currentRow.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="申请时间">{{ currentRow.created_at }}</el-descriptions-item>
        <el-descriptions-item label="请假原因" :span="2">{{ currentRow.reason }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { createLeave, getLeaveList, approveLeave, deleteLeave } from '@/api/oa'

const loading = ref(false)
const list = ref([])
const myLeave = ref(null)
const dialogVisible = ref(false)
const detailVisible = ref(false)
const submitting = ref(false)
const isEdit = ref(false)
const currentRow = ref(null)
const formRef = ref(null)

const searchForm = reactive({
  user_name: '',
  type: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  size: 10,
  total: 0
})

const form = reactive({
  id: null,
  leave_type: 'annual',
  start_date: '',
  end_date: '',
  days: 1,
  reason: ''
})

const rules = {
  leave_type: [{ required: true, message: '请选择请假类型', trigger: 'change' }],
  start_date: [{ required: true, message: '请选择开始日期', trigger: 'change' }],
  end_date: [{ required: true, message: '请选择结束日期', trigger: 'change' }],
  reason: [{ required: true, message: '请输入请假原因', trigger: 'blur' }]
}

const isApprover = computed(() => {
  const user = JSON.parse(localStorage.getItem('caimeite_user') || '{}')
  return ['admin', 'manager'].includes(user.role)
})

const getLeaveTypeText = (type) => {
  const map = { annual: '年假', sick: '病假', personal: '事假', marriage: '婚假', maternity: '产假', bereavement: '丧假' }
  return map[type] || type
}

const getStatusText = (status) => {
  const map = { pending: '待审批', approved: '已批准', rejected: '已拒绝' }
  return map[status] || status
}

const getStatusType = (status) => {
  const map = { pending: 'warning', approved: 'success', rejected: 'danger' }
  return map[status] || 'info'
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      ...searchForm,
      page: pagination.page,
      size: pagination.size
    }
    const data = await getLeaveList(params)
    list.value = data.list || []
    pagination.total = data.total || 0
  } catch (e) {
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

const resetSearch = () => {
  searchForm.user_name = ''
  searchForm.type = ''
  searchForm.status = ''
  pagination.page = 1
  loadData()
}

const handleAdd = () => {
  isEdit.value = false
  Object.assign(form, {
    id: null,
    leave_type: 'annual',
    start_date: '',
    end_date: '',
    days: 1,
    reason: ''
  })
  dialogVisible.value = true
}

const handleView = (row) => {
  currentRow.value = row
  detailVisible.value = true
}

const handleApprove = async (row) => {
  try {
    await ElMessageBox.confirm('确认批准该请假申请？', '提示', { type: 'warning' })
    await approveLeave(row.id, { status: 'approved' })
    ElMessage.success('审批成功')
    loadData()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '审批失败')
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确认撤回该请假申请？', '提示', { type: 'warning' })
    await deleteLeave(row.id)
    ElMessage.success('撤回成功')
    loadData()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '撤回失败')
  }
}

const submitForm = async () => {
  if (!formRef.value) return
  await formRef.value.validate()
  
  submitting.value = true
  try {
    await createLeave(form)
    ElMessage.success('提交成功')
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(e.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.leave-container { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }

.info-card { margin-bottom: 20px; }
.leave-info { display: flex; align-items: center; gap: 10px; }
.ml-4 { margin-left: 16px; }

.search-card { margin-bottom: 20px; }
.data-card { margin-bottom: 20px; }

.pagination-container { margin-top: 20px; display: flex; justify-content: flex-end; }
</style>
