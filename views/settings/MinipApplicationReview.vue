<template>
  <div class="minip-application-review">
    <PageHeader title="📋 入会申请审核" subtitle="管理 minip 首页「企业入会」提交的申请，审核通过后用户可登录员工后台" />

    <el-card>
      <div class="toolbar">
        <el-select v-model="filterStatus" placeholder="按状态筛选" clearable style="width:160px">
          <el-option label="待审核 (pending)" value="pending" />
          <el-option label="已通过 (approved)" value="approved" />
          <el-option label="已拒绝 (rejected)" value="rejected" />
        </el-select>
        <el-button @click="loadList">刷新</el-button>
      </div>

      <el-table :data="filteredList" stripe v-loading="loading">
        <el-table-column label="ID" prop="id" width="60" />
        <el-table-column label="公司名称" prop="company_name" width="180" />
        <el-table-column label="联系人" prop="contact_name" width="100" />
        <el-table-column label="联系电话" prop="contact_phone" width="130" />
        <el-table-column label="邮箱" prop="contact_email" show-overflow-tooltip />
        <el-table-column label="业务类型" prop="business_type" width="100" />
        <el-table-column label="团队规模" prop="team_size" width="90" />
        <el-table-column label="期望入驻" width="120">
          <template #default="{ row }">
            <span style="font-size:12px">{{ row.expected_join_date ? new Date(row.expected_join_date).toLocaleDateString('zh-CN') : '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="审核人" prop="reviewer_name" width="100" />
        <el-table-column label="提交时间" width="150">
          <template #default="{ row }">
            <span style="font-size:12px">{{ new Date(row.created_at).toLocaleString('zh-CN') }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="showDetail(row)">详情</el-button>
            <el-button v-if="row.status === 'pending'" size="small" type="success" @click="review(row, 'approved')">通过</el-button>
            <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="review(row, 'rejected')">拒绝</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailVisible" title="申请详情" width="600">
      <el-descriptions v-if="current" :column="2" border>
        <el-descriptions-item label="公司名称">{{ current.company_name }}</el-descriptions-item>
        <el-descriptions-item label="联系人">{{ current.contact_name }}</el-descriptions-item>
        <el-descriptions-item label="联系电话">{{ current.contact_phone }}</el-descriptions-item>
        <el-descriptions-item label="邮箱">{{ current.contact_email }}</el-descriptions-item>
        <el-descriptions-item label="业务类型">{{ current.business_type }}</el-descriptions-item>
        <el-descriptions-item label="团队规模">{{ current.team_size }}</el-descriptions-item>
        <el-descriptions-item label="期望入驻" :span="2">
          {{ current.expected_join_date ? new Date(current.expected_join_date).toLocaleDateString('zh-CN') : '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="备注" :span="2">{{ current.remarks || '-' }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusType(current.status)" size="small">{{ current.status }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="审核人">{{ current.reviewer_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="审核意见" :span="2">{{ current.review_remarks || '-' }}</el-descriptions-item>
        <el-descriptions-item label="审核时间" :span="2">
          {{ current.reviewed_at ? new Date(current.reviewed_at).toLocaleString('zh-CN') : '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="提交时间" :span="2">
          {{ new Date(current.created_at).toLocaleString('zh-CN') }}
        </el-descriptions-item>
      </el-descriptions>

      <div v-if="current && current.status === 'pending'" style="margin-top:16px">
        <el-input v-model="reviewRemarks" type="textarea" :rows="3" placeholder="审核意见（可选）" />
      </div>

      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
        <template v-if="current && current.status === 'pending'">
          <el-button type="danger" @click="review(current, 'rejected')" :loading="reviewing">拒绝</el-button>
          <el-button type="success" @click="review(current, 'approved')" :loading="reviewing">通过</el-button>
        </template>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const list = ref([])
const loading = ref(false)
const filterStatus = ref('')
const detailVisible = ref(false)
const current = ref(null)
const reviewRemarks = ref('')
const reviewing = ref(false)

const filteredList = computed(() =>
  filterStatus.value ? list.value.filter(a => a.status === filterStatus.value) : list.value
)

function statusType(s) {
  return { pending: 'warning', approved: 'success', rejected: 'danger' }[s] || 'info'
}

async function loadList() {
  loading.value = true
  try {
    const r = await api.get('/minip/admin/applications')
    list.value = r.data?.data || []
  } catch (e) {
    ElMessage.error('加载失败: ' + (e.response?.data?.message || e.message))
  }
  loading.value = false
}

function showDetail(row) {
  current.value = row
  reviewRemarks.value = ''
  detailVisible.value = true
}

async function review(row, status) {
  reviewing.value = true
  try {
    const r = await api.put(`/minip/admin/applications/${row.id}/review`, {
      status,
      review_remarks: reviewRemarks.value || ''
    })
    if (r.data?.code === 0) {
      ElMessage.success(status === 'approved' ? '已通过' : '已拒绝')
      detailVisible.value = false
      loadList()
    } else {
      ElMessage.error(r.data?.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error('操作失败: ' + (e.response?.data?.message || e.message))
  }
  reviewing.value = false
}

onMounted(loadList)
</script>

<style scoped>
.minip-application-review { padding: 16px; }
.toolbar { display: flex; gap: 12px; margin-bottom: 16px; }
</style>