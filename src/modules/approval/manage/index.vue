<template>
  <div class="approval-manage-container">
    <!-- Header -->
    <div class="page-header">
      <h2>审批管理</h2>
    </div>

    <!-- Stats -->
    <el-row :gutter="20" class="stats-row">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total || 0 }}</div>
            <div class="stat-label">审批总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item warning">
            <div class="stat-value">{{ stats.pending || 0 }}</div>
            <div class="stat-label">待审批</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item success">
            <div class="stat-value">{{ stats.approved || 0 }}</div>
            <div class="stat-label">已通过</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item danger">
            <div class="stat-value">{{ stats.rejected || 0 }}</div>
            <div class="stat-label">已拒绝</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Search -->
    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="申请人">
          <el-input v-model="searchForm.applicant_name" placeholder="申请人姓名" clearable @change="loadData" />
        </el-form-item>
        <el-form-item label="审批类型">
          <el-select v-model="searchForm.type" placeholder="全部" clearable @change="loadData">
            <el-option label="请假" value="leave" />
            <el-option label="加班" value="overtime" />
            <el-option label="报销" value="expense" />
            <el-option label="出差" value="business" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable @change="loadData">
            <el-option label="待审批" value="pending" />
            <el-option label="已通过" value="approved" />
            <el-option label="已拒绝" value="rejected" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadData">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- List -->
    <el-card class="data-card">
      <el-table v-loading="loading" :data="list" stripe border>
        <el-table-column prop="id" label="审批单号" width="100" />
        <el-table-column prop="applicant_name" label="申请人" width="120" />
        <el-table-column prop="department" label="部门" width="120" />
        <el-table-column prop="type" label="类型" width="100">
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
        <el-table-column prop="created_at" label="申请时间" width="160" />
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleView(row)">查看</el-button>
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getApprovals } from '@/api/oa'

const loading = ref(false)
const list = ref([])
const stats = ref({})

const searchForm = reactive({
  applicant_name: '',
  type: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  size: 10,
  total: 0
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

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      ...searchForm,
      page: pagination.page,
      size: pagination.size,
      all: 1
    }
    const data = await getApprovals(params)
    list.value = data.list || []
    pagination.total = data.total || 0
    
    // Calculate stats
    if (data.list) {
      stats.value.total = data.list.length
      stats.value.pending = data.list.filter(i => i.status === 'pending').length
      stats.value.approved = data.list.filter(i => i.status === 'approved').length
      stats.value.rejected = data.list.filter(i => i.status === 'rejected').length
    }
  } catch (e) {
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

const resetSearch = () => {
  searchForm.applicant_name = ''
  searchForm.type = ''
  searchForm.status = ''
  pagination.page = 1
  loadData()
}

const handleView = (row) => {
  // Navigate to detail or show dialog
  ElMessage.info('查看审批详情: ' + row.id)
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.approval-manage-container { padding: 20px; }
.page-header { margin-bottom: 20px; }
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
