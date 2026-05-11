<template>
  <el-card>
    <el-form :model="searchForm" label-width="80px" @submit.prevent="handleSearch">
      <el-row :gutter="20">
        <el-col :span="6">
          <el-form-item label="Name">
            <el-input v-model="searchForm.name" placeholder="Enter name" />
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-button type="primary" native-type="submit">Search</el-button>
          <el-button @click="handleAdd">Add</el-button>
        </el-col>
      </el-row>
    </el-form>

    <el-table :data="list" v-loading="loading" border style="width: 100%">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" label="Name" />
      <el-table-column prop="createdAt" label="Created At" />
      <el-table-column label="Actions" width="120">
        <template #default="scope">
          <el-button link type="primary" @click="handleEdit(scope.row)">Edit</el-button>
          <el-button link type="danger" @click="handleDelete(scope.row.id)">Delete</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      v-model:current-page="page"
      v-model:page-size="pageSize"
      :total="total"
      layout="total, prev, pager, next"
      @current-change="loadData"
      @size-change="loadData"
    />

    <el-dialog v-model="dialogVisible" title="Import Form" width="400">
      <el-form v-model="formData" label-width="80px">
        <el-form-item label="Name">
          <el-input v-model="formData.name" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancel</el-button>
        <el-button type="primary" @click="dialogSubmit">Submit</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getImportList } from '@/api/import'

// Reactive state
const list = ref([])
const total = ref(0)
const loading = ref(true)
const searchForm = reactive({ name: '' })
const dialogVisible = ref(false)
const formData = reactive({ id: null, name: '' })
const page = ref(1)
const pageSize = ref(10)

// Load data from API
const loadData = async () => {
  loading.value = true
  try {
    const params = {
      ...searchForm,
      page: page.value,
      pageSize: pageSize.value
    }
    const { data, total: totalCount } = await getImportList(params)
    list.value = data
    total.value = totalCount
  } finally {
    loading.value = false
  }
}

// Handle search
const handleSearch = () => {
  page.value = 1
  loadData()
}

// Handle add
const handleAdd = () => {
  formData.id = null
  formData.name = ''
  dialogVisible.value = true
}

// Handle edit
const handleEdit = (row) => {
  formData.id = row.id
  formData.name = row.name
  dialogVisible.value = true
}

// Handle delete
const handleDelete = (id) => {
  ElMessageBox.confirm('Are you sure to delete this item?', 'Warning', {
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel',
    type: 'warning'
  })
    .then(async () => {
      // Replace with actual delete API
      await loadData()
      ElMessage({ message: 'Deleted successfully', type: 'success' })
    })
    .catch(() => {
      ElMessage({ message: 'Delete cancelled', type: 'info' })
    })
}

// Dialog submit
const dialogSubmit = async () => {
  dialogVisible.value = false
  await loadData()
  ElMessage({ message: 'Saved successfully', type: 'success' })
}

// Initial load
onMounted(() => {
  loadData()
})
</script>

<style scoped>
.el-pagination {
  margin-top: 20px;
  text-align: right;
}
</style>