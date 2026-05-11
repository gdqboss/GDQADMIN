<template>
  <div class="dict-container">
    <el-card class="header-card">
      <el-button type="primary" @click="handleAdd">添加</el-button>
      <el-input v-model="searchForm.name" placeholder="名称" style="width: 120px; margin-right: 10px;">
        <template #append>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
        </template>
      </el-input>
    </el-card>

    <el-card class="table-card">
      <el-table 
        v-loading="loading"
        :data="list" 
        style="width: 100%"
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @change="handleSelectionChange" />
        <el-table-column prop="code" label="编码" width="120"></el-table-column>
        <el-table-column prop="name" label="名称" width="200"></el-table-column>
        <el-table-column prop="description" label="描述"></el-table-column>
        <el-table-column label="操作" width="300">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          @size-change="handleSizeChange"
          @current-page-change="handleCurrentPageChange"
          :current-page="pagination.currentPage"
          :page-sizes="[10, 20, 50]"
          :page-size="pagination.pageSize"
          layout="total, bottom"
          total="total"
        ></el-pagination>
      </div>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="formData.id ? '编辑字典项' : '添加字典项'">
      <el-form 
        ref="elFormRef" 
        :model="formData" 
        label-col-span="3" 
        label-width="100px"
        rules="rules"
      >
        <el-form-item label="编码" prop="code">
          <el-input v-model="formData.code" />
        </el-form-item>
        <el-form-item label="名称" prop="name">
          <el-input v-model="formData.name" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" type="textarea"></el-input>
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
// 假设 API 文件结构为 @/api/dict.js，并导出 getDictList, createDict, updateDict, deleteDict 等函数
import { getDictList, createDict, updateDict, deleteDict } from '@/api/dict';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  page: 1,
  pageSize: 10,
  search: {
    name: '',
  },
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  code: '',
  name: '',
  description: '',
});
const elFormRef = ref(null);

// Pagination state management (using local refs for simplicity in this component structure)
const pagination = reactive({
    currentPage: 1,
    pageSize: 10,
});


const rules = reactive({
    name: [
        { required: true, message: '名称不能为空', trigger: 'blur' },
        { min: 3, max: 20, message: '长度在 3 到 20 个字符之间', trigger: 'blur' }
    ],
    code: [
        { required: true, message: '编码不能为空', trigger: 'blur' }
    ]
});

const loadData = async () => {
  loading.value = true;
  try {
    await getDictList({ 
      page: searchForm.search.page || 1, 
      pageSize: searchForm.search.pageSize || 10, 
      search: searchForm.search 
    });
    // API 返回结构假设为 { list: [...], total: N }
    list.value = await getDictList({ page: 1, pageSize: 10, search: searchForm.search }).then(res => res.data || []);
    total.value = await getDictList({ page: 1, pageSize: 10, search: searchForm.search }).then(res => res.total || 0);

  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  // Reset page to 1 on search
  searchForm.search.page = 1;
  await loadData();
};

const handleSizeChange = (size) => {
    pagination.pageSize = size;
    handleCurrentPageChange(1); // Reset to first page when changing size
}

const handleCurrentPageChange = async (page) => {
    pagination.currentPage = page;
    await loadData();
};


const resetForm = () => {
    formData.id = null;
    formData.code = '';
    formData.name = '';
    formData.description = '';
    elFormRef.value.resetFields();
}

const handleAdd = () => {
  resetForm();
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // Populate form data with existing row data
  formData.id = row.id;
  formData.code = row.code;
  formData.name = row.name;
  formData.description = row.description;
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定删除字典项 "${row.name}" 吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteDict({ id: row.id }); // API call for deletion
    ElMessage.success('删除成功');
    // Refresh list after successful deletion
    searchForm.search.page = 1;
    await loadData();
  } catch (error) {
    ElMessage.error('删除失败');
  }
};

const dialogSubmit = async () => {
  elFormRef.value.validate(async (valid) => {
    if (!valid) {
      return false;
    }

    loading.value = true;
    try {
      let result;
      if (formData.id) {
        // Edit logic
        result = await updateDict({ id: formData.id, code: formData.code, name: formData.name, description: formData.description });
        ElMessage.success('编辑成功');
      } else {
        // Add logic
        result = await createDict({ code: formData.code, name: formData.name, description: formData.description });
        ElMessage.success('添加成功');
      }

      dialogVisible.value = false;
      resetForm();
      searchForm.search.page = 1;
      await loadData();

    } catch (error) {
      ElMessage.error('操作失败，请检查网络或后端返回信息');
    } finally {
      loading.value = false;
    }
  });
};


// --- Lifecycle Hooks and Watchers ---

onMounted(() => {
  loadData();
});

// Expose methods/state for template use if needed, though setup script handles reactivity well.
defineExpose({
    handleSearch,
    handleAdd,
    handleEdit,
    handleDelete,
    dialogSubmit
})
</script>

<style scoped>
.dict-container {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.table-card {
  margin-top: 10px;
}

.pagination-container {
    display: flex;
    justify-content: flex-end;
    padding-top: 15px;
}

/* Style adjustments for el-button links */
:deep(.el-table__row :deep(.el-button--link)) {
    margin-right: 8px;
}
</style>