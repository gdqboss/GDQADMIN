<template>
  <div class="tools-container">
    <el-card class="search-card">
      <el-form :model="searchForm" inline>
        <el-form-item label="Tool Name">
          <el-input v-model="searchForm.name" placeholder="Search by name" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">Search</el-button>
          <el-button @click="handleReset">Reset</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <div class="table-header">
        <el-button type="primary" @click="handleAdd">Add Tool</el-button>
      </div>
      <el-table :data="list" v-loading="loading" stripe border style="width: 100%">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="Name" min-width="150" />
        <el-table-column prop="description" label="Description" min-width="200" show-overflow-tooltip />
        <el-table-column prop="status" label="Status" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Actions" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleEdit(row)">Edit</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">Delete</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="searchForm.page"
          v-model:page-size="searchForm.pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadData"
          @current-change="loadData"
        />
      </div>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEdit ? 'Edit Tool' : 'Add Tool'" width="500px">
      <el-form v-model="formData" label-width="100px">
        <el-form-item label="Name" required>
          <el-input v-model="formData.name" placeholder="Enter tool name" />
        </el-form-item>
        <el-form-item label="Description">
          <el-input v-model="formData.description" type="textarea" :rows="3" placeholder="Enter description" />
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
        <el-button type="primary" @click="dialogSubmit" :loading="loading">Confirm</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getToolList, addTool, updateTool, deleteTool } from "@/api/tools";
import { ElMessage, ElMessageBox } from 'element-plus'

const list = ref([])
const total = ref(0)
const loading = ref(false)
const searchForm = reactive({
  name: '',
  page: 1,
  pageSize: 10
})
const dialogVisible = ref(false)
const formData = reactive({
  id: null,
  name: '',
  description: '',
  status: 'active'
})
const isEdit = ref(false)

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: searchForm.page,
      pageSize: searchForm.pageSize
    }
    if (searchForm.name) params.name = searchForm.name
    const res = await getToolList(params)
    list.value = res.data.list
    total.value = res.data.total
  } catch (error) {
    ElMessage.error('Failed to load data')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  searchForm.page = 1
  loadData()
}

const handleReset = () => {
  searchForm.name = ''
  searchForm.page = 1
  loadData()
}

const handleAdd = () => {
  isEdit.value = false
  formData.id = null
  formData.name = ''
  formData.description = ''
  formData.status = 'active'
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  formData.id = row.id
  formData.name = row.name
  formData.description = row.description
  formData.status = row.status
  dialogVisible.value = true
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('Are you sure you want to delete this tool?', 'Confirmation', {
      confirmButtonText: 'Confirm',
      cancelButtonText: 'Cancel',
      type: 'warning'
    })
    loading.value = true
    await deleteTool(row.id)
    ElMessage.success('Deleted successfully')
    loadData()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('Failed to delete')
    }
  } finally {
    loading.value = false
  }
}

const dialogSubmit = async () => {
  if (!formData.name) {
    ElMessage.warning('Please enter tool name')
    return
  }
  loading.value = true
  try {
    if (isEdit.value) {
      await updateTool(formData.id, {
        name: formData.name,
        description: formData.description,
        status: formData.status
      })
      ElMessage.success('Updated successfully')
    } else {
      await addTool({
        name: formData.name,
        description: formData.description,
        status: formData.status
      })
      ElMessage.success('Added successfully')
    }
    dialogVisible.value = false
    loadData()
  } catch (error) {
    ElMessage.error('Operation failed')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.tools-container {
  padding: 20px;
}

.search-card {
  margin-bottom: 20px;
}

.table-card {
  margin-bottom: 20px;
}

.table-header {
  margin-bottom: 16px;
}

.pagination-wrapper {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>