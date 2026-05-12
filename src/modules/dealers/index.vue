<template>
  <div class="dealers-container">
    <el-card class="search-card">
      <el-form :model="searchForm" inline>
        <el-form-item :label="$t('dealers.dealerName')">
          <el-input v-model="searchForm.name" :placeholder="$t('dealers.pleaseInputDealerName')" clearable />
        </el-form-item>
        <el-form-item :label="$t('dealers.contact')">
          <el-input v-model="searchForm.contact" :placeholder="$t('dealers.contact')" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">{{ $t('dealers.search') }}</el-button>
          <el-button @click="resetSearch">{{ $t('dealers.reset') }}</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <div class="table-header">
        <el-button type="primary" @click="handleAdd">{{ $t('dealers.add') }}</el-button>
      </div>
      <el-table :data="list" v-loading="loading" stripe border>
        <el-table-column prop="id" :label="$t('dealers.id')" width="80" />
        <el-table-column prop="name" :label="$t('dealers.dealerName')" min-width="150" />
        <el-table-column prop="contact" :label="$t('dealers.contact')" width="120" />
        <el-table-column prop="phone" :label="$t('dealers.phone')" width="140" />
        <el-table-column prop="address" :label="$t('dealers.address')" min-width="200" show-overflow-tooltip />
        <el-table-column :label="$t('dealers.operations')" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row)">{{ $t('dealers.editBtn') }}</el-button>
            <el-button type="danger" link @click="handleDelete(row)">{{ $t('dealers.delete') }}</el-button>
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

    <el-dialog v-model="dialogVisible" :title="isEdit ? $t('dealers.edit') : $t('dealers.add')" width="600px">
      <el-form v-model="formData" label-width="100px">
        <el-form-item :label="$t('dealers.dealerName')" required>
          <el-input v-model="formData.name" :placeholder="$t('dealers.pleaseInputDealerName')" />
        </el-form-item>
        <el-form-item :label="$t('dealers.contact')">
          <el-input v-model="formData.contact" :placeholder="$t('dealers.contact')" />
        </el-form-item>
        <el-form-item :label="$t('dealers.phone')">
          <el-input v-model="formData.phone" :placeholder="$t('dealers.phone')" />
        </el-form-item>
        <el-form-item :label="$t('dealers.address')">
          <el-input v-model="formData.address" type="textarea" :rows="3" :placeholder="$t('dealers.address')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ $t('dealers.cancel') }}</el-button>
        <el-button type="primary" @click="dialogSubmit" :loading="loading">{{ $t('dealers.submit') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getDealerList, addDealer, updateDealer, deleteDealer } from "@/api/dealers";
import i18n from '@/i18n'

const { t } = i18n.global

const list = ref([])
const total = ref(0)
const loading = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)

const searchForm = reactive({
  name: '',
  contact: '',
  page: 1,
  pageSize: 10
})

const formData = reactive({
  id: null,
  name: '',
  contact: '',
  phone: '',
  address: ''
})

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: searchForm.page,
      pageSize: searchForm.pageSize
    }
    if (searchForm.name) params.name = searchForm.name
    if (searchForm.contact) params.contact = searchForm.contact
    const res = await getDealerList(params)
    list.value = res.data.list
    total.value = res.data.total
  } catch (e) {
    ElMessage.error(t('dealers.loadFailed'))
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
  searchForm.contact = ''
  handleSearch()
}

const handleAdd = () => {
  isEdit.value = false
  formData.id = null
  formData.name = ''
  formData.contact = ''
  formData.phone = ''
  formData.address = ''
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  formData.id = row.id
  formData.name = row.name
  formData.contact = row.contact
  formData.phone = row.phone
  formData.address = row.address
  dialogVisible.value = true
}

const handleDelete = (row) => {
  ElMessageBox.confirm(t('dealers.confirmDelete'), t('dealers.delete'), {
    confirmButtonText: t('dealers.submit'),
    cancelButtonText: t('dealers.cancel'),
    type: 'warning'
  }).then(async () => {
    try {
      await deleteDealer(row.id)
      ElMessage.success(t('dealers.deleteSuccess'))
      loadData()
    } catch (e) {
      ElMessage.error(t('dealers.deleteFailed'))
    }
  }).catch(() => {})
}

const dialogSubmit = async () => {
  if (!formData.name) {
    ElMessage.warning(t('dealers.pleaseInputDealerName'))
    return
  }
  loading.value = true
  try {
    if (isEdit.value) {
      await updateDealer(formData)
      ElMessage.success(t('dealers.updateSuccess'))
    } else {
      await addDealer(formData)
      ElMessage.success(t('dealers.addSuccess'))
    }
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(isEdit.value ? t('dealers.updateFailed') : t('dealers.addFailed'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.dealers-container {
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
