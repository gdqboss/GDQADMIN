<template>
  <div class="dealers-container">
    <el-card class="search-card">
      <el-form :model="searchForm" inline>
        <el-form-item label="经销商名称">
          <el-input v-model="searchForm.name" placeholder="请输入" clearable />
        </el-form-item>
        <el-form-item label="联系人">
          <el-input v-model="searchForm.contact" placeholder="请输入" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <div class="table-header">
        <el-button type="primary" @click="handleAdd">新增经销商</el-button>
      </div>
      <el-table :data="list" v-loading="loading" stripe border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="经销商名称" min-width="150" />
        <el-table-column prop="contact" label="联系人" width="120" />
        <el-table-column prop="phone" label="联系电话" width="140" />
        <el-table-column prop="address" label="地址" min-width="200" show-overflow-tooltip />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
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

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑经销商' : '新增经销商'" width="600px">
      <el-form v-model="formData" label-width="100px">
        <el-form-item label="经销商名称" required>
          <el-input v-model="formData.name" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="联系人">
          <el-input v-model="formData.contact" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="formData.phone" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="地址">
          <el-input v-model="formData.address" type="textarea" :rows="3" placeholder="请输入" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="dialogSubmit" :loading="loading">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getDealerList, addDealer, updateDealer, deleteDealer } from "@/api/dealers";

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
    ElMessage.error('获取经销商列表失败')
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
  ElMessageBox.confirm('确定删除该经销商吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      await deleteDealer(row.id)
      ElMessage.success('删除成功')
      loadData()
    } catch (e) {
      ElMessage.error('删除失败')
    }
  }).catch(() => {})
}

const dialogSubmit = async () => {
  if (!formData.name) {
    ElMessage.warning('请输入经销商名称')
    return
  }
  loading.value = true
  try {
    if (isEdit.value) {
      await updateDealer(formData)
      ElMessage.success('更新成功')
    } else {
      await addDealer(formData)
      ElMessage.success('新增成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(isEdit.value ? '更新失败' : '新增失败')
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