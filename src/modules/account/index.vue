<template>
  <div class="account-management">
    <el-card class="header-card" shadow="hover">
      <template #header>
        <div class="card-header-title">账户管理</div>
        <el-button type="primary" @click="handleAdd">添加用户</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="用户名">
          <el-input v-model="searchForm.username" placeholder="请输入用户名"></el-input>
        </el-form-item>
        <el-form-item label="手机号">
          <el-input v-model="searchForm.phone" placeholder="请输入手机号"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="never">
      <el-table 
        :data="list" 
        v-loading="loading" 
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55"></el-table-column>
        <el-table-column prop="username" label="用户名" width="180"></el-table-column>
        <el-table-column prop="phone" label="手机号" width="150"></el-table-column>
        <el-table-column prop="email" label="邮箱" width="200"></el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        class="pagination-container"
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        :total="total"
        layout="total, sizes, prev, pager, next"
        style="margin-top: 20px"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" title="账户信息管理" width="500px" :before-close="handleDialogClose">
      <el-form ref="formDataRef" :model="formData" :rules="rules" label-col-span="2" label-width="80px">
        <el-form-item label="用户名" prop="username">
          <el-input v-model="formData.username" placeholder="请输入用户名"></el-input>
        </el-form-item>
        <el-form-item label="手机号" prop="phone">
          <el-input v-model="formData.phone" placeholder="请输入手机号"></el-input>
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="formData.email" placeholder="请输入邮箱"></el-input>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="formData.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="dialogSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getAccountList, addAccount, updateAccount, deleteAccount } from '@/api/account'

const list = ref([])
const total = ref(0)
const loading = ref(false)
const searchForm = reactive({ username: '', phone: '' })
const dialogVisible = ref(false)
const formDataRef = ref(null)
const formData = reactive({ id: null, username: '', phone: '', email: '', status: 1 })
const rules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  phone: [{ required: true, message: '请输入手机号', trigger: 'blur' }]
}
const currentPage = ref(1)
const pageSize = ref(10)

const loadData = async () => {
  loading.value = true
  try {
    const res = await getAccountList({ page: currentPage.value, pageSize: pageSize.value, ...searchForm })
    list.value = res.data.items
    total.value = res.data.total
  } catch (e) {
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => { currentPage.value = 1; loadData() }
const resetSearch = () => { Object.assign(searchForm, { username: '', phone: '' }); loadData() }
const handleSizeChange = (v) => { pageSize.value = v; loadData() }
const handleCurrentChange = (v) => { currentPage.value = v; loadData() }
const handleAdd = () => { Object.assign(formData, { id: null, username: '', phone: '', email: '', status: 1 }); dialogVisible.value = true }
const handleEdit = (row) => { Object.assign(formData, row); dialogVisible.value = true }
const handleDelete = async (row) => {
  await ElMessageBox.confirm(`确认删除用户 ${row.username}？`, '警告', { type: 'warning' })
  await deleteAccount(row.id)
  ElMessage.success('删除成功')
  loadData()
}
const dialogSubmit = async () => {
  await formDataRef.value.validate()
  if (formData.id) { await updateAccount(formData); ElMessage.success('更新成功') }
  else { await addAccount(formData); ElMessage.success('添加成功') }
  dialogVisible.value = false
  loadData()
}
const handleDialogClose = () => { dialogVisible.value = false }

onMounted(() => { loadData() })
</script>

<style scoped>
.account-management { padding: 20px; }
.pagination-container { margin-top: 20px; justify-content: flex-end; }
</style>