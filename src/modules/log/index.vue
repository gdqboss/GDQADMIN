<template>
  <div class="log-management">
    <el-card>
      <el-form :model="searchForm" label-width="80px" @submit.prevent="handleSearch">
        <el-row :gutter="20">
          <el-col :span="6">
            <el-form-item label="Search">
              <el-input v-model="searchForm.keyword" placeholder="Enter keyword" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-button type="primary" native-type="submit">Search</el-button>
            <el-button @click="handleSearch">Reset</el-button>
          </el-col>
        </el-row>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column type="index" label="ID" width="80" />
        <el-table-column prop="timestamp" label="Time" width="180" />
        <el-table-column prop="level" label="Level" width="100" />
        <el-table-column prop="message" label="Message" />
        <el-table-column prop="logger" label="Logger" width="150" />
        <el-table-column label="Actions" width="120">
          <template #default>
            <el-button link type="primary" @click="handleEdit">Edit</el-button>
            <el-button link type="danger" @click="handleDelete">Delete</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="searchForm.page"
        v-model:page-size="searchForm.pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @size-change="handleSearch"
        @current-change="handleSearch"
      />

      <el-button type="primary" @click="handleAdd">Add Log</el-button>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="formData.id ? 'Edit Log' : 'Add Log'">
      <el-form v-model="formData" label-width="120px">
        <el-form-item label="Level">
          <el-input v-model="formData.level" />
        </el-form-item>
        <el-form-item label="Message">
          <el-input v-model="formData.message" type="textarea" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancel</el-button>
        <el-button type="primary" @click="dialogSubmit">Submit</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { getLogList } from '@/api/log'

const list = ref([])
const total = ref(0)
const loading = ref(false)
const dialogVisible = ref(false)
const searchForm = reactive({
  page: 1,
  pageSize: 20,
  keyword: ''
})
const formData = reactive({
  id: null,
  level: '',
  message: ''
})

const loadData = async () => {
  loading.value = true
  try {
    const { data, total: totalCount } = await getLogList(searchForm)
    list.value = data
    total.value = totalCount
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  searchForm.page = 1
  loadData()
}

const handleAdd = () => {
  Object.assign(formData, {
    id: null,
    level: '',
    message: ''
  })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  Object.assign(formData, row)
  dialogVisible.value = true
}

const handleDelete = (row) => {
  // Implement delete logic
  ElMessageBox.confirm('Are you sure to delete this log?', 'Warning', {
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel',
    type: 'warning'
  }).then(() => {
    // Call delete API
    loadData()
  })
}

const dialogSubmit = () => {
  if (formData.id) {
    // Call update API
  } else {
    // Call create API
  }
  dialogVisible.value = false
  loadData()
}

// Initial load
loadData()
</script>

<style scoped>
.log-management {
  padding: 20px;
}

.el-table {
  margin: 20px 0;
}

.pagination {
  text-align: right;
}
</style>