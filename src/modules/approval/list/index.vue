<template>
  <div class="approval-list-container">
    <!-- Header -->
    <div class="page-header">
      <h2>审批列表</h2>
      <div class="header-actions">
        <el-button type="primary" @click="handleCreate">新建审批</el-button>
      </div>
    </div>

    <!-- Tabs -->
    <el-tabs v-model="activeTab" @tab-change="loadData" class="mb-4">
      <el-tab-pane label="我的申请" name="my" />
      <el-tab-pane label="待我审批" name="pending" />
      <el-tab-pane label="已审批" name="processed" />
      <el-tab-pane label="全部" name="all" />
    </el-tabs>

    <!-- Search Form -->
    <el-card class="search-card" v-if="activeTab !== 'pending'">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="审批类型">
          <el-select v-model="searchForm.type" placeholder="全部" clearable @change="loadData">
            <el-option v-for="t in approvalTypes" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="搜索关键词" clearable @change="loadData" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadData">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Pending List (Approver View) -->
    <el-card class="search-card" v-if="activeTab === 'pending'">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="申请人">
          <el-input v-model="searchForm.applicant_name" placeholder="申请人姓名" clearable @change="loadData" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadData">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Approval List -->
    <el-card class="data-card">
      <el-table v-loading="loading" :data="list" stripe border>
        <el-table-column prop="id" label="审批单号" width="100" />
        <el-table-column prop="applicant_name" label="申请人" width="100" />
        <el-table-column prop="department" label="部门" width="120" />
        <el-table-column prop="type" label="审批类型" width="120">
          <template #default="{ row }">
            {{ getTypeText(row.type) }}
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="150" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="current_step" label="当前步骤" width="100" />
        <el-table-column prop="created_at" label="申请时间" width="160" />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleView(row)">查看</el-button>
            <template v-if="row.status === 'pending' && activeTab === 'pending'">
              <el-button link type="success" size="small" @click="handleApprove(row)">通过</el-button>
              <el-button link type="danger" size="small" @click="handleReject(row)">拒绝</el-button>
            </template>
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

    <!-- Detail Dialog -->
    <el-dialog v-model="detailVisible" title="审批详情" width="700px">
      <el-descriptions :column="2" border v-if="currentRow">
        <el-descriptions-item label="审批单号">{{ currentRow.id }}</el-descriptions-item>
        <el-descriptions-item label="申请人">{{ currentRow.applicant_name }}</el-descriptions-item>
        <el-descriptions-item label="部门">{{ currentRow.department }}</el-descriptions-item>
        <el-descriptions-item label="审批类型">{{ getTypeText(currentRow.type) }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="getStatusType(currentRow.status)">{{ getStatusText(currentRow.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="当前步骤">{{ currentRow.current_step }}</el-descriptions-item>
        <el-descriptions-item label="申请时间" :span="2">{{ currentRow.created_at }}</el-descriptions-item>
        <el-descriptions-item label="标题" :span="2">{{ currentRow.title }}</el-descriptions-item>
        <el-descriptions-item label="内容" :span="2">
          <div v-html="currentRow.content"></div>
        </el-descriptions-item>
      </el-descriptions>

      <!-- Approval History -->
      <el-divider>审批记录</el-divider>
      <el-timeline v-if="currentRow.history">
        <el-timeline-item v-for="h in currentRow.history" :key="h.id" :type="h.action === 'approve' ? 'success' : 'danger'">
          <p><strong>{{ h.approver_name }}</strong> {{ h.action === 'approve' ? '通过' : '拒绝' }}</p>
          <p class="text-sm text-gray">{{ h.comment }}</p>
          <p class="text-xs text-gray">{{ h.action_at }}</p>
        </el-timeline-item>
      </el-timeline>

      <!-- Approval Action (for pending items) -->
      <div class="approval-actions" v-if="currentRow && currentRow.status === 'pending' && activeTab === 'pending'">
        <el-divider />
        <el-form :model="approvalForm" label-width="80px">
          <el-form-item label="审批意见">
            <el-input v-model="approvalForm.comment" type="textarea" rows="3" placeholder="请输入审批意见" />
          </el-form-item>
        </el-form>
        <div class="dialog-footer">
          <el-button type="danger" @click="doReject">拒绝</el-button>
          <el-button type="success" @click="doApprove">通过</el-button>
        </div>
      </div>
      <div v-else class="dialog-footer-only">
        <el-button @click="detailVisible = false">关闭</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getApprovalTypes, createApproval, getApprovals, getApprovalDetail, approveApproval, rejectApproval, withdrawApproval } from '@/api/oa'

const router = useRouter()
const loading = ref(false)
const list = ref([])
const activeTab = ref('my')
const currentRow = ref(null)
const detailVisible = ref(false)
const approvalTypes = ref([])

const searchForm = reactive({
  type: '',
  keyword: '',
  applicant_name: ''
})

const pagination = reactive({
  page: 1,
  size: 10,
  total: 0
})

const approvalForm = reactive({
  comment: ''
})

const getTypeText = (type) => {
  const map = { leave: '请假', overtime: '加班', expense: '报销', business: '出差', general: '一般审批' }
  return map[type] || type
}

const getStatusText = (status) => {
  const map = { pending: '待审批', approved: '已通过', rejected: '已拒绝', withdrawn: '已撤回' }
  return map[status] || status
}

const getStatusType = (status) => {
  const map = { pending: 'warning', approved: 'success', rejected: 'danger', withdrawn: 'info' }
  return map[status] || 'info'
}

const loadApprovalTypes = async () => {
  try {
    const data = await getApprovalTypes()
    approvalTypes.value = data || []
  } catch (e) {
    console.error(e)
  }
}

const loadData = async () => {
  loading.value = true
  try {
    let params = {
      page: pagination.page,
      size: pagination.size
    }
    
    if (activeTab.value === 'my') {
      params.my = 1
    } else if (activeTab.value === 'pending') {
      params.pending = 1
    } else if (activeTab.value === 'processed') {
      params.processed = 1
    }
    
    if (searchForm.type) params.type = searchForm.type
    if (searchForm.keyword) params.keyword = searchForm.keyword
    if (searchForm.applicant_name) params.applicant_name = searchForm.applicant_name
    
    const data = await getApprovals(params)
    list.value = data.list || []
    pagination.total = data.total || 0
  } catch (e) {
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

const resetSearch = () => {
  searchForm.type = ''
  searchForm.keyword = ''
  searchForm.applicant_name = ''
  pagination.page = 1
  loadData()
}

const handleCreate = () => {
  router.push('/approval/create')
}

const handleView = async (row) => {
  try {
    const data = await getApprovalDetail(row.id)
    currentRow.value = data
    detailVisible.value = true
  } catch (e) {
    ElMessage.error('加载详情失败')
  }
}

const handleApprove = async (row) => {
  try {
    await ElMessageBox.confirm('确认通过该审批？', '提示', { type: 'warning' })
    await approveApproval(row.id, { comment: '' })
    ElMessage.success('审批已通过')
    detailVisible.value = false
    loadData()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '操作失败')
  }
}

const handleReject = async (row) => {
  try {
    await ElMessageBox.confirm('确认拒绝该审批？', '提示', { type: 'warning' })
    await rejectApproval(row.id, { comment: '' })
    ElMessage.success('审批已拒绝')
    detailVisible.value = false
    loadData()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '操作失败')
  }
}

const doApprove = async () => {
  if (!currentRow.value) return
  try {
    await approveApproval(currentRow.value.id, { comment: approvalForm.comment })
    ElMessage.success('审批已通过')
    detailVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

const doReject = async () => {
  if (!currentRow.value) return
  try {
    await rejectApproval(currentRow.value.id, { comment: approvalForm.comment })
    ElMessage.success('审批已拒绝')
    detailVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

onMounted(() => {
  loadApprovalTypes()
  loadData()
})
</script>

<style scoped>
.approval-list-container { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
.mb-4 { margin-bottom: 16px; }

.search-card { margin-bottom: 20px; }
.data-card { margin-bottom: 20px; }

.pagination-container { margin-top: 20px; display: flex; justify-content: flex-end; }
.text-sm { font-size: 14px; }
.text-gray { color: #909399; }
.text-xs { font-size: 12px; }

.approval-actions { margin-top: 20px; }
.dialog-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
</style>
