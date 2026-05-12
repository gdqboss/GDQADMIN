<template>
  <div class="excel-report">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header">
          <span class="card-title">Excel报表管理</span>
          <el-button type="primary" @click="handleUpload">
            <el-icon><Upload /></el-icon>
            上传Excel
          </el-button>
        </div>
      </template>
    </el-card>

    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="文件名">
          <el-input v-model="searchForm.name" placeholder="请输入文件名" clearable />
        </el-form-item>
        <el-form-item label="上传日期">
          <el-date-picker
            v-model="searchForm.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
          />
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
        <el-table-column prop="name" label="文件名" min-width="200">
          <template #default="{ row }">
            <div class="file-name">
              <el-icon><Document /></el-icon>
              <span>{{ row.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="size" label="大小" width="120">
          <template #default="{ row }">
            {{ formatFileSize(row.size) }}
          </template>
        </el-table-column>
        <el-table-column prop="type" label="类型" width="120">
          <template #default="{ row }">
            <el-tag type="success">{{ row.type || 'xlsx' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="uploader" label="上传人" width="120" />
        <el-table-column prop="createdAt" label="上传时间" width="180" />
        <el-table-column prop="downloadCount" label="下载次数" width="100" align="center" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleDownload(row)">下载</el-button>
            <el-button type="primary" link @click="handlePreview(row)">预览</el-button>
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

    <el-dialog v-model="uploadDialogVisible" title="上传Excel文件" width="500px">
      <el-upload
        ref="uploadRef"
        class="upload-demo"
        drag
        :action="uploadUrl"
        :headers="uploadHeaders"
        :on-success="handleUploadSuccess"
        :on-error="handleUploadError"
        :on-progress="handleUploadProgress"
        :before-upload="handleBeforeUpload"
        :file-list="fileList"
        accept=".xlsx,.xls"
      >
        <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
        <div class="el-upload__text">拖拽文件到此处或<em>点击上传</em></div>
        <template #tip>
          <div class="el-upload__tip">只能上传xlsx/xls文件，且不超过10MB</div>
        </template>
      </el-upload>
    </el-dialog>

    <el-dialog v-model="previewDialogVisible" title="Excel预览" width="90%" top="5vh">
      <div v-loading="previewLoading" class="preview-container">
        <el-alert v-if="previewError" :title="previewError" type="error" :closable="false" show-icon />
        <el-empty v-else-if="!previewData.length" description="暂无数据" />
        <el-table v-else :data="previewData" border stripe max-height="600" show-summary>
          <el-table-column v-for="(col, index) in previewColumns" :key="index" :prop="col.prop" :label="col.label" :width="col.width" />
        </el-table>
      </div>
      <template #footer>
        <el-button @click="previewDialogVisible = false">关闭</el-button>
        <el-button type="primary" @click="handleDownloadCurrent">下载当前</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Upload, Document, UploadFilled } from '@element-plus/icons-vue'

const loading = ref(false)
const previewLoading = ref(false)
const list = ref([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(10)
const uploadDialogVisible = ref(false)
const previewDialogVisible = ref(false)
const uploadRef = ref(null)
const fileList = ref([])
const previewData = ref([])
const previewColumns = ref([])
const previewError = ref('')

const uploadUrl = '/api/report/excel/upload'
const uploadHeaders = { Authorization: 'Bearer ' + localStorage.getItem('token') || '' }

const searchForm = reactive({
  name: '',
  dateRange: []
})

const formatFileSize = (size) => {
  if (!size) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(size) / Math.log(k))
  return parseFloat((size / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const loadData = async () => {
  loading.value = true
  try {
    // Simulated data
    list.value = [
      { id: 1, name: '销售报表_2026年5月.xlsx', size: 1258291, type: 'xlsx', uploader: '管理员', createdAt: '2026-05-10 14:30:00', downloadCount: 25 },
      { id: 2, name: '财务报表_2026年Q1.xlsx', size: 894566, type: 'xlsx', uploader: '财务部', createdAt: '2026-04-15 09:20:00', downloadCount: 42 },
      { id: 3, name: '库存汇总_2026年4月.xlsx', size: 567234, type: 'xlsx', uploader: '仓库管理', createdAt: '2026-05-01 16:45:00', downloadCount: 18 },
      { id: 4, name: '客户分析报告.xlsx', size: 1456321, type: 'xlsx', uploader: '市场部', createdAt: '2026-05-08 11:15:00', downloadCount: 33 }
    ]
    total.value = 4
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
  searchForm.dateRange = []
  handleSearch()
}

const handleUpload = () => {
  fileList.value = []
  uploadDialogVisible.value = true
}

const handleBeforeUpload = (file) => {
  const isExcel = file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
    file.type === 'application/vnd.ms-excel'
  const isLt10M = file.size / 1024 / 1024 < 10

  if (!isExcel) {
    ElMessage.error('只能上传Excel文件(.xlsx, .xls)')
    return false
  }
  if (!isLt10M) {
    ElMessage.error('文件大小不能超过10MB')
    return false
  }
  return true
}

const handleUploadSuccess = (res) => {
  if (res.code === 0 || res.success) {
    ElMessage.success('上传成功')
    uploadDialogVisible.value = false
    loadData()
  } else {
    ElMessage.error(res.message || '上传失败')
  }
}

const handleUploadError = () => {
  ElMessage.error('上传失败，请重试')
}

const handleUploadProgress = () => {
  // Progress handling if needed
}

const handleDownload = (row) => {
  ElMessage.success(`开始下载: ${row.name}`)
  // Actual download implementation would use window.open or axios with responseType blob
}

const handlePreview = async (row) => {
  previewDialogVisible.value = true
  previewLoading.value = true
  previewError.value = ''
  previewData.value = []

  try {
    // Simulated preview data
    previewColumns.value = [
      { prop: 'id', label: '序号', width: 80 },
      { prop: 'product', label: '产品名称', width: 150 },
      { prop: 'category', label: '分类', width: 120 },
      { prop: 'sales', label: '销售额', width: 120 },
      { prop: 'cost', label: '成本', width: 120 },
      { prop: 'profit', label: '利润', width: 120 },
      { prop: 'quantity', label: '数量', width: 80 }
    ]
    previewData.value = [
      { id: 1, product: '智能手表 Pro', category: '电子产品', sales: 156000, cost: 98000, profit: 58000, quantity: 120 },
      { id: 2, product: '无线蓝牙耳机', category: '电子产品', sales: 89500, cost: 52000, profit: 37500, quantity: 250 },
      { id: 3, product: '运动跑步鞋', category: '服装鞋帽', sales: 67800, cost: 38000, profit: 29800, quantity: 180 },
      { id: 4, product: '有机纯牛奶', category: '食品饮料', sales: 45600, cost: 28000, profit: 17600, quantity: 450 },
      { id: 5, product: '多功能收纳箱', category: '家居用品', sales: 32400, cost: 18000, profit: 14400, quantity: 95 }
    ]
  } catch (e) {
    previewError.value = '预览失败: ' + e.message
  } finally {
    previewLoading.value = false
  }
}

const handleDownloadCurrent = () => {
  ElMessage.success('开始下载当前预览数据')
}

const handleDelete = (row) => {
  ElMessageBox.confirm('确定删除该报表吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      ElMessage.success('删除成功')
      loadData()
    } catch (e) {
      ElMessage.error('删除失败')
    }
  }).catch(() => {})
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.excel-report {
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
.file-name {
  display: flex;
  align-items: center;
  gap: 8px;
}
.upload-demo {
  width: 100%;
}
.preview-container {
  min-height: 300px;
}
</style>
