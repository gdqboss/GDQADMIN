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
          <el-button type="danger" @click="handleDelete(scope.row.id)">Delete</el-button>
        </template>
      </el-table-column>
    </el-table>
    <el-pagination
      v-model:currentPage="searchForm.currentPage"
      v-model:pageSize="searchForm.pageSize"
      :total="total"
      @current-change="loadData"
      @size-change="loadData"
      layout="total, sizes, prev, pager, next, jumper"
    />
    <el-dialog v-model="dialogVisible" title="Employee" width="500px">
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
import { getEmployeeList, addEmployee, updateEmployee, deleteEmployee } from "@/api/employees";

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
  const { data } = await getEmployeeList(searchForm)
  list.value = data.list
  total.value = data.total
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

const handleDelete = async (id) => {
  await deleteEmployee(id)
  loadData()
}

const dialogSubmit = async () => {
  if (formData.id === 0) {
    await addEmployee(formData)
  } else {
    await updateEmployee(formData)
  }
  dialogVisible.value = false
  loadData()
}

loadData()
</script>

<style scoped>
</style>