<template>
  <div class="notice-container">
    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="标题">
          <el-input v-model="searchForm.title" placeholder="请输入标题"></el-input>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态" clearable style="width: 150px;">
            <el-option label="草稿" value="draft"></el-option>
            <el-option label="已发布" value="published"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="hover">
      <el-table :data="list" v-loading="loading" border>
        <el-table-column prop="id" label="ID" width="80"></el-table-column>
        <el-table-column prop="title" label="标题"></el-table-column>
        <el-table-column prop="type" label="类型" width="120"></el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'published' ? 'success' : 'info'">
              {{ row.status === 'published' ? '已发布' : '草稿' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="创建时间" width="180"></el-table-column>
        <el-table-column label="操作" width="200" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        @size-change="loadData"
        @current-change="loadData"
        :current-page="pagination.currentPage"
        :page-size="pagination.pageSize"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next, jumper"
        :total="total"
        style="margin-top: 20px; justify-content: flex-end;"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEditing ? '编辑公告' : '添加公告'" width="500px">
      <el-form :model="formData" ref="dialogFormRef" label-width="80px">
        <el-form-item label="标题" prop="title">
          <el-input v-model="formData.title" placeholder="请输入标题"></el-input>
        </el-form-item>
        <el-form-item label="内容" prop="content">
          <el-input v-model="formData.content" type="textarea" :rows="4" placeholder="请输入内容"></el-input>
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择类型" style="width: 100%;">
            <el-option label="系统公告" value="system"></el-option>
            <el-option label="活动公告" value="activity"></el-option>
            <el-option label="其他公告" value="other"></el-option>
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSave">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  title: '',
  status: ''
});
const pagination = reactive({
  currentPage: 1,
  pageSize: 10
});
const dialogVisible = ref(false);
const dialogFormRef = ref(null);
const isEditing = ref(false);
const formData = reactive({
  id: null,
  title: '',
  content: '',
  type: 'system'
});

const loadData = async (page = 1, pageSize = 10) => {
  loading.value = true;
  try {
    pagination.currentPage = page;
    pagination.pageSize = pageSize;
    // Simulated data
    list.value = [];
    total.value = 0;
  } catch (e) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  loadData(1, pagination.pageSize);
};

const resetSearch = () => {
  searchForm.title = '';
  searchForm.status = '';
  loadData(1, pagination.pageSize);
};

const handleAdd = () => {
  isEditing.value = false;
  Object.assign(formData, { id: null, title: '', content: '', type: 'system' });
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  isEditing.value = true;
  Object.assign(formData, { id: row.id, title: row.title, content: row.content, type: row.type });
  dialogVisible.value = true;
};

const handleSave = async () => {
  try {
    ElMessage.success(isEditing.value ? '更新成功' : '添加成功');
    dialogVisible.value = false;
    loadData(pagination.currentPage, pagination.pageSize);
  } catch (e) {
    ElMessage.error(isEditing.value ? '更新失败' : '添加失败');
  }
};

const handleDelete = (id) => {
  ElMessageBox.confirm('确定删除该公告吗？', '提示', { type: 'warning' })
    .then(async () => {
      try {
        ElMessage.success('删除成功');
        loadData(pagination.currentPage, pagination.pageSize);
      } catch (e) {
        ElMessage.error('删除失败');
      }
    })
    .catch(() => {});
};

onMounted(() => {
  loadData();
});
</script>

<style scoped>
.notice-container {
  padding: 20px;
}
.search-card {
  margin-bottom: 20px;
}
</style>
