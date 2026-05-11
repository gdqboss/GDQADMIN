<template>
  <div>
    <el-form :model="searchForm" inline>
      <el-form-item label="Search">
        <el-input v-model="searchForm.search" placeholder="Search" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="handleSearch">Search</el-button>
        <el-button type="primary" @click="handleAdd">Add</el-button>
      </el-form-item>
    </el-form>
    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="id" label="ID" />
      <el-table-column prop="name" label="Name" />
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
    />
    <el-dialog v-model="dialogVisible" title="Shift" width="500px">
      <el-form v-model="formData" label-width="100px">
        <el-form-item label="Name">
          <el-input v-model="formData.name" />
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
import { getShiftList, addShift, updateShift, deleteShift } from "@/api/shift";

const list = ref([])
const total = ref(0)
const loading = ref(false)
const searchForm = reactive({
  search: '',
  currentPage: 1,
  pageSize: 10,
})
const dialogVisible = ref(false)
const formData = reactive({
  id: 0,
  name: '',
})

const loadData = async () => {
  loading.value = true
  const res = await getShiftList(searchForm)
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
}

const handleEdit = (row) => {
  dialogVisible.value = true
  formData.id = row.id
  formData.name = row.name
}

const handleDelete = async (id) => {
  await deleteShift(id)
  loadData()
}

const dialogSubmit = async () => {
  if (formData.id === 0) {
    await addShift(formData)
  } else {
    await updateShift(formData)
  }
  dialogVisible.value = false
  loadData()
}

loadData()
</script>

<style scoped>
</style>