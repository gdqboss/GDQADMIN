<template>
  <div class="report-center">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header">
          <span class="card-title">报表中心</span>
          <el-button type="primary" @click="handleCreate">
            <el-icon><Plus /></el-icon>
            新建报表
          </el-button>
        </div>
      </template>
    </el-card>

    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="报表名称">
          <el-input v-model="searchForm.name" placeholder="请输入报表名称" clearable />
        </el-form-item>
        <el-form-item label="报表类型">
          <el-select v-model="searchForm.type" placeholder="请选择" clearable>
            <el-option label="销售报表" value="sales" />
            <el-option label="财务报表" value="finance" />
            <el-option label="库存报表" value="inventory" />
            <el-option label="客户报表" value="customer" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择" clearable>
            <el-option label="启用" value="1" />
            <el-option label="禁用" value="0" />
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
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="报表名称" min-width="180" />
        <el-table-column prop="type" label="类型" width="120">
          <template #default="{ row }">
            <el-tag>{{ getTypeName(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
        <el-table-column prop="creator" label="创建人" width="120" />
        <el-table-column prop="createdAt" label="创建时间" width="180" />
        <el-table-column prop="status" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(row)"
            />
          </template>
        </el-table-column>
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
      :title="isEdit ? '编辑报表' : '新建报表'"
      width="600px"
      @close="handleDialogClose"
    >
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="100px">
        <el-form-item label="报表名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入报表名称" />
        </el-form-item>
        <el-form-item label="报表类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择报表类型">
            <el-option label="销售报表" value="sales" />
            <el-option label="财务报表" value="finance" />
            <el-option label="库存报表" value="inventory" />
            <el-option label="客户报表" value="customer" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" type="textarea" :rows="3" placeholder="请输入描述" />
        </el-form-item>
        <el-form-item label="SQL查询" prop="sqlQuery">
          <el-input v-model="formData.sqlQuery" type="textarea" :rows="5" placeholder="SELECT * FROM ..." />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-switch v-model="formData.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitLoading">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="viewDialogVisible"
      title="报表详情"
      width="80%"
      top="5vh"
    >
      <div class="report-preview">
        <el-alert v-if="previewError" :title="previewError" type="error" :closable="false" />
        <el-empty v-else-if="!previewData" description="暂无数据" />
        <el-table v-else :data="previewData" border stripe max-height="500">
          <el-table-column v-for="(val, key) in (previewData[0] || {})" :key="key" :prop="String(key)" :label="String(key)" />
        </el-table>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getReportList, addReport, updateReport, deleteReport } from '@/api/report'

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
const previewData = ref(null)
const previewError = ref('')

const searchForm = reactive({
  name: '',
  type: '',
  status: ''
})

const formData = reactive({
  id: null,
  name: '',
  type: 'sales',
  description: '',
  sqlQuery: '',
  status: 1
})

const rules = {
  name: [{ required: true, message: '请输入报表名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择报表类型', trigger: 'change' }]
}

const getTypeName = (type) => {
  const map = { sales: '销售报表', finance: '财务报表', inventory: '库存报表', customer: '客户报表' }
  return map[type] || type
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value
    }
    if (searchForm.name) params.name = searchForm.name
    if (searchForm.type) params.type = searchForm.type
    if (searchForm.status) params.status = searchForm.status

    const res = await getReportList(params)
    list.value = res.data?.list || res.data || []
    total.value = res.data?.total || 0
  } catch (e) {
    ElMessage.error('获取报表列表失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadData()
}

const handleReset = () => {
  searchForm.name = ''
  searchForm.type = ''
  searchForm.status = ''
  handleSearch()
}

const handleCreate = () => {
  isEdit.value = false
  formData.id = null
  formData.name = ''
  formData.type = 'sales'
  formData.description = ''
  formData.sqlQuery = ''
  formData.status = 1
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  formData.id = row.id
  formData.name = row.name
  formData.type = row.type
  formData.description = row.description || ''
  formData.sqlQuery = row.sqlQuery || ''
  formData.status = row.status
  dialogVisible.value = true
}

const handleView = async (row) => {
  viewDialogVisible.value = true
  previewData.value = null
  previewError.value = ''
  try {
    // Simulated preview data
    previewData.value = [
      { id: 1, product: '产品A', sales: 12500, profit: 3200 },
      { id: 2, product: '产品B', sales: 8900, profit: 2100 },
      { id: 3, product: '产品C', sales: 15600, profit: 4500 }
    ]
  } catch (e) {
    previewError.value = '预览失败: ' + e.message
  }
}

const handleDelete = (row) => {
  ElMessageBox.confirm('确定删除该报表吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      await deleteReport(row.id)
      ElMessage.success('删除成功')
      loadData()
    } catch (e) {
      ElMessage.error('删除失败')
    }
  }).catch(() => {})
}

const handleStatusChange = async (row) => {
  try {
    await updateReport(row.id, { status: row.status })
    ElMessage.success('状态更新成功')
  } catch (e) {
    ElMessage.error('状态更新失败')
    row.status = row.status === 1 ? 0 : 1
  }
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate()

  submitLoading.value = true
  try {
    if (isEdit.value) {
      await updateReport(formData.id, formData)
      ElMessage.success('更新成功')
    } else {
      await addReport(formData)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(isEdit.value ? '更新失败' : '创建失败')
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
.report-center {
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
.filter-card {
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
.report-preview {
  min-height: 200px;
}
</style>
