<template>
  <div class="storage-module">
    <el-card class="header-card">
      <el-button type="primary" @click="handleAdd">添加</el-button>
      <el-input v-model="searchForm.name" placeholder="名称" style="width: 120px; margin-right: 10px;">
        <template #append>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
        </template>
      </el-input>
    </el-card>

    <el-card class="data-card">
      <el-table v-loading="loading" :data="list" style="width: 100%" border>
        <el-table-column prop="id" label="ID" width="80"></el-table-column>
        <el-table-column prop="name" label="名称" width="200"></el-table-column>
        <el-table-column prop="description" label="描述"></el-table-column>
        <el-table-column prop="status" label="状态">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'warning'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="showDeleteDialog(row.id, row.name)"></el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        @size-change="handleSizeChange"
        @current-page-change="handleCurrentPageChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        layout="bottom"
        total="total"
        adjust-size
      ></el-pagination>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="formTitle || '操作'">
      <el-form 
        :model="formData" 
        :rules="rules" 
        ref="elFormRef"
        label-col-span="3"
        label-width="80px"
      >
        <el-form-item label="名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入模块名称"></el-input>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" placeholder="请输入模块描述"></el-input>
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
// 假设 API 文件结构为 @/api/storage.js，并导出 getStorageList 等方法
import { getStorageList, createStorage, updateStorage, deleteStorage } from '@/api/storage';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  description: '',
  status: '1', // 默认启用
});
const elFormRef = ref(null);

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Form rules validation
const rules = reactive({
  name: [
    { required: true, message: '名称不能为空', trigger: 'blur' },
    { min: 2, max: 50, message: '长度必须在 2 到 50 个字符之间', trigger: 'blur' }
  ],
  description: [
    { required: false, message: '描述不能为空', trigger: 'blur' }
  ],
  status: [
    { required: true, message: '状态选择不能为空', trigger: 'change' }
  ]
});

const formTitle = ref('添加/编辑存储模块');

// --- Methods ---

const loadData = async () => {
  loading.value = true;
  try {
    await getStorageList({ page: 1, pageSize: 10, search: {} }); // 初始加载，不带搜索条件
    console.log('数据加载成功');
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  if (!searchForm.name) {
    ElMessage.warning('请输入模块名称进行搜索');
    return;
  }
  loading.value = true;
  try {
    await getStorageList({ 
      page: 1, 
      pageSize: pageSize.value, 
      search: { name: searchForm.name } 
    });
    console.log('搜索数据成功');
  } catch (error) {
    ElMessage.error('搜索失败');
  } finally {
    loading.value = false;
  }
};

const handleAdd = () => {
  formData.id = null; // 清空ID，表示新增
  formData.name = '';
  formData.description = '';
  formData.status = '1';
  formTitle.value = '添加存储模块';
  dialogVisible.value = true;
  elFormRef.value.resetFields();
};

const handleEdit = (row) => {
  // 填充表单数据，注意这里需要根据实际API返回的结构调整
  formData.id = row.id;
  formData.name = row.name;
  formData.description = row.description;
  formData.status = String(row.status); // 确保状态是字符串类型匹配radio-group
  formTitle.value = '编辑存储模块';
  dialogVisible.value = true;
  elFormRef.value.clearValidate();
};

const showDeleteDialog = (id, name) => {
  ElMessageBox.confirm(
    `确定要删除 "${name}" 模块吗?此操作不可恢复。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  ).then(async () => {
    loading.value = true;
    try {
      await deleteStorage({ id });
      ElMessage.success('删除成功');
      // 刷新列表
      list.value = []; // 清空，然后重新加载或直接调用 loadData
      await loadData(); 
    } catch (error) {
      ElMessage.error('删除失败');
    } finally {
      loading.value = false;
    }
  }).catch(() => {
    // 用户点击取消
  });
};

const dialogSubmit = async () => {
  elFormRef.value.validate(async (valid) => {
    if (!valid) {
      ElMessage.warning('请检查表单项是否填写完整');
      return;
    }

    loading.value = true;
    try {
      const isEdit = !!formData.id;
      let result;

      if (isEdit) {
        result = await updateStorage({ id: formData.id, name: formData.name, description: formData.description, status: parseInt(formData.status) });
        ElMessage.success('编辑成功');
      } else {
        result = await createStorage({ name: formData.name, description: formData.description, status: parseInt(formData.status) });
        ElMessage.success('添加成功');
      }

      dialogVisible.value = false;
      // 刷新列表，并重置搜索条件（如果需要）
      await loadData(); 
    } catch (error) {
      ElMessage.error('操作失败: ' + error.message);
    } finally {
      loading.value = false;
    }
  });
};

// --- Pagination Handlers ---

const handleSizeChange = (size) => {
  pageSize.value = size;
  currentPage.value = 1;
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  handleSearch(); // 切换页码时，重新加载数据（使用当前搜索条件）
};


// --- Lifecycle Hooks ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.storage-module {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.data-card {
  /* 样式保持简洁，让el-table和el-pagination发挥作用 */
}

.dialog-footer :deep(.el-button--primary) {
    background-color: var(--el-color-primary);
    border-color: var(--el-color-primary);
}
</style>