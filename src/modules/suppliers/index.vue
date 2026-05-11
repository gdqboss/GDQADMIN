<template>
  <div>
    <el-form :model="searchForm" inline>
      <el-form-item label="Name">
        <el-input v-model="searchForm.name" placeholder="Name" />
      </el-form-item>
      <el-form-item label="Email">
        <el-input v-model="searchForm.email" placeholder="Email" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="handleSearch">Search</el-button>
      </el-form-item>
    </el-form>
    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="id" label="ID" />
      <el-table-column prop="name" label="Name" />
      <el-table-column prop="email" label="Email" />
      <el-table-column label="Operations">
        <template #default="scope">
          <el-button type="primary" @click="handleEdit(scope.row)">Edit</el-button>
          <el-button type="danger" @click="handleDelete(scope.row)">Delete</el-button>
        </template>
      </el-table-column>
    </el-table>
    <el-pagination
      v-model:currentPage="searchForm.currentPage"
      v-model:page-size="searchForm.pageSize"
      :total="total"
      @current-change="loadData"
      @size-change="loadData"
    />
    <el-dialog v-model="dialogVisible" title="Supplier Form">
      <el-form v-model="formData" label-width="80px">
        <el-form-item label="Name">
          <el-input v-model="formData.name" />
        </el-form-item>
        <el-form-item label="Email">
          <el-input v-model="formData.email" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancel</el-button>
        <el-button type="primary" @click="dialogSubmit">Submit</el-button>
      </template>
    </el-dialog>
    <el-button type="primary" @click="handleAdd">Add</el-button>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { getSupplierList, addSupplier, updateSupplier, deleteSupplier } from "@/api/suppliers";

const list = ref([])
const total = ref(0)
const loading = ref(false)
const searchForm = reactive({
  name: '',
  email: '',
  currentPage: 1,
  pageSize: 10,
})
const dialogVisible = ref(false)
const formData = reactive({
  id: 0,
  name: '',
  email: '',
})

const loadData = async () => {
  loading.value = true
  const res = await getSupplierList(searchForm)
  list.value = res.data.list
  total.value = res.data.total
  loading.value = false
}

const handleSearch = () => {
  searchForm.currentPage = 1
  loadData()
}

const handleAdd = () => {
  dialogVisible.value = true
  formData.id = 0
  formData.name = ''
  formData.email = ''
}

const handleEdit = (row) => {
  dialogVisible.value = true
  formData.id = row.id
  formData.name = row.name
  formData.email = row.email
}

const handleDelete = async (row) => {
  await deleteSupplier(row.id)
  loadData()
}

const dialogSubmit = async () => {
  if (formData.id === 0) {
    await addSupplier(formData)
  } else {
    await updateSupplier(formData)
  }
  dialogVisible.value = false
  loadData()
}

loadData()
</script>

<style scoped>
</style>