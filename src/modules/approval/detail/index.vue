<template>
  <div class="approval-detail-container">
    <!-- Header -->
    <div class="page-header">
      <h2>审批详情</h2>
      <el-button @click="$router.back()">返回</el-button>
    </div>

    <!-- Loading -->
    <el-card v-if="loading">
      <el-skeleton :rows="10" animated />
    </el-card>

    <!-- Detail Content -->
    <el-card v-else-if="detail">
      <!-- Basic Info -->
      <el-descriptions :column="2" border class="mb-4">
        <el-descriptions-item label="审批单号">{{ detail.id }}</el-descriptions-item>
        <el-descriptions-item label="审批类型">{{ getTypeText(detail.type) }}</el-descriptions-item>
        <el-descriptions-item label="申请人">{{ detail.applicant_name }}</el-descriptions-item>
        <el-descriptions-item label="部门">{{ detail.department }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="getStatusType(detail.status)">{{ getStatusText(detail.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="申请时间">{{ detail.created_at }}</el-descriptions-item>
        <el-descriptions-item label="标题" :span="2">{{ detail.title }}</el-descriptions-item>
      </el-descriptions>

      <!-- Content -->
      <el-divider content-position="left">申请内容</el-divider>
      <div class="content-body" v-html="detail.content"></div>

      <!-- Attachments -->
      <el-divider content-position="left" v-if="detail.attachments?.length">附件</el-divider>
      <div v-if="detail.attachments?.length" class="attachments">
        <el-link v-for="(url, idx) in detail.attachments" :key="idx" :href="url" target="_blank" type="primary">
          附件{{ idx + 1 }}
        </el-link>
      </div>

      <!-- Approval History -->
      <el-divider content-position="left">审批记录</el-divider>
      <el-timeline v-if="history.length">
        <el-timeline-item 
          v-for="h in history" 
          :key="h.id" 
          :type="h.action === 'approve' ? 'success' : 'danger'"
        >
          <div class="timeline-content">
            <strong>{{ h.approver_name }}</strong>
            <span class="ml-2">{{ h.action === 'approve' ? '通过了' : '拒绝了' }}</span>
            <span class="ml-2 text-gray">({{ h.step_name }})</span>
          </div>
          <div class="text-sm text-gray mt-1">{{ h.comment || '无' }}</div>
          <div class="text-xs text-gray mt-1">{{ h.action_at }}</div>
        </el-timeline-item>
      </el-timeline>
      <el-empty v-else description="暂无审批记录" />

      <!-- Action Buttons -->
      <div class="action-buttons" v-if="detail.status === 'pending' && canApprove">
        <el-form :model="approvalForm" label-width="80px">
          <el-form-item label="审批意见">
            <el-input v-model="approvalForm.comment" type="textarea" rows="3" placeholder="请输入审批意见" />
          </el-form-item>
        </el-form>
        <div class="dialog-footer">
          <el-button type="danger" @click="handleReject" :loading="actionLoading">拒绝</el-button>
          <el-button type="success" @click="handleApprove" :loading="actionLoading">通过</el-button>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { getApprovalDetail, approveApproval, rejectApproval } from '@/api/oa'

const route = useRoute()
const loading = ref(true)
const detail = ref(null)
const history = ref([])
const actionLoading = ref(false)

const approvalForm = reactive({ comment: '' })

const canApprove = computed(() => {
  // Logic to determine if current user can approve this
  return true
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

const loadDetail = async () => {
  loading.value = true
  try {
    const id = route.params.id
    const data = await getApprovalDetail(id)
    detail.value = data
    history.value = data.history || []
  } catch (e) {
    ElMessage.error('加载详情失败')
  } finally {
    loading.value = false
  }
}

const handleApprove = async () => {
  actionLoading.value = true
  try {
    await approveApproval(detail.value.id, { comment: approvalForm.comment })
    ElMessage.success('审批已通过')
    loadDetail()
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    actionLoading.value = false
  }
}

const handleReject = async () => {
  actionLoading.value = true
  try {
    await rejectApproval(detail.value.id, { comment: approvalForm.comment })
    ElMessage.success('审批已拒绝')
    loadDetail()
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    actionLoading.value = false
  }
}

onMounted(() => {
  loadDetail()
})
</script>

<style scoped>
.approval-detail-container { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
.mb-4 { margin-bottom: 16px; }
.mt-1 { margin-top: 4px; }
.ml-2 { margin-left: 8px; }

.text-gray { color: #909399; }
.text-sm { font-size: 14px; }
.text-xs { font-size: 12px; }

.content-body { padding: 16px 0; line-height: 1.8; }
.attachments { display: flex; gap: 15px; }
.action-buttons { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ebeef5; }
.dialog-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
</style>
