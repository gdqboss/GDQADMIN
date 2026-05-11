<template>
  <div class="store-index">
    <el-card class="search-card">
      <el-form :model="searchForm" inline>
        <el-form-item label="Store Name">
          <el-input v-model="searchForm.name" placeholder="Search store name" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">Search</el-button>
          <el-button @click="resetSearch">Reset</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <div class="table-header">
        <el-button type="primary" @click="handleAdd">Add Store</el-button>
      </div>
      <el-table :data="list" v-loading="loading" stripe border style="width: 100%">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="Name" />
        <el-table-column prop="address" label="Address" />
        <el-table-column prop="phone" label="Phone" width="150" />
        <el-table-column label="Actions" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleEdit(row)">Edit</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row.id)">Delete</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="searchForm.page"
          v-model:page-size="searchForm.pageSize"
          :total="total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadData"
          @current-change="loadData"
        />
      </div>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEdit ? 'Edit Store' : 'Add Store'" width="500px">
      <el-form v-model="formData" label-width="100px">
        <el-form-item label="Name" required>
          <el-input v-model="formData.name" placeholder="Store name" />
        </el-form-item>
        <el-form-item label="Address">
          <el-input v-model="formData.address" placeholder="Store address" />
        </el-form-item>
        <el-form-item label="Phone">
          <el-input v-model="formData.phone" placeholder="Phone number" />
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
import { getStoreList, addStore, updateStore, deleteStore } from "@/api/stores";
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
  address: '',
  phone: ''
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
    const res = await getStoreList(params)
    list.value = res.data.list
    total.value = res.data.total
  } catch (e) {
    ElMessage.error('Failed to load store list')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  searchForm.page = 1
  loadData()
}

const resetSearch = () => {
  searchForm.name = ''
  searchForm.page = 1
  loadData()
}

const handleAdd = () => {
  isEdit.value = false
  formData.id = null
  formData.name = ''
  formData.address = ''
  formData.phone = ''
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  formData.id = row.id
  formData.name = row.name
  formData.address = row.address
  formData.phone = row.phone
  dialogVisible.value = true
}

const handleDelete = async (id) => {
  try {
    await ElMessageBox.confirm('Are you sure to delete this store?', 'Warning', {
      confirmButtonText: 'Confirm',
      cancelButtonText: 'Cancel',
      type: 'warning'
    })
    await deleteStore(id)
    ElMessage.success('Deleted successfully')
    loadData()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error('Delete failed')
    }
  }
}

const dialogSubmit = async () => {
  if (!formData.name) {
    ElMessage.warning('Store name is required')
    return
  }
  loading.value = true
  try {
    if (isEdit.value) {
      await updateStore(formData.id, {
        name: formData.name,
        address: formData.address,
        phone: formData.phone
      })
      ElMessage.success('Updated successfully')
    } else {
      await addStore({
        name: formData.name,
        address: formData.address,
        phone: formData.phone
      })
      ElMessage.success('Added successfully')
    }
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(isEdit.value ? 'Update failed' : 'Add failed')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.store-index {
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
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}
</style>