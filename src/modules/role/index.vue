<template>
  <div class="role-management">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header-title">角色管理</div>
        <el-button type="primary" @click="handleAdd">添加角色</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="角色名称">
          <el-input v-model="searchForm.name" placeholder="请输入角色名称"></el-input>
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="searchForm.description" placeholder="请输入描述"></el-input>
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
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="80"></el-table-column>
        <el-table-column prop="name" label="角色名称" width="180"></el-table-column>
        <el-table-column prop="description" label="描述" width="250"></el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        @size-change="handleSizeChange"
        @current-page-change="handleCurrentPageChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        layout="total, bottom"
        total="total"
      ></el-pagination>
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" 
      title="角色信息" 
      width="50%" 
      :before-close="handleDialogClose">
      <el-form 
        :model="formData" 
        ref="elFormRef" 
        :rules="rules"
        label-for="name"
      >
        <el-form-item label="角色名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入角色名称"></el-input>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" placeholder="请输入描述"></el-input>
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="$emit('cancel')">取消</el-button>
          <el-button type="primary" @click="dialogSubmit">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 文件结构为 @/api/role.js，并导出 getRoleList, createRole, updateRole, deleteRole 等函数
import { getRoleList, createRole, updateRole, deleteRole } from '@/api/role';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  description: ''
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  description: ''
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);
const searchFormRef = ref(null);
const elFormRef = ref(null);

// --- Methods ---

const loadData = async () => {
  loading.value = true;
  try {
    await fetchRoleList();
  } finally {
    loading.value = false;
  }
};

const fetchRoleList = async (page = 1, size = 10, searchParams = {}) => {
  loading.value = true;
  try {
    // API 调用示例：使用 object 参数传递所有参数
    const result = await getRoleList({ page: page, pageSize: size, ...searchParams });
    list.value = result.data || []; // 假设返回结构包含 data 数组
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载角色列表失败');
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  currentPage.value = 1; // 查询时重置页码到第一页
  await fetchRoleList(currentPage.value, pageSize.value, searchForm);
};

const resetSearch = () => {
  searchForm.name = '';
  searchForm.description = '';
  handleSearch();
};

const handleAdd = () => {
  formData.id = null; // 清空ID，表示新增
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.id = row.id;
  formData.name = row.name;
  formData.description = row.description;
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除角色 "${row.name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteRole(row.id); // API 调用删除
    ElMessage.success('删除成功');
    // 刷新列表，并保持当前页码（如果需要）
    handleSearch(); 
  } catch (error) {
    ElMessage.error('删除失败');
  }
};

const dialogSubmit = async () => {
  elFormRef.value.validate(async (valid) => {
    if (!valid) return;

    try {
      let result;
      if (formData.id) {
        // 编辑逻辑
        result = await updateRole(formData.id, formData); // API 调用更新
        ElMessage.success('编辑成功');
      } else {
        // 新增逻辑
        result = await createRole(formData); // API 调用创建
        ElMessage.success('添加成功');
      }
      dialogVisible.value = false;
      handleSearch(); // 提交成功后刷新列表
    } catch (error) {
      ElMessage.error('操作失败，请检查网络或权限。');
    }
  });
};

const handleDialogClose = () => {
  // 关闭弹窗时重置表单数据（可选）
  formData.id = null;
  formData.name = '';
  formData.description = '';
};


// --- Pagination Handlers ---

const handleSizeChange = (val) => {
  pageSize.value = val;
  handleSearch(); // 改变每页大小后，重新查询第一页数据
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  // 如果用户只是翻页，不应该触发搜索参数的修改，直接调用 fetchRoleList 即可
  fetchRoleList(currentPage.value, pageSize.value, searchForm);
};


// --- Lifecycle Hooks ---

onMounted(() => {
  loadData();
});

// Expose methods for template use if necessary (though not strictly needed here)
defineExpose({
    handleSearch,
    handleAdd,
    handleEdit,
    handleDelete,
    dialogSubmit
});
</script>

<style scoped>
.role-management {
  padding: 20px;
}

/* 头部卡片样式 */
.header-card {
  margin-bottom: 20px;
}

/* 查询搜索卡片样式 */
.search-card {
  margin-bottom: 20px;
}

/* 表格卡片样式 */
.table-card {
  padding-top: 10px;
}

/* 弹窗底部按钮组的样式调整，确保操作按钮对齐 */
.dialog-footer {
    display: flex;
    gap: 10px;
}
</style>