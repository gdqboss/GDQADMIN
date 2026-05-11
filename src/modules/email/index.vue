<template>
  <div>
    <el-button type="primary" @click="handleAdd">Add</el-button>
    <el-table :data="list" v-loading="loading" border style="margin-top: 20px">
      <el-table-column prop="email" label="Email" />
      <el-table-column prop="status" label="Status" />
      <el-table-column label="Actions" width="150">
        <template #default="scope">
          <el-button @click="handleEdit(scope.row)" type="text">Edit</el-button>
          <el-button @click="handleDelete(scope.row)" type="text" style="color: #f56c6c">Delete</el-button>
        </template>
      </el-table-column>
    </el-table>
    <el-pagination
      layout="prev, pager, next"
      :total="total"
      :page-size="searchForm.pageSize"
      :current-page="searchForm.page"
      @current-change="handlePageChange"
      style="margin-top: 20px; text-align: right"
    />
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="400">
      <el-form :model="formData" label-width="120px">
        <el-form-item label="Email">
          <el-input v-model="formData.email" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancel</el-button>
        <el-button type="primary" @click="dialogSubmit">Confirm</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessageBox, ElMessage } from 'element-plus'
import { getEmailList } from '@/api/email'

const list = ref([])
const total = ref(0)
const loading = ref(false)
const searchForm = reactive({ page: 1, pageSize: 10 })
const dialogVisible = ref(false)
const dialogTitle = ref('')
const formData = reactive({ id: null, email: '' })

const loadData = async () => {
  loading.value = true
  try {
    const res = await getEmailList(searchForm)
    list.value = res.data.items
    total.value = res.data.total
  } catch (error) {
    console.error('Failed to fetch email list:', error)
  } finally {
    loading.value = false
  }
}

const handlePageChange = (page) => {
  searchForm.page = page
  loadData()
}

const handleSearch = () => {
  searchForm.page = 1
  loadData()
}

const handleAdd = () => {
  dialogTitle.value = 'Add Email'
  formData.id = null
  formData.email = ''
  dialogVisible.value = true
}

const handleEdit = (row) => {
  dialogTitle.value = 'Edit Email'
  Object.assign(formData, row)
  dialogVisible.value = true
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('Are you sure you want to delete this email?', 'Warning', {
      confirmButtonText: 'OK',
      cancelButtonText: 'Cancel',
      type: 'warning'
    })
    await deleteEmail(row.id)
    ElMessage.success('Deleted successfully')
    loadData()
  } catch (error) {
    if (error !== 'cancel') console.error('Delete failed:', error)
  }
}

const dialogSubmit = async () => {
  if (formData.id) {
    await updateEmail(formData)
  } else {
    await addEmail(formData)
  }
  dialogVisible.value = false
  loadData()
}

onMounted(() => {
  loadData()
})
</script>