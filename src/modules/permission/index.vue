<template>
  <div class="permission-container">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header-title">权限管理</div>
        <el-button type="primary" @click="handleAdd">新增权限</el-button>
      </template>
    </el-card>

    <!-- Search Form -->
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="搜索名称/标识" clearable style="width:200px" />
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="searchForm.category" placeholder="选择分类" clearable style="width:150px">
            <el-option v-for="c in categories" :key="c.category" :label="c.category" :value="c.category" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Table -->
    <el-card class="table-card" shadow="never">
      <el-table :data="list" v-loading="loading" stripe border>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="name" label="权限标识" min-width="180" />
        <el-table-column prop="label" label="显示名" min-width="120" />
        <el-table-column prop="category" label="分类" width="120">
          <template #default="{ row }">
            <el-tag type="info">{{ row.category }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- Pagination -->
      <el-pagination
        @size-change="handleSizeChange"
        @current-page-change="handleCurrentPageChange"
        :current-page="pagination.page"
        :page-sizes="[20, 50, 100]"
        :page-size="pagination.pageSize"
        layout="total, sizes, prev, pager, next"
        :total="pagination.total"
      />
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px">
      <el-form ref="formRef" :model="form" label-width="80px">
        <el-form-item label="权限标识" prop="name" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="form.name" placeholder="如: product:read" :disabled="!!form.id" />
        </el-form-item>
        <el-form-item label="显示名" prop="label" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="form.label" placeholder="如: 查看商品" />
        </el-form-item>
        <el-form-item label="分类" prop="category">
          <el-select v-model="form.category" style="width:100%">
            <el-option v-for="c in categories" :key="c.category" :label="c.category" :value="c.category" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" @click="handleSubmit">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getPermissionList, getPermissionCategories, createPermission, updatePermission, deletePermission } from '@/api/rbac/permissions'

const list = ref([])
const categories = ref([])
const loading = ref(false)
const searchForm = reactive({ keyword: '', category: '' })
const pagination = reactive({ page: 1, pageSize: 100, total: 0 })
const dialogVisible = ref(false)
const dialogTitle = ref('')
const formRef = ref()
const form = reactive({ id: null, name: '', label: '', category: 'other', description: '' })

async function loadData() {
  loading.value = true
  try {
    const res = await getPermissionList({ ...searchForm, page: pagination.page, pageSize: pagination.pageSize })
    list.value = res.data.list
    pagination.total = res.data.total
  } catch (error) {
    ElMessage.error('加载数据失败: ' + error.message)
  } finally {
    loading.value = false
  }
}

async function loadCategories() {
  try {
    const res = await getPermissionCategories()
    categories.value = res.data
  } catch (error) {
    ElMessage.error('加载分类失败: ' + error.message)
  }
}

function handleSearch() {
  pagination.page = 1
  loadData()
}

function handleReset() {
  searchForm.keyword = ''
  searchForm.category = ''
  pagination.page = 1
  loadData()
}

function handleSizeChange(val) {
  pagination.pageSize = val
  loadData()
}

function handleCurrentPageChange(val) {
  pagination.page = val
  loadData()
}

function handleAdd() {
  Object.assign(form, { id: null, name: '', label: '', category: 'other', description: '' })
  dialogTitle.value = '新增权限'
  dialogVisible.value = true
}

function handleEdit(row) {
  Object.assign(form, { id: row.id, name: row.name, label: row.label, category: row.category, description: row.description })
  dialogTitle.value = '编辑权限'
  dialogVisible.value = true
}

async function handleSubmit() {
  try {
    await formRef.value.validate()
    if (form.id) {
      await updatePermission(form.id, { label: form.label, category: form.category, description: form.description })
      ElMessage.success('编辑成功')
    } else {
      await createPermission(form)
      ElMessage.success('新增成功')
    }
    dialogVisible.value = false
    loadData()
    loadCategories()
  } catch (e) {
    // 验证失败
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定删除权限「${row.label}」？`, '确认', { type: 'warning' })
    await deletePermission(row.id)
    ElMessage.success('删除成功')
    loadData()
    loadCategories()
  } catch (e) {
    // 用户取消
  }
}

onMounted(() => { loadData(); loadCategories() })
</script>

<style scoped>
.permission-container {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.search-card {
  margin-bottom: 20px;
}

.card-header-title {
  font-size: 16px;
  font-weight: 600;
}
</style>
