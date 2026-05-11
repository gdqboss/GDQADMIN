<template>

  <div class="staff-management">
    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="姓名">
          <el-input v-model="searchForm.name" placeholder="请输入员工姓名"></el-input>
        </el-form-item>
        <el-form-item label="部门">
          <el-select v-model="searchForm.department" placeholder="请选择部门">
            <el-option label="技术部" value="tech"></el-option>
            <el-option label="运营部" value="ops"></el-option>
            <el-option label="管理部" value="admin"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="searchForm.status">
            <el-radio-button label="全部" value="all"></el-radio-button>
            <el-radio-button label="启用" value="active"></el-radio-button>
            <el-radio-button label="禁用" value="inactive"></el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="loadData(1, 10)">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="hover">
      <template #header>
        <span>员工列表</span>
        <el-button type="primary" @click="handleAdd">添加员工</el-button>
      </template>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getStaffList, getStaffDetail, updateStaffInfo, deleteStaff } from '@/api/staff';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({ name: '', department: '', status: '' });
const dialogVisible = ref(false);
const formData = reactive({ id: null, name: '', department: '', phone: '', status: 'active' });

// --- 数据加载与搜索 ---

const loadData = async (page = 1, pageSize = 10) => {
  loading.value = true;
  try {
    const params = { page, pageSize, ...searchForm };
    const result = await getStaffList({ ...params });
    list.value = result.data || [];
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载员工列表失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  // 重置页码到第一页进行搜索
  await loadData(1, 10);
};

// --- 表单操作与对话框管理 ---

const resetForm = () => {
  formData.id = null;
  formData.name = '';
  formData.department = '';
  formData.phone = '';
  formData.status = 'active';
};

const handleAdd = () => {
  resetForm();
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据用于编辑
  formData.id = row.id;
  formData.name = row.name;
  formData.department = row.department;
  formData.phone = row.phone;
  formData.status = row.status;
  dialogVisible.value = true;
};

const handleDialogClose = () => {
  dialogVisible.value = false;
  resetForm();
};

// --- 提交逻辑 ---

const dialogSubmit = async () => {
  if (!formData.id) {
    // 添加新员工
    try {
      await getStaffList({ name: formData.name, department: formData.department }); // 假设API有创建接口，这里用getStaffList占位或替换为createStaff
      ElMessage.success('添加成功');
      handleSearch(); // 刷新列表
      dialogVisible.value = false;
    } catch (error) {
      ElMessage.error('添加失败');
    }
  } else {
    // 编辑员工
    try {
      await updateStaffInfo({ id: formData.id, name: formData.name, department: formData.department, phone: formData.phone, status: formData.status });
      ElMessage.success('编辑成功');
      handleSearch(); // 刷新列表
      dialogVisible.value = false;
    } catch (error) {
      ElMessage.error('编辑失败');
    }
  }
};

// --- 删除逻辑 ---

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `您确定要删除员工 ${row.name} 吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteStaff({ id: row.id });
    ElMessage.success('删除成功');
    handleSearch(); // 刷新列表
  } catch (error) {
    ElMessage.error('删除失败');
  }
};

// --- 生命周期钩子 ---
onMounted(() => {
  loadData(1, 10);
});
</script>

<style scoped>
.staff-management {
  padding: 20px;
}

.search-card, .table-card {
  margin-bottom: 20px;
}

/* 确保 el-button 在对话框底部有良好的间距 */
.dialog-footer :deep(.el-button--primary) {
    background-color: var(--el-color-primary);
    border-color: var(--el-color-primary);
}
</style>