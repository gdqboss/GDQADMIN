<template>
  <div class="permission-module">
    <el-card class="box-card">
      <el-header>
        <span>权限管理</span>
        <el-button type="primary" @click="handleAdd">添加</el-button>
      </el-header>

      <!-- 搜索表单 -->
      <el-card class="search-card mb-4">
        <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
          <el-form-item label="名称">
            <el-input v-model="searchForm.name" placeholder="请输入权限名称"></el-input>
          </el-form-item>
          <el-form-item label="类型">
            <el-select v-model="searchForm.type" placeholder="请选择类型">
              <el-option label="系统权限" value="system"></el-option>
              <el-option label="功能模块" value="module"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleSearch">查询</el-button>
            <el-button @click="resetSearch">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 数据表格 -->
      <el-table 
        :data="list" 
        v-loading="loading" 
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="名称"></el-table-column>
        <el-table-column prop="type" label="类型" width="120">
          <template #default="{ row }">
            <el-tag :type="row.type === 'system' ? 'info' : 'success'">{{ row.type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="描述"></el-table-column>
        <el-table-column label="操作" width="300">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="handleDelete(row.id, row.name)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页组件 -->
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

    <!-- 添加/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" 
      title="权限信息管理" 
      width="50%" 
      :before-close="handleDialogClose"
    >
      <el-form 
        ref="searchFormRef" 
        :model="formData" 
        label-col-span="3" 
        label-width="100px"
      >
        <el-form-item label="ID">
          <el-input v-model="formData.id" :disabled="true"></el-input>
        </el-form-item>
        <el-form-item label="名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入权限名称"></el-input>
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择类型">
            <el-option label="系统权限" value="system"></el-option>
            <el-option label="功能模块" value="module"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" placeholder="请输入权限描述"></el-input>
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
// 假设的 API 引入，请根据实际情况修改路径和函数名
import { getPermissionList, createPermission, updatePermission, deletePermission } from '@/api/permission';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  page: 1,
  pageSize: 10,
  search: {
    name: '',
    type: '',
  },
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  type: 'system',
  description: ''
});

// 引用表单组件用于重置和验证
const searchFormRef = ref(null);

// --- 生命周期钩子 ---
onMounted(() => {
  loadData();
});

// --- 方法定义 ---

const loadData = async () => {
  loading.value = true;
  try {
    // 模拟 API 调用，使用搜索表单中的参数进行查询
    await getPermissionList({
      page: searchForm.search.page || 1,
      pageSize: searchForm.search.pageSize || 10,
      search: searchForm.search,
    });
    // 假设 API 返回结构为 { list: [...], total: N }
    list.value = await getPermissionList({
        page: searchForm.search.page || 1,
        pageSize: searchForm.search.pageSize || 10,
        search: searchForm.search,
    }).then(res => res.data.list); // 假设返回数据在 data.list 中
    total.value = await getPermissionList({
        page: searchForm.search.page || 1,
        pageSize: searchForm.search.pageSize || 10,
        search: searchForm.search,
    }).then(res => res.data.total); // 假设返回总数在 data.total 中

  } catch (error) {
    console.error('加载数据失败:', error);
    ElMessage.error('加载权限列表失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  // 确保搜索表单中的 page 和 pageSize 是正确的，这里我们直接使用 searchForm 的值来触发查询
  searchForm.search.page = 1; // 重置到第一页
  await loadData();
};

const resetSearch = () => {
    searchForm.search.name = '';
    searchForm.search.type = '';
    // 重新加载数据，通常重置搜索后需要刷新列表
    loadData();
}

const handleSizeChange = (size) => {
  searchForm.search.pageSize = size;
  loadData();
};

const handleCurrentPageChange = (page) => {
  searchForm.search.page = page;
  loadData();
};


// --- 弹窗和 CRUD 操作 ---

const handleAdd = () => {
  formData.id = null; // 清空ID，表示新增
  formData.name = '';
  formData.type = 'system';
  formData.description = '';
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.id = row.id;
  formData.name = row.name;
  formData.type = row.type;
  formData.description = row.description;
  dialogVisible.value = true;
};

const handleDialogClose = () => {
    // 无论用户如何关闭弹窗，都重置表单状态（可选）
    formData.id = null;
    formData.name = '';
    formData.type = 'system';
    formData.description = '';
}


const dialogSubmit = async () => {
  if (!searchFormRef.value) return;

  // 触发表单验证
  await searchFormRef.value.validateElForm();

  try {
    let result;
    if (formData.id) {
      // 编辑逻辑
      result = await updatePermission(formData);
      ElMessage.success('编辑成功');
    } else {
      // 添加逻辑
      result = await createPermission(formData);
      ElMessage.success('添加成功');
    }
    dialogVisible.value = false;
    loadData(); // 刷新列表
  } catch (error) {
    console.error('提交失败:', error);
    ElMessage.error('操作失败，请检查网络或权限。');
  }
};

const handleDelete = async (id, name) => {
  await ElMessageBox.confirm(
    `您确定要删除 "${name}" 权限吗?此操作不可恢复。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    await deletePermission(id);
    ElMessage.success('删除成功');
    loadData(); // 刷新列表
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

// --- 表单选择器监听（如果需要根据类型改变其他字段，可以在这里添加）---
</script>

<style scoped>
.permission-module {
  padding: 20px;
}

.box-card {
  margin-bottom: 20px;
}

.search-card {
    border: none;
    box-shadow: none !important;
}

/* 确保 el-table 的高度和布局正常 */
:deep(.el-table__body-wrapper) {
    height: 400px; /* 示例固定高度，根据实际需要调整 */
}

.dialog-footer :deep(.el-button--primary) {
    background-color: var(--el-color-primary);
    border-color: var(--el-color-primary);
}
</style>