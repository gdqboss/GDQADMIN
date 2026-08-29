<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const filterStatus = ref('')
const page = ref(1)
const pageSize = ref(20)
const keyword = ref('')

const showReviewDialog = ref(false)
const reviewing = ref(false)
const reviewForm = ref({ id: null, status: 'reviewing', review_remarks: '' })
const reviewTarget = ref(null)

// 状态 → 中文 + 颜色
const statusOptions = [
  { value: 'pending',    label: '待审核',   type: 'info' },
  { value: 'reviewing',  label: '审核中',   type: 'warning' },
  { value: 'approved',   label: '已通过',   type: 'success' },
  { value: 'rejected',   label: '已拒绝',   type: 'danger' },
]
const statusMeta = (s) => statusOptions.find(o => o.value === s) || { label: s, type: 'info' }

const fetchList = async () => {
  loading.value = true
  try {
    const params = { page: page.value, pageSize: pageSize.value }
    if (filterStatus.value) params.status = filterStatus.value
    if (keyword.value) params.keyword = keyword.value
    const res = await api.get('/minip/admin/applications', { params })
    if (res.code === 0) {
      list.value = res.data?.list || res.data || []
      total.value = res.data?.total || list.value.length
    } else {
      ElMessage.error(res.message || '加载失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '网络异常')
  } finally {
    loading.value = false
  }
}

const openReview = (row) => {
  reviewTarget.value = row
  reviewForm.value = { id: row.id, status: row.status === 'pending' ? 'reviewing' : 'approved', review_remarks: '' }
  showReviewDialog.value = true
}

const submitReview = async () => {
  if (!reviewForm.value.status) {
    ElMessage.warning('请选择审核结果')
    return
  }
  if (!['approved', 'rejected'].includes(reviewForm.value.status) && reviewForm.value.status !== 'reviewing') {
    ElMessage.warning('状态无效')
    return
  }
  reviewing.value = true
  try {
    const res = await api.put(`/minip/admin/applications/${reviewForm.value.id}/review`, {
      status: reviewForm.value.status,
      review_remarks: reviewForm.value.review_remarks,
    })
    if (res.code === 0) {
      ElMessage.success('审核已提交')
      showReviewDialog.value = false
      await fetchList()
    } else {
      ElMessage.error(res.message || '审核失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '网络异常')
  } finally {
    reviewing.value = false
  }
}

const quickApprove = async (row) => {
  try {
    await ElMessageBox.confirm(`确认通过「${row.company_name}」的入驻申请？`, '通过审核', { type: 'success' })
    const res = await api.put(`/minip/admin/applications/${row.id}/review`, { status: 'approved', review_remarks: '快速通过' })
    if (res.code === 0) { ElMessage.success('已通过'); await fetchList() }
    else ElMessage.error(res.message || '失败')
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message || '网络异常') }
}

const quickReject = async (row) => {
  try {
    const { value: remarks } = await ElMessageBox.prompt('请输入拒绝原因', '拒绝申请', {
      confirmButtonText: '确认拒绝', cancelButtonText: '取消', inputType: 'textarea', inputPlaceholder: '请说明拒绝原因...'
    })
    const res = await api.put(`/minip/admin/applications/${row.id}/review`, { status: 'rejected', review_remarks: remarks })
    if (res.code === 0) { ElMessage.success('已拒绝'); await fetchList() }
    else ElMessage.error(res.message || '失败')
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message || '网络异常') }
}

const formatDate = (d) => {
  if (!d) return '-'
  try { return new Date(d).toLocaleString('zh-CN', { hour12: false }) } catch { return d }
}

onMounted(fetchList)
</script>

<template>
  <div class="application-review">
    <PageHeader title="企业入驻审核" subtitle="管理 gbaw.cn 首页「企业入驻」板块提交的入驻申请" />

    <el-card class="filter-bar">
      <el-form :inline="true" @submit.prevent="fetchList">
        <el-form-item label="状态">
          <el-select v-model="filterStatus" placeholder="全部状态" clearable style="width: 140px" @change="fetchList">
            <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="搜索">
          <el-input v-model="keyword" placeholder="公司/联系人/电话" clearable style="width: 220px" @keyup.enter="fetchList" @clear="fetchList" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="'Search'" @click="fetchList">查询</el-button>
          <el-button :icon="'Refresh'" @click="fetchList">刷新</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card>
      <el-table :data="list" v-loading="loading" stripe border style="width: 100%">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="company_name" label="公司名称" min-width="180" show-overflow-tooltip />
        <el-table-column prop="contact_name" label="联系人" width="100" />
        <el-table-column prop="contact_phone" label="联系电话" width="130" />
        <el-table-column prop="business_type" label="业务类型" width="120" show-overflow-tooltip />
        <el-table-column prop="team_size" label="团队规模" width="100" />
        <el-table-column prop="expected_join_date" label="预计入驻" width="120">
          <template #default="{ row }">
            <span>{{ row.expected_join_date || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusMeta(row.status).type" size="small">{{ statusMeta(row.status).label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="提交时间" width="160">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="审核" width="200" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 'pending' || row.status === 'reviewing'" size="small" type="success" @click="quickApprove(row)">通过</el-button>
            <el-button v-if="row.status === 'pending' || row.status === 'reviewing'" size="small" type="danger" @click="quickReject(row)">拒绝</el-button>
            <el-button size="small" @click="openReview(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          background
          layout="total, prev, pager, next, sizes"
          :total="total"
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[20, 50, 100]"
          @current-change="fetchList"
          @size-change="fetchList"
        />
      </div>
    </el-card>

    <!-- 详情/审核弹窗 -->
    <el-dialog v-model="showReviewDialog" title="入驻申请详情" width="700px" :close-on-click-modal="false">
      <template v-if="reviewTarget">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="公司名称">{{ reviewTarget.company_name }}</el-descriptions-item>
          <el-descriptions-item label="联系人">{{ reviewTarget.contact_name }}</el-descriptions-item>
          <el-descriptions-item label="联系电话">{{ reviewTarget.contact_phone }}</el-descriptions-item>
          <el-descriptions-item label="联系邮箱">{{ reviewTarget.contact_email || '-' }}</el-descriptions-item>
          <el-descriptions-item label="业务类型">{{ reviewTarget.business_type || '-' }}</el-descriptions-item>
          <el-descriptions-item label="团队规模">{{ reviewTarget.team_size || '-' }}</el-descriptions-item>
          <el-descriptions-item label="预计入驻">{{ reviewTarget.expected_join_date || '-' }}</el-descriptions-item>
          <el-descriptions-item label="提交时间">{{ formatDate(reviewTarget.created_at) }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">
            <div style="white-space: pre-wrap">{{ reviewTarget.remarks || '（无）' }}</div>
          </el-descriptions-item>
          <el-descriptions-item label="审核结果">
            <el-tag :type="statusMeta(reviewTarget.status).type">{{ statusMeta(reviewTarget.status).label }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="审核时间">{{ formatDate(reviewTarget.reviewed_at) }}</el-descriptions-item>
          <el-descriptions-item v-if="reviewTarget.review_remarks" label="审核备注" :span="2">
            <div style="white-space: pre-wrap">{{ reviewTarget.review_remarks }}</div>
          </el-descriptions-item>
        </el-descriptions>

        <el-divider>审核操作</el-divider>
        <el-form label-width="100px">
          <el-form-item label="审核结果">
            <el-radio-group v-model="reviewForm.status">
              <el-radio-button value="reviewing">审核中</el-radio-button>
              <el-radio-button value="approved">通过</el-radio-button>
              <el-radio-button value="rejected">拒绝</el-radio-button>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="审核备注">
            <el-input v-model="reviewForm.review_remarks" type="textarea" :rows="3" placeholder="可填写审核意见" />
          </el-form-item>
        </el-form>
      </template>
      <template #footer>
        <el-button @click="showReviewDialog = false">取消</el-button>
        <el-button type="primary" :loading="reviewing" @click="submitReview">提交审核</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.filter-bar { margin-bottom: 16px; }
.pagination { margin-top: 16px; text-align: right; }
.application-review :deep(.el-card) { margin-bottom: 16px; }
</style>