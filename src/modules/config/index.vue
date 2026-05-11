<template>
  <div class="config-container">
    <h2>系统配置管理</h2>

    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="配置项名称" prop="name">
          <el-input v-model="searchForm.name" placeholder="请输入配置项名称"></el-input>
        </el-form-item>
        <el-form-item label="配置值" prop="value">
          <el-input v-model="searchForm.value" placeholder="请输入配置值"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="loadData(1, 10)">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="hover">
      <template #header>
        <div class="header-title">系统配置列表</div>
        <el-button type="primary" @click="handleAdd">添加配置</el-button>
      </template>

      <el-table 
        v-loading="loading" 
        :data="list" 
        @selection-change="handleSelectionChange"
        stripe
        border
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="配置项名称" width="200"></el-table-column>
        <el-table-column prop="value" label="配置值" width="300"></el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        @size-change="handleSizeChange"
        @current-page-change="handlePageChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        layout="bottom"
        total="total"
      ></el-pagination>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="formTitle || '配置管理'">
      <el-form 
        :model="formData" 
        :rules="rules" 
        ref="elFormRef"
        label-col-span="2"
      >
        <el-form-item label="ID" prop="id">
          <el-input v-model="formData.id" :disabled="true"></el-input>
        </el-form-item>
        <el-form-item label="配置项名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入配置项名称"></el-input>
        </el-form-item>
        <el-form-item label="配置值" prop="value">
          <el-input v-model="formData.value" placeholder="请输入配置值"></el-input>
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
// 假设 API 文件结构为 @/api/config.js，并导出 getConfigList, createConfig, updateConfig, deleteConfig 等函数
import { getConfigList, createConfig, updateConfig, deleteConfig } from '@/api/config';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({ name: '', value: '' });
const dialogVisible = ref(false);
const formData = reactive({ id: null, name: '', value: '' });
const elFormRef = ref(null);

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Form validation rules
const rules = reactive({
  name: [
    { required: true, message: '配置项名称不能为空', trigger: 'blur' },
    { min: 2, max: 50, message: '长度在 2 到 50 个字符之间', trigger: 'blur' }
  ],
  value: [
    { required: true, message: '配置值不能为空', trigger: 'blur' }
  ]
});

const formTitle = ref('添加/编辑配置');

// --- Methods ---

const loadData = async (page = 1, size = 10) => {
  loading.value = true;
  try {
    // 调用 API，使用对象参数
    const res = await getConfigList({ page: page, pageSize: size, search: searchForm });
    list.value = res.data || [];
    total.value = res.total || 0;
  } catch (error) {
    console.error('加载配置列表失败:', error);
    ElMessage.error('获取数据失败，请重试');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  await loadData(1, 10); // 查询时重置页码到第一页
};

const handleAdd = () => {
  // 重置表单和状态为添加模式
  formData.id = null;
  formData.name = '';
  formData.value = '';
  formTitle.value = '添加配置';
  dialogVisible.value = true;
  elFormRef.value.resetFields();
};

const handleEdit = (row) => {
  // 填充表单数据为编辑模式
  formData.id = row.id;
  formData.name = row.name;
  formData.value = row.value;
  formTitle.value = '编辑配置';
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除配置项 "${row.name}" 吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    // 调用 API 删除，使用对象参数
    await deleteConfig({ id: row.id }); 
    ElMessage.success('删除成功');
    loadData(currentPage.value, pageSize.value); // 刷新列表
  } catch (error) {
    console.error('删除失败:', error);
    ElMessage.error('删除失败，请检查权限或联系管理员');
  }
};

const dialogSubmit = async () => {
  elFormRef.value.validate(async (valid) => {
    if (!valid) {
      return false;
    }

    try {
      let result;
      if (formData.id) {
        // 编辑逻辑
        await updateConfig({ id: formData.id, name: formData.name, value: formData.value });
        result = '编辑成功';
      } else {
        // 添加逻辑
        await createConfig({ name: formData.name, value: formData.value });
        result = '添加成功';
      }

      ElMessage.success(result);
      dialogVisible.value = false;
      loadData(currentPage.value, pageSize.value); // 刷新列表
    } catch (error) {
      console.error('提交失败:', error);
      ElMessage.error('操作失败，请重试');
    }
  });
};

// --- Pagination Handlers ---

const handlePageChange = async (page) => {
  currentPage.value = page;
  await loadData(page, pageSize.value);
};

const handleSizeChange = async (size) => {
  pageSize.value = size;
  currentPage.value = 1; // 改变页大小后，重置到第一页
  await loadData(1, size);
};

// --- Selection Handler (Optional but good practice for table actions) ---
const handleSelectionChange = (selection) => {
    console.log('选中的行:', selection);
}


// --- Lifecycle Hooks ---
onMounted(() => {
  loadData(); // 组件挂载时加载数据
});

</script>

<style scoped>
.config-container {
  padding: 20px;
}

/* 覆盖 el-card 的默认样式，使其更像一个管理页的布局 */
.search-card, .table-card {
  margin-bottom: 20px;
}

.header-title {
    font-size: 1.2em;
    font-weight: bold;
    margin-right: 20px;
}

/* 确保 el-dialog 的样式在组件内生效 */
:deep(.el-dialog__body) {
  padding: 20px;
}
</style>