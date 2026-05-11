<template>
  <div class="qrcode-container">
    <el-card class="search-card">
      <el-form :model="searchForm" inline>
        <el-form-item label="名称">
          <el-input v-model="searchForm.name" placeholder="请输入名称" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <div class="table-header">
        <el-button type="primary" @click="handleAdd">新增二维码</el-button>
      </div>
      <el-table :data="list" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="名称" min-width="150" />
        <el-table-column prop="url" label="链接" min-width="200" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="创建时间" width="180" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" link size="small" @click="handleDelete(row)">删除</el-button>
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

    <el-dialog v-model="dialogVisible" :title="formData.id ? '编辑二维码' : '新增二维码'" width="500px">
      <el-form v-model="formData" label-width="80px">
        <el-form-item label="名称">
          <el-input v-model="formData.name" placeholder="请输入名称" />
        </el-form-item>
        <el-form-item label="链接">
          <el-input v-model="formData.url" placeholder="请输入链接" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="formData.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">禁用</el-radio>
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
import { getQrcodeList, addQrcode, updateQrcode, deleteQrcode } from "@/api/qrcode";

const list = ref([])
const total = ref(0)
const loading = ref(false)
const dialogVisible = ref(false)

const searchForm = reactive({
  name: '',
  page: 1,
  pageSize: 10
})

const formData = reactive({
  id: null,
  name: '',
  url: '',
  status: 1
})

const loadData = async () => {
  loading.value = true
  try {
    const res = await getQrcodeList({
      name: searchForm.name,
      page: searchForm.page,
      pageSize: searchForm.pageSize
    })
    list.value = res.data.list
    total.value = res.data.total
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
  formData.id = null
  formData.name = ''
  formData.url = ''
  formData.status = 1
  dialogVisible.value = true
}

const handleEdit = (row) => {
  formData.id = row.id
  formData.name = row.name
  formData.url = row.url
  formData.status = row.status
  dialogVisible.value = true
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确认删除该二维码？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await deleteQrcode(row.id)
    ElMessage.success('删除成功')
    loadData()
  } catch {
    // cancelled
  }
}

const dialogSubmit = async () => {
  try {
    if (formData.id) {
      await updateQrcode(formData.id, {
        name: formData.name,
        url: formData.url,
        status: formData.status
      })
      ElMessage.success('更新成功')
    } else {
      await addQrcode({
        name: formData.name,
        url: formData.url,
        status: formData.status
      })
      ElMessage.success('新增成功')
    }
    dialogVisible.value = false
    loadData()
  } catch {
    // error handled by api interceptor
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.qrcode-container {
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