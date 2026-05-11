<template>
  <div class="member-container">
    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" :model="searchForm" ref="searchFormRef">
        <el-form-item label="姓名">
          <el-input v-model="searchForm.name" placeholder="请输入姓名"></el-input>
        </el-form-item>
        <el-form-item label="电话">
          <el-input v-model="searchForm.phone" placeholder="请输入手机号"></el-input>
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
        <el-table-column prop="name" label="姓名" width="180"></el-table-column>
        <el-table-column prop="phone" label="电话" width="200"></el-table-column>
        <el-table-column prop="email" label="邮箱"></el-table-column>
        <el-table-column prop="level" label="级别" width="120"></el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'">
              {{ row.status === 'active' ? '启用' : '禁用' }}
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

    <!-- 添加/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="isEditing ? '编辑成员' : '添加成员'" width="500px">
      <el-form :model="formData" ref="dialogFormRef" label-width="80px">
        <el-form-item label="姓名" prop="name">
          <el-input v-model="formData.name" placeholder="请输入姓名"></el-input>
        </el-form-item>
        <el-form-item label="电话" prop="phone">
          <el-input v-model="formData.phone" placeholder="请输入手机号"></el-input>
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="formData.email" placeholder="请输入邮箱"></el-input>
        </el-form-item>
        <el-form-item label="级别" prop="level">
          <el-select v-model="formData.level" placeholder="请选择级别" style="width: 100%;">
            <el-option label="普通会员" value="basic"></el-option>
            <el-option label="高级会员" value="premium"></el-option>
            <el-option label="VIP会员" value="vip"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="formData.status" placeholder="请选择状态" style="width: 100%;">
            <el-option label="启用" value="active"></el-option>
            <el-option label="禁用" value="inactive"></el-option>
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
import { getMemberList, getMemberDetail, updateMember, deleteMember } from '@/api/member';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  phone: ''
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
  name: '',
  phone: '',
  email: '',
  level: 'basic',
  status: 'active'
});

const loadData = async (page = 1, pageSize = 10) => {
  loading.value = true;
  try {
    pagination.currentPage = page;
    pagination.pageSize = pageSize;
    const res = await getMemberList({
      page,
      pageSize,
      name: searchForm.name,
      phone: searchForm.phone
    });
    list.value = res.data.list || res.data || [];
    total.value = res.data.total || 0;
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
  searchForm.name = '';
  searchForm.phone = '';
  loadData(1, pagination.pageSize);
};

const handleAdd = () => {
  isEditing.value = false;
  Object.assign(formData, { id: null, name: '', phone: '', email: '', level: 'basic', status: 'active' });
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  isEditing.value = true;
  Object.assign(formData, { id: row.id, name: row.name, phone: row.phone, email: row.email, level: row.level, status: row.status });
  dialogVisible.value = true;
};

const handleSave = async () => {
  try {
    if (isEditing.value) {
      await updateMember(formData.id, formData);
      ElMessage.success('更新成功');
    } else {
      await getMemberList(formData);
      ElMessage.success('添加成功');
    }
    dialogVisible.value = false;
    loadData(pagination.currentPage, pagination.pageSize);
  } catch (e) {
    ElMessage.error(isEditing.value ? '更新失败' : '添加失败');
  }
};

const handleDelete = (id) => {
  ElMessageBox.confirm('确定删除该成员吗？', '提示', { type: 'warning' })
    .then(async () => {
      try {
        await deleteMember(id);
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
.member-container {
  padding: 20px;
}
.search-card {
  margin-bottom: 20px;
}
</style>
