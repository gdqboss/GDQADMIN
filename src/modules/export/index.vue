<template>
  <div class="export-container">
    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="名称">
          <el-input v-model="searchForm.name" placeholder="请输入名称"></el-input>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态">
            <el-option label="启用" value="1"></el-option>
            <el-option label="禁用" value="0"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="hover">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3>导出记录列表</h3>
        <el-button type="primary" @click="handleAdd">添加</el-button>
      </div>

      <el-table v-model="list"         :columns="columns"
        :data="list"
        style="width: 100%"
        stripe
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column prop="id" label="ID" width="100" />
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button link @click="handleEdit(row)">编辑</el-button>
            <el-button link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        @size-change="handleSizeChange"
        @current-page-change="handleCurrentPageChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        :total="total"
        layout="bottom right"
        style="margin-top: 20px;"
      ></el-pagination>
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible"       title="添加/编辑导出记录"
      width="50%"
      :append-to-body="true"
    >
      <el-form 
        :model="formData" 
        ref="elFormRef" 
        :rules="rules" 
        label-col="{ size: '12%' }" 
        label-width="80px"
      >
        <el-form-item label="记录名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入记录名称"></el-input>
        </el-form-item>
        <el-form-item label="导出类型" prop="exportType">
          <el-select v-model="formData.exportType" placeholder="请选择导出类型">
            <el-option label="用户数据" value="user"></el-option>
            <el-option label="业务报表" value="report"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="formData.status">
            <el-radio-button label="1">启用</el-radio-button>
            <el-radio-button label="0">禁用</el-radio-button>
          </el-radio-group>
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
import { getExportList, createExportRecord, updateExportRecord, deleteExportRecord } from '@/api/export';

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
  exportType: 'user',
  status: '1',
});

// Pagination State
const currentPage = ref(1);
const pageSize = ref(10);
const searchFormRef = ref(null);
const elFormRef = ref(null);

// Table Columns Definition
const columns = [
  { title: 'ID', key: 'id', width: 80 },
  { title: '记录名称', key: 'name' },
  { title: '导出类型', key: 'exportType' },
  { title: '状态', key: 'status', width: 100 },
  { title: '操作', key: 'actions', flex: 1, width: '20%' },
];

// --- Methods ---

const loadData = async () => {
  loading.value = true;
  try {
    await getExportList({ page: 1, pageSize: 10, search: {} }); // 初始加载，不带搜索条件
    console.log('Initial data loaded');
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  if (!searchFormRef.value) return;
  await searchFormRef.value.validate();
  loading.value = true;
  try {
    // 实际调用 API 时，需要将搜索条件传递给 getExportList
    await getExportList({ page: 1, pageSize: 10, search: { name: searchForm.name, status: searchForm.status } });
    console.log('Search executed');
  } catch (error) {
    ElMessage.error('查询失败');
  } finally {
    loading.value = false;
  }
};

const resetSearch = () => {
  searchForm.name = '';
  searchForm.status = '1';
  handleSearch(); // 重置后重新加载数据
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  currentPage.value = 1;
  loadData();
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  loadData();
};

// --- Dialog/CRUD Handlers ---

const openAddDialog = () => {
  formData.id = null; // 清空ID，表示新增
  Object.assign(formData, { name: '', exportType: 'user', status: '1' });
  dialogVisible.value = true;
};

const handleAdd = () => {
  openAddDialog();
};

const openEditDialog = (row) => {
  // 填充表单数据
  Object.assign(formData, row);
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  openEditDialog(row);
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除记录 "${row.name}" 吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteExportRecord(row.id);
    ElMessage.success('删除成功');
    // 刷新列表（这里需要更精细的逻辑来处理分页和搜索条件）
    loadData(); 
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

const dialogSubmit = async () => {
  await elFormRef.value.validate();
  if (!elFormRef.value) return;

  try {
    let result;
    if (!formData.id) {
      // Add
      result = await createExportRecord(formData);
      ElMessage.success('添加成功');
    } else {
      // Edit
      await updateExportRecord(formData.id, formData);
      ElMessage.success('编辑成功');
    }
    dialogVisible.value = false;
    loadData(); // 提交成功后刷新列表
  } catch (error) {
    ElMessage.error('操作失败，请检查网络或数据。');
  }
};

// --- Lifecycle Hooks & Watchers ---

onMounted(() => {
  loadData();
});

const handleSelectionChange = (val) => {
  console.log('Selected rows:', val);
};

</script>

<style scoped>
.export-container {
  padding: 20px;
}

.search-card, .table-card {
  margin-bottom: 20px;
}

/* 确保 el-button 在 flex 布局中能正常显示 */
.el-form-item :deep(.el-btn) {
    margin-right: 10px;
}
</style>