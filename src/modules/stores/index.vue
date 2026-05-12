<template>
  <div class="store-index">
    <el-card class="search-card">
      <el-form :model="searchForm" inline>
        <el-form-item :label="$t('stores.storeName')">
          <el-input v-model="searchForm.name" :placeholder="$t('stores.searchPlaceholder')" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">{{ $t('stores.search') }}</el-button>
          <el-button @click="resetSearch">{{ $t('stores.reset') }}</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <div class="table-header">
        <el-button type="primary" @click="handleAdd">{{ $t('stores.add') }}</el-button>
      </div>
      <el-table :data="list" v-loading="loading" stripe border style="width: 100%">
        <el-table-column prop="id" :label="$t('stores.id')" width="80" />
        <el-table-column prop="name" :label="$t('stores.name')" />
        <el-table-column prop="address" :label="$t('stores.address')" />
        <el-table-column prop="phone" :label="$t('stores.phone')" width="150" />
        <el-table-column :label="$t('stores.operations')" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleEdit(row)">{{ $t('stores.editBtn') }}</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row.id)">{{ $t('stores.delete') }}</el-button>
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

    <el-dialog v-model="dialogVisible" :title="isEdit ? $t('stores.edit') : $t('stores.add')" width="500px">
      <el-form v-model="formData" label-width="100px">
        <el-form-item :label="$t('stores.name')" required>
          <el-input v-model="formData.name" :placeholder="$t('stores.storeName')" />
        </el-form-item>
        <el-form-item :label="$t('stores.address')">
          <el-input v-model="formData.address" :placeholder="$t('stores.address')" />
        </el-form-item>
        <el-form-item :label="$t('stores.phone')">
          <el-input v-model="formData.phone" :placeholder="$t('stores.phone')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ $t('stores.cancel') }}</el-button>
        <el-button type="primary" @click="dialogSubmit" :loading="loading">{{ $t('stores.submit') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getStoreList, addStore, updateStore, deleteStore } from "@/api/stores";
import { ElMessage, ElMessageBox } from 'element-plus'
import i18n from '@/i18n'

const { t } = i18n.global

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
    ElMessage.error(t('stores.loadFailed'))
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
    await ElMessageBox.confirm(t('stores.confirmDelete'), t('stores.delete'), {
      confirmButtonText: t('stores.submit'),
      cancelButtonText: t('stores.cancel'),
      type: 'warning'
    })
    await deleteStore(id)
    ElMessage.success(t('stores.deleteSuccess'))
    loadData()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(t('stores.deleteFailed'))
    }
  }
}

const dialogSubmit = async () => {
  if (!formData.name) {
    ElMessage.warning(t('stores.storeNameRequired'))
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
      ElMessage.success(t('stores.updateSuccess'))
    } else {
      await addStore({
        name: formData.name,
        address: formData.address,
        phone: formData.phone
      })
      ElMessage.success(t('stores.addSuccess'))
    }
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(isEdit.value ? t('stores.updateFailed') : t('stores.addFailed'))
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
