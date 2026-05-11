<template>
  <div class="print-management">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header-title">打印记录管理</div>
        <el-button type="primary" @click="handleAdd">新增</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="打印机名称">
          <el-input v-model="searchForm.printerName" placeholder="请输入打印机名称"></el-input>
        </el-form-item>
        <el-form-item label="打印时间范围">
          <el-date-picker v-model="searchForm.startTime" 
            type="daterange" 
            range-separator="至" 
            start-placeholder="开始日期" 
            end-placeholder="结束日期"
            @change="handleSearch"
          ></el-date-picker>
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
        <el-icon class="el-icon--selection" :class="{ 'is-selected': $event }">
          <el-checkbox></el-checkbox>
        </el-icon>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="printerName" label="打印机名称"></el-table-column>
        <el-table-column prop="printTime" label="打印时间"></el-table-column>
        <el-table-column prop="status" label="状态">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'warning'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="recordCount" label="记录数"></el-table-column>
        <el-table-column label="操作" width="300" align="center">
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
        layout="top"
        total="total"
      ></el-pagination>
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible" 
      title="打印记录{{ isEditing ? '编辑' : '新增' }}" 
      width="50%" 
      :close-on-click-modal="false"
    >
      <el-form 
        ref="formDataRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="3" 
        wrapper-tag="div"
      >
        <el-form-item label="打印机名称" prop="printerName">
          <el-input v-model="formData.printerName" placeholder="请输入打印机名称"></el-input>
        </el-form-item>
        <el-form-item label="记录数" prop="recordCount">
          <el-input-number v-model="formData.recordCount" :min="0"></el-input-number>
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
          <el-button @click="$refs.dialog.close()">取消</el-button>
          <el-button type="primary" @click="dialogSubmit">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 文件结构如下，如果不存在则使用 request
import getPrintList from '@/api/print'; // 请确保此路径存在或修改为实际的API调用

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  printerName: '',
  startTime: [],
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  printerName: '',
  recordCount: 0,
  status: '1', // 默认启用
});
const isEditing = ref(false);

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Refs for forms and dialogs
const searchFormRef = ref(null);
const formDataRef = ref(null);


// --- Methods ---

const loadData = async () => {
  loading.value = true;
  try {
    await fetchList();
  } finally {
    loading.value = false;
  }
};

const fetchList = async (params = {}) => {
  loading.value = true;
  try {
    // 模拟 API 调用，使用传入的参数进行分页查询
    const result = await getPrintList({
      page: currentPage.value,
      pageSize: pageSize.value,
      search: params,
    });

    list.value = result.data || [];
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载数据失败');
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  currentPage.value = 1; // 查询时重置页码
  await fetchList({ ...searchForm });
};

const resetSearch = () => {
  searchForm.printerName = '';
  searchForm.startTime = [];
  handleSearch();
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  currentPage.value = 1;
  fetchList({});
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  fetchList({});
};

// --- Dialog Handlers ---

const openAddDialog = () => {
  formData.id = null;
  formData.printerName = '';
  formData.recordCount = 0;
  formData.status = '1';
  isEditing.value = false;
  dialogVisible.value = true;
};

const openEditDialog = (row) => {
  // 填充表单数据
  formData.id = row.id;
  formData.printerName = row.printerName;
  formData.recordCount = row.recordCount;
  formData.status = String(row.status); // 确保类型一致性
  isEditing.value = true;
  dialogVisible.value = true;
};

const dialogSubmit = async () => {
  if (!formDataRef.value) return;
  
  // 触发表单验证
  await formDataRef.value.validateElForm();

  try {
    let result;
    if (isEditing.value && formData.id) {
      // 执行编辑API调用
      result = await getPrintList({ id: formData.id, ...formData }); // 假设API支持更新单个记录
      ElMessage.success('编辑成功');
    } else {
      // 执行新增API调用
      result = await getPrintList(null, null, { ...formData }); // 假设API支持创建
      ElMessage.success('添加成功');
    }

    dialogVisible.value = false;
    await loadData(); // 数据加载完成后刷新列表
  } catch (error) {
    ElMessage.error('操作失败，请检查输入内容。');
    console.error(error);
  }
};


// --- Delete Handler ---

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除打印记录 ${row.id} 吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    // 执行删除API调用
    await getPrintList({ id: row.id }); // 假设API支持根据ID删除
    ElMessage.success('删除成功');
    await loadData();
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

// --- Selection Handler (Optional, but good practice for bulk actions) ---
const handleSelectionChange = (val) => {
  console.log('Selected rows:', val);
  // 可以在这里实现批量操作的逻辑，例如：批量禁用/启用
};


// --- Lifecycle Hooks ---
onMounted(() => {
  loadData();
});

</script>

<style scoped>
.print-management {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.search-card {
  margin-bottom: 20px;
}

.table-card {
  /* 保持默认样式即可 */
}

.dialog-footer :deep(.el-button--primary) {
    background-color: var(--el-color-primary);
    border-color: var(--el-color-primary);
}
</style>