<template>
  <div>
    <el-form :model="searchForm" inline>
      <el-form-item :label="$t('suppliers.name')">
        <el-input v-model="searchForm.name" :placeholder="$t('suppliers.searchPlaceholder')" />
      </el-form-item>
      <el-form-item :label="$t('suppliers.email')">
        <el-input v-model="searchForm.email" :placeholder="$t('suppliers.email')" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="handleSearch">{{ $t('suppliers.search') }}</el-button>
      </el-form-item>
    </el-form>
    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="id" :label="$t('suppliers.id')" />
      <el-table-column prop="name" :label="$t('suppliers.name')" />
      <el-table-column prop="email" :label="$t('suppliers.email')" />
      <el-table-column :label="$t('suppliers.operations')">
        <template #default="scope">
          <el-button type="primary" @click="handleEdit(scope.row)">{{ $t('suppliers.editBtn') }}</el-button>
          <el-button type="danger" @click="handleDelete(scope.row)">{{ $t('suppliers.delete') }}</el-button>
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
    <el-dialog v-model="dialogVisible" :title="isEdit ? $t('suppliers.edit') : $t('suppliers.add')">
      <el-form v-model="formData" label-width="80px">
        <el-form-item :label="$t('suppliers.name')">
          <el-input v-model="formData.name" />
        </el-form-item>
        <el-form-item :label="$t('suppliers.email')">
          <el-input v-model="formData.email" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ $t('suppliers.cancel') }}</el-button>
        <el-button type="primary" @click="dialogSubmit">{{ $t('suppliers.submit') }}</el-button>
      </template>
    </el-dialog>
    <el-button type="primary" @click="handleAdd">{{ $t('suppliers.add') }}</el-button>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { getSupplierList, addSupplier, updateSupplier, deleteSupplier } from "@/api/suppliers";
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
    const res = await getSupplierList(searchForm)
    list.value = res.data.list
    total.value = res.data.total
  } catch (e) {
    ElMessage.error(t('suppliers.loadFailed'))
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

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(t('suppliers.confirmDelete'), t('suppliers.delete'), {
      confirmButtonText: t('suppliers.submit'),
      cancelButtonText: t('suppliers.cancel'),
      type: 'warning'
    })
    await deleteSupplier(row.id)
    ElMessage.success(t('suppliers.deleteSuccess'))
    loadData()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(t('suppliers.deleteFailed'))
    }
  }
}

const dialogSubmit = async () => {
  loading.value = true
  try {
    if (formData.id === 0) {
      await addSupplier(formData)
      ElMessage.success(t('suppliers.addSuccess'))
    } else {
      await updateSupplier(formData)
      ElMessage.success(t('suppliers.updateSuccess'))
    }
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(isEdit.value ? t('suppliers.updateFailed') : t('suppliers.addFailed'))
  } finally {
    loading.value = false
  }
}

loadData()
</script>

<style scoped>
</style>
