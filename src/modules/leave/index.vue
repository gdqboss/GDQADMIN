<template>
  <div>
    <el-form :model="searchForm" inline>
      <el-form-item label="Name">
        <el-input v-model="searchForm.name" placeholder="Name" />
      </el-form-item>
      <el-form-item label="Type">
        <el-select v-model="searchForm.type" placeholder="Type">
          <el-option label="All" value="" />
          <el-option label="Annual" value="annual" />
          <el-option label="Sick" value="sick" />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="handleSearch">Search</el-button>
      </el-form-item>
      <el-form-item>
        <el-button type="success" @click="handleAdd">Add</el-button>
      </el-form-item>
    </el-form>
    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="id" label="ID" />
      <el-table-column prop="name" label="Name" />
      <el-table-column prop="type" label="Type" />
      <el-table-column label="Actions">
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
      layout="total, sizes, prev, pager, next, jumper"
    />
    <el-dialog v-model="dialogVisible" :title="formData.id ? 'Edit' : 'Add'">
      <el-form v-model="formData" label-width="80px">
        <el-form-item label="Name">
          <el-input v-model="formData.name" />
        </el-form-item>
        <el-form-item label="Type">
          <el-select v-model="formData.type">
            <el-option label="Annual" value="annual" />
            <el-option label="Sick" value="sick" />
          </el-select>
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
import { getLeaveList, addLeave, updateLeave, deleteLeave } from "@/api/leave";

const list = ref([])
const total = ref(0)
const loading = ref(false)
const searchForm = reactive({
  name: '',
  type: '',
  currentPage: 1,
  pageSize: 10,
})
const dialogVisible = ref(false)
const formData = reactive({
  id: null,
  name: '',
  type: '',
})

const loadData = async () => {
  loading.value = true
  const res = await getLeaveList(searchForm)
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
  formData.id = null
  formData.name = ''
  formData.type = ''
}

const handleEdit = (row) => {
  dialogVisible.value = true
  formData.id = row.id
  formData.name = row.name
  formData.type = row.type
}

const handleDelete = async (row) => {
  await deleteLeave(row.id)
  loadData()
}

const dialogSubmit = async () => {
  if (formData.id) {
    await updateLeave(formData)
  } else {
    await addLeave(formData)
  }
  dialogVisible.value = false
  loadData()
}

loadData()
</script>

<style scoped>
</style>