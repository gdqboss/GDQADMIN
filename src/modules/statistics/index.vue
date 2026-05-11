<template>
  <div class="statistics-container">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header-title">统计数据管理</div>
        <el-button type="primary" @click="handleAdd">添加</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="统计项名称">
          <el-input v-model="searchForm.name" placeholder="请输入统计项名称"></el-input>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态">
            <el-option label="启用" value="1"></el-option>
            <el-option label="禁用" value="0"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="loadData">重置</el-button>
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
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="统计项名称"></el-table-column>
        <el-table-column prop="status" label="状态">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'warning'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="创建时间"></el-table-column>
        <el-table-column label="操作" width="300">
          <template #default="{ row }">
            <el-button link type="primary" @click="$emit('edit', row)">编辑</el-button>
            <el-button link type="warning" @click="$emit('delete', row)">删除</el-button>
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

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible" 
      title="统计项信息" 
      width="50%" 
      :before-close="handleDialogClose">
      <el-form 
        :model="formData" 
        ref="elFormRef" 
        :rules="rules"
        label-col-span="4"
        prop-rules
      >
        <el-form-item label="ID" prop="id">
          <el-input v-model="formData.id" :disabled="true"></el-input>
        </el-form-item>
        <el-form-item label="统计项名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入统计项名称"></el-input>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="formData.status" placeholder="请选择状态">
            <el-option label="启用" value="1"></el-option>
            <el-option label="禁用" value="0"></el-option>
          </el-select>
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
// 假设 API 文件结构如下，请根据实际情况调整导入路径
import { getStatisticsList, createStatistic, updateStatistic, deleteStatistic } from '@/api/statistics';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  status: '1', // 默认启用
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  status: '1',
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Refs for forms and elements
const searchFormRef = ref(null);
const elFormRef = ref(null);

// --- Methods ---

const loadData = async () => {
  await handleSearch();
};

const handleSearch = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      search: searchForm,
    };
    // 实际调用 API，使用 object 参数
    const result = await getStatisticsList({ page: currentPage.value, pageSize: pageSize.value, search: searchForm });
    list.value = result.data || [];
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  handleSearch();
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  handleSearch();
};

// --- Dialog/CRUD Handlers ---

const openDialog = (record = {}) => {
  if (record) {
    // Edit mode
    Object.assign(formData, record);
    dialogVisible.value = true;
  } else {
    // Add mode
    formData.id = null;
    formData.name = '';
    formData.status = '1';
    dialogVisible.value = true;
  }
};

const handleAdd = () => {
  openDialog();
};

const handleEdit = (record) => {
  openDialog(record);
};

const handleDelete = async (record) => {
  await ElMessageBox.confirm(
    `确定要删除统计项 "${record.name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteStatistic(record.id);
    ElMessage.success('删除成功');
    handleSearch(); // 刷新列表
  } catch (error) {
    ElMessage.error('删除失败');
  }
};

const handleDialogClose = () => {
  dialogVisible.value = false;
  // 清空表单数据，防止残留
  formData.id = null;
  formData.name = '';
  formData.status = '1';
};

const dialogSubmit = async () => {
  elFormRef.value.validate(async (valid) => {
    if (!valid) return;

    try {
      let result;
      // 简单判断：如果ID存在且非空，认为是编辑；否则是新增
      if (formData.id) {
        result = await updateStatistic(formData);
        ElMessage.success('更新成功');
      } else {
        await createStatistic(formData);
        ElMessage.success('添加成功');
      }
      dialogVisible.value = false;
      handleSearch(); // 刷新列表
    } catch (error) {
      ElMessage.error('提交失败，请检查网络或数据。');
    }
  });
};

// --- Selection Change Handler (If using selection for bulk actions, though not implemented here) ---
const handleSelectionChange = (val) => {
  console.log('Selected rows:', val);
};


// --- Lifecycle Hooks ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.statistics-container {
  padding: 20px;
}

.header-card, .search-card, .table-card {
  margin-bottom: 20px;
}

/* Adjusting el-button alignment in the header */
.card-header-title {
  font-size: 18px;
  font-weight: bold;
}

/* Style for status tag within table */
:deep(.el-tag--success) {
    background-color: #e6f7ff !important;
    color: #1890ff !important;
}
:deep(.el-tag--warning) {
    background-color: #fffbe6 !important;
    color: #faad14 !important;
}

/* Dialog footer spacing */
.dialog-footer {
  padding-right: 10px;
}
</style>