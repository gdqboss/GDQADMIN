<template>
  <div class="image-container">
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
        <el-button type="primary" @click="handleAdd">新增图片</el-button>
      </div>
      <el-table :data="list" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="名称" min-width="150" />
        <el-table-column prop="url" label="URL" min-width="200" show-overflow-tooltip />
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

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑图片' : '新增图片'" width="500px">
      <el-form v-model="formData" label-width="80px">
        <el-form-item label="名称" required>
          <el-input v-model="formData.name" placeholder="请输入名称" />
        </el-form-item>
        <el-form-item label="URL" required>
          <el-input v-model="formData.url" placeholder="请输入URL" />
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
import { getImageList, addImage, updateImage, deleteImage } from "@/api/image";

const list = ref([])
const total = ref(0)
const loading = ref(false)
const searchForm = reactive({
  page: 1,
  pageSize: 10,
  name: ''
})
const dialogVisible = ref(false)
const formData = reactive({
  id: null,
  name: '',
  url: ''
})
const isEdit = ref(false)

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: searchForm.page,
      pageSize: searchForm.pageSize
    }
    if (searchForm.name) {
      params.name = searchForm.name
    }
    const res = await getImageList(params)
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
  isEdit.value = false
  formData.id = null
  formData.name = ''
  formData.url = ''
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  formData.id = row.id
  formData.name = row.name
  formData.url = row.url
  dialogVisible.value = true
}

const handleDelete = async (row) => {
  try {
    await deleteImage(row.id)
    loadData()
  } catch (e) {
    console.error(e)
  }
}

const dialogSubmit = async () => {
  loading.value = true
  try {
    if (isEdit.value) {
      await updateImage(formData.id, { name: formData.name, url: formData.url })
    } else {
      await addImage({ name: formData.name, url: formData.url })
    }
    dialogVisible.value = false
    loadData()
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.image-container {
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