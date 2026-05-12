<template>
  <div>
    <el-form :model="searchForm" inline>
      <el-form-item :label="$t('employees.name')">
        <el-input v-model="searchForm.name" :placeholder="$t('employees.searchPlaceholder')" />
      </el-form-item>
      <el-form-item :label="$t('employees.email')">
        <el-input v-model="searchForm.email" :placeholder="$t('employees.email')" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="handleSearch">{{ $t('employees.search') }}</el-button>
      </el-form-item>
    </el-form>
    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="id" :label="$t('employees.id')" />
      <el-table-column prop="name" :label="$t('employees.name')" />
      <el-table-column prop="email" :label="$t('employees.email')" />
      <el-table-column :label="$t('employees.operations')">
        <template #default="scope">
          <el-button type="primary" @click="handleEdit(scope.row)">{{ $t('employees.editBtn') }}</el-button>
          <el-button type="danger" @click="handleDelete(scope.row.id)">{{ $t('employees.delete') }}</el-button>
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
    <el-dialog v-model="dialogVisible" :title="isEdit ? $t('employees.edit') : $t('employees.add')" width="500px">
      <el-form v-model="formData" label-width="80px">
        <el-form-item :label="$t('employees.name')">
          <el-input v-model="formData.name" />
        </el-form-item>
        <el-form-item :label="$t('employees.email')">
          <el-input v-model="formData.email" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ $t('employees.cancel') }}</el-button>
        <el-button type="primary" @click="dialogSubmit">{{ $t('employees.submit') }}</el-button>
      </template>
    </el-dialog>
    <el-button type="primary" @click="handleAdd">{{ $t('employees.add') }}</el-button>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { getEmployeeList, addEmployee, updateEmployee, deleteEmployee } from "@/api/employees";
import { ElMessage, ElMessageBox } from 'element-plus'
import i18n from '@/i18n'

const { t } = i18n.global

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
const isEdit = ref(false)
const formData = reactive({
  id: 0,
  name: '',
  email: '',
})

const loadData = async () => {
  loading.value = true
  try {
    const { data } = await getEmployeeList(searchForm)
    list.value = data.list
    total.value = data.total
  } catch (e) {
    ElMessage.error(t('employees.loadFailed'))
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  searchForm.currentPage = 1
  loadData()
}

const handleAdd = () => {
  isEdit.value = false
  dialogVisible.value = true
  formData.id = 0
  formData.name = ''
  formData.email = ''
}

const handleEdit = (row) => {
  isEdit.value = true
  dialogVisible.value = true
  formData.id = row.id
  formData.name = row.name
  formData.email = row.email
}

const handleDelete = async (id) => {
  try {
    await ElMessageBox.confirm(t('employees.confirmDelete'), t('employees.delete'), {
      confirmButtonText: t('employees.submit'),
      cancelButtonText: t('employees.cancel'),
      type: 'warning'
    })
    await deleteEmployee(id)
    ElMessage.success(t('employees.deleteSuccess'))
    loadData()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(t('employees.deleteFailed'))
    }
  }
}

const dialogSubmit = async () => {
  loading.value = true
  try {
    if (formData.id === 0) {
      await addEmployee(formData)
      ElMessage.success(t('employees.addSuccess'))
    } else {
      await updateEmployee(formData)
      ElMessage.success(t('employees.updateSuccess'))
    }
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(isEdit.value ? t('employees.updateFailed') : t('employees.addFailed'))
  } finally {
    loading.value = false
  }
}

loadData()
</script>

<style scoped>
</style>
