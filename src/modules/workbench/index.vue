<template>
  <div class="workbench-container">
    <el-card class="header-card" shadow="hover">
      <template #header>
        <div class="header-title">工作台管理</div>
        <el-button type="primary" @click="handleAdd">添加</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="工作台名称">
          <el-input v-model="searchForm.name" placeholder="请输入工作台名称"></el-input>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态">
            <el-option label="启用" :value="1"></el-option>
            <el-option label="禁用" :value="0"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="loadData">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="hover">
      <el-table 
        :data="list" 
        style="width: 100%" 
        stripe 
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="工作台名称"></el-table-column>
        <el-table-column prop="status" label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'warning'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="创建时间"></el-table-column>
        <el-table-column label="操作" width="300">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
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
      title="工作台信息管理" 
      width="50%" 
      :before-close="handleDialogClose">
      <el-form 
        :model="formData" 
        ref="elFormRef" 
        :rules="rules"
        label-for="name"
      >
        <el-form-item label="工作台名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入工作台名称"></el-input>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="formData.status" placeholder="请选择状态">
            <el-option label="启用" :value="1"></el-option>
            <el-option label="禁用" :value="0"></el-option>
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
// 假设 API 函数路径为 '@/api/workbench'
import { getWorkbenchList, updateWorkbench, createWorkbench } from "@/api/workbench";

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  status: 1, // 默认启用
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  status: 1,
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Refs for forms and elements
const searchFormRef = ref(null);
const elFormRef = ref(null);


// --- Methods ---

const loadData = async () => {
  loading.value = true;
  currentPage.value = 1;
  await fetchData();
};

const handleSearch = async () => {
  loading.value = true;
  currentPage.value = 1;
  await fetchData();
};

const fetchData = async (page = 1, size = 10) => {
    try {
        // API 调用示例：使用对象参数
        const res = await getWorkbenchList({ page: page, pageSize: size, search: searchForm });
        list.value = res.data || [];
        total.value = res.total || 0;
    } catch (error) {
        ElMessage.error('加载数据失败');
    } finally {
        loading.value = false;
    }
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  fetchData(page, pageSize.value);
};

const handleSizeChange = (size) => {
  pageSize.value = size;
  fetchData(currentPage.value, size);
};


// --- CRUD Operations ---

const resetForm = () => {
    formData.id = null;
    formData.name = '';
    formData.status = 1;
}

const handleAdd = () => {
  resetForm();
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.id = row.id;
  formData.name = row.name;
  formData.status = row.status;
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除工作台 "${row.name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    // API 调用示例：删除
    await deleteWorkbench(row.id);
    ElMessage.success('删除成功');
    loadData(); // 刷新列表
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

const dialogSubmit = async () => {
  if (!elFormRef.value.validateModel()) {
    return;
  }

  loading.value = true;
  let result;
  try {
    if (formData.id) {
      // 编辑逻辑
      result = await updateWorkbench(formData);
      ElMessage.success('编辑成功');
    } else {
      // 添加逻辑
      result = await createWorkbench(formData);
      ElMessage.success('添加成功');
    }
    dialogVisible.value = false;
    loadData(); // 刷新列表
  } catch (error) {
    ElMessage.error('操作失败，请检查网络或数据。');
  } finally {
    loading.value = false;
  }
};

const handleDialogClose = () => {
    resetForm();
    dialogVisible.value = false;
};


// --- Lifecycle Hooks ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.workbench-container {
  padding: 20px;
}

.header-card, .search-card, .table-card {
  margin-bottom: 20px;
}

.header-title {
    font-size: 1.5em;
    font-weight: bold;
}

/* 确保 el-button 在表单内有良好的间距 */
.el-form-item :deep(.el-btn) {
    margin-right: 10px;
}
</style>