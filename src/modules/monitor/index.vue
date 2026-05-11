<template>
  <div class="monitor-container">
    <el-card>
      <!-- Search Form -->
      <el-form :model="searchForm" label-width="80px" @submit.prevent="handleSearch">
        <el-row :gutter="20">
          <el-col :span="6">
            <el-form-item label="Keyword">
              <el-input v-model="searchForm.keyword" placeholder="Enter keyword" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-button type="primary" @click="handleSearch">Search</el-button>
            <el-button @click="resetSearch">Reset</el-button>
          </el-col>
        </el-row>
      </el-form>

      <!-- Table Actions -->
      <div class="table-actions">
        <el-button type="primary" @click="handleAdd">Add</el-button>
      </div>

      <!-- Table -->
      <el-table :data="list" border stripe :loading="loading" style="width: 100%">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="Name" />
        <el-table-column prop="status" label="Status" />
        <el-table-column prop="createdAt" label="Created At" />
        <el-table-column label="Actions" width="120">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">Edit</el-button>
            <el-button link type="danger" @click="handleDelete(row.id)">Delete</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- Pagination -->
      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :total="total"
        layout="total, prev, pager, next"
        @current-change="loadData"
        @size-change="loadData"
      />

      <!-- Dialog -->
      <el-dialog v-model="dialogVisible" :title="formData.id ? 'Edit Monitor' : 'Add Monitor'">
        <el-form v-model="formData" label-width="120px">
          <el-form-item label="Name">
            <el-input v-model="formData.name" />
          </el-form-item>
          <el-form-item label="Status">
            <el-select v-model="formData.status" placeholder="Select status">
              <el-option label="Active" value="active" />
              <el-option label="Inactive" value="inactive" />
            </el-select>
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="dialogVisible = false">Cancel</el-button>
          <el-button type="primary" @click="dialogSubmit">Submit</el-button>
        </template>
      </el-dialog>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getMonitorList } from '@/api/monitor'

// Reactive state
const list = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const searchForm = reactive({
  keyword: ''
})
const dialogVisible = ref(false)
const formData = reactive({
  id: null,
  name: '',
  status: 'active'
})

// Load data from API
const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      keyword: searchForm.keyword
    }
    const { data, total: totalItems } = await getMonitorList(params)
    list.value = data
    total.value = totalItems
  } finally {
    loading.value = false
  }
}

// Search handler
const handleSearch = () => {
  currentPage.value = 1
  loadData()
}

// Reset search
const resetSearch = () => {
  searchForm.keyword = ''
  handleSearch()
}

// Add new item
const handleAdd = () => {
  formData.id = null
  formData.name = ''
  formData.status = 'active'
  dialogVisible.value = true
}

// Edit existing item
const handleEdit = (row) => {
  Object.assign(formData, row)
  dialogVisible.value = true
}

// Delete item
const handleDelete = (id) => {
  ElMessageBox.confirm('Are you sure to delete this item?', 'Warning', {
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel',
    type: 'warning'
  }).then(async () => {
    // TODO: Call delete API
    ElMessage.success('Deleted successfully')
    await loadData()
  }).catch(() => {})
}

// Dialog submit
const dialogSubmit = async () => {
  try {
    // TODO: Call add/edit API
    ElMessage.success(formData.id ? 'Updated successfully' : 'Added successfully')
    dialogVisible.value = false
    await loadData()
  } catch (error) {
    ElMessage.error('Operation failed')
  }
}

// Initial load
onMounted(() => {
  loadData()
})
</script>

<style scoped>
.monitor-container {
  padding: 20px;
}

.table-actions {
  margin: 16px 0;
}

:deep(.el-table) {
  margin-top: 16px;
}
</style>