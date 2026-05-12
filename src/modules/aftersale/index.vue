<template>
  <div class="aftersale-container">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header">
          <span class="card-title">售后管理</span>
          <el-button type="primary" @click="handleAdd">
            <el-icon><Plus /></el-icon>
            新增售后
          </el-button>
        </div>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="售后单号">
          <el-input v-model="searchForm.afterSaleNo" placeholder="请输入售后单号" clearable />
        </el-form-item>
        <el-form-item label="产品名称">
          <el-input v-model="searchForm.productName" placeholder="请输入产品名称" clearable />
        </el-form-item>
        <el-form-item label="处理状态">
          <el-select v-model="searchForm.status" placeholder="请选择" clearable>
            <el-option label="待处理" value="0" />
            <el-option label="处理中" value="1" />
            <el-option label="已完成" value="2" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="never">
      <el-table :data="list" v-loading="loading" stripe border>
        <el-table-column prop="afterSaleNo" label="售后单号" width="180" />
        <el-table-column prop="productName" label="产品名称" min-width="150" />
        <el-table-column prop="customerName" label="客户姓名" width="120" />
        <el-table-column prop="customerPhone" label="联系电话" width="140" />
        <el-table-column prop="reason" label="故障原因" min-width="200" show-overflow-tooltip />
        <el-table-column prop="status" label="处理状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="handler" label="处理人" width="120" />
        <el-table-column prop="createdAt" label="创建时间" width="180" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleView(row)">查看</el-button>
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadData"
          @current-change="loadData"
        />
      </div>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑售后单' : '新增售后单'"
      width="700px"
      @close="handleDialogClose"
    >
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="售后单号" prop="afterSaleNo">
              <el-input v-model="formData.afterSaleNo" placeholder="系统自动生成" :disabled="isEdit" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="产品名称" prop="productName">
              <el-input v-model="formData.productName" placeholder="请输入产品名称" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="客户姓名" prop="customerName">
              <el-input v-model="formData.customerName" placeholder="请输入客户姓名" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系电话" prop="customerPhone">
              <el-input v-model="formData.customerPhone" placeholder="请输入联系电话" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="故障原因" prop="reason">
          <el-input v-model="formData.reason" type="textarea" :rows="3" placeholder="请详细描述故障原因" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="处理状态" prop="status">
              <el-select v-model="formData.status" placeholder="请选择状态">
                <el-option label="待处理" value="0" />
                <el-option label="处理中" value="1" />
                <el-option label="已完成" value="2" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="处理人" prop="handler">
              <el-input v-model="formData.handler" placeholder="请输入处理人" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="处理备注" prop="remark">
          <el-input v-model="formData.remark" type="textarea" :rows="3" placeholder="请输入处理备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitLoading">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="viewDialogVisible" title="售后详情" width="700px">
      <el-descriptions :column="2" border>
        <el-descriptions-item label="售后单号">{{ viewData.afterSaleNo }}</el-descriptions-item>
        <el-descriptions-item label="处理状态">
          <el-tag :type="getStatusType(viewData.status)">{{ getStatusText(viewData.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="产品名称" :span="2">{{ viewData.productName }}</el-descriptions-item>
        <el-descriptions-item label="客户姓名">{{ viewData.customerName }}</el-descriptions-item>
        <el-descriptions-item label="联系电话">{{ viewData.customerPhone }}</el-descriptions-item>
        <el-descriptions-item label="故障原因" :span="2">{{ viewData.reason }}</el-descriptions-item>
        <el-descriptions-item label="处理人">{{ viewData.handler }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ viewData.createdAt }}</el-descriptions-item>
        <el-descriptions-item label="处理备注" :span="2">{{ viewData.remark || '无' }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="viewDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getAftersaleList, addAftersale, updateAftersale, deleteAftersale } from '@/api/aftersale'

const loading = ref(false)
const submitLoading = ref(false)
const list = ref([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(10)
const dialogVisible = ref(false)
const viewDialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)

const searchForm = reactive({
  afterSaleNo: '',
  productName: '',
  status: ''
})

const formData = reactive({
  id: null,
  afterSaleNo: '',
  productName: '',
  customerName: '',
  customerPhone: '',
  reason: '',
  status: '0',
  handler: '',
  remark: ''
})

const viewData = reactive({
  afterSaleNo: '',
  productName: '',
  customerName: '',
  customerPhone: '',
  reason: '',
  status: '',
  handler: '',
  createdAt: '',
  remark: ''
})

const rules = {
  productName: [{ required: true, message: '请输入产品名称', trigger: 'blur' }],
  customerName: [{ required: true, message: '请输入客户姓名', trigger: 'blur' }],
  customerPhone: [{ required: true, message: '请输入联系电话', trigger: 'blur' }],
  reason: [{ required: true, message: '请输入故障原因', trigger: 'blur' }]
}

const getStatusType = (status) => {
  const map = { '0': 'info', '1': 'warning', '2': 'success' }
  return map[status] || 'info'
}

const getStatusText = (status) => {
  const map = { '0': '待处理', '1': '处理中', '2': '已完成' }
  return map[status] || '未知'
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value
    }
    if (searchForm.afterSaleNo) params.afterSaleNo = searchForm.afterSaleNo
    if (searchForm.productName) params.productName = searchForm.productName
    if (searchForm.status) params.status = searchForm.status

    const res = await getAftersaleList(params)
    list.value = res.data?.list || res.data || []
    total.value = res.data?.total || 0
  } catch (e) {
    ElMessage.error('获取售后列表失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadData()
}

const handleReset = () => {
  searchForm.afterSaleNo = ''
  searchForm.productName = ''
  searchForm.status = ''
  handleSearch()
}

const handleAdd = () => {
  isEdit.value = false
  formData.id = null
  formData.afterSaleNo = ''
  formData.productName = ''
  formData.customerName = ''
  formData.customerPhone = ''
  formData.reason = ''
  formData.status = '0'
  formData.handler = ''
  formData.remark = ''
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  formData.id = row.id
  formData.afterSaleNo = row.afterSaleNo
  formData.productName = row.productName
  formData.customerName = row.customerName
  formData.customerPhone = row.customerPhone
  formData.reason = row.reason
  formData.status = row.status
  formData.handler = row.handler || ''
  formData.remark = row.remark || ''
  dialogVisible.value = true
}

const handleView = (row) => {
  viewData.afterSaleNo = row.afterSaleNo
  viewData.productName = row.productName
  viewData.customerName = row.customerName
  viewData.customerPhone = row.customerPhone
  viewData.reason = row.reason
  viewData.status = row.status
  viewData.handler = row.handler || ''
  viewData.createdAt = row.createdAt
  viewData.remark = row.remark || ''
  viewDialogVisible.value = true
}

const handleDelete = (row) => {
  ElMessageBox.confirm('确定删除该售后单吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      await deleteAftersale(row.id)
      ElMessage.success('删除成功')
      loadData()
    } catch (e) {
      ElMessage.error('删除失败')
    }
  }).catch(() => {})
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate()

  submitLoading.value = true
  try {
    if (isEdit.value) {
      await updateAftersale(formData.id, formData)
      ElMessage.success('更新成功')
    } else {
      await addAftersale(formData)
      ElMessage.success('新增成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(isEdit.value ? '更新失败' : '新增失败')
  } finally {
    submitLoading.value = false
  }
}

const handleDialogClose = () => {
  formRef.value?.resetFields()
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.aftersale-container {
  padding: 20px;
}
.header-card {
  margin-bottom: 20px;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.card-title {
  font-size: 18px;
  font-weight: 600;
}
.search-card {
  margin-bottom: 20px;
}
.table-card {
  margin-bottom: 20px;
}
.pagination-wrapper {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
