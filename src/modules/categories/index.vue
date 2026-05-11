<template>
  <div class="category-management">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header-title">分类管理</div>
        <el-button type="primary" @click="handleAdd">新增</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="分类名称">
          <el-input v-model="searchForm.name" placeholder="请输入分类名称"></el-input>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态">
            <el-option label="启用" value="1" />
            <el-option label="禁用" value="0" />
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
        <el-table-column prop="name" label="分类名称" width="200"></el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'warning'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
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
        :total="total"
      ></el-pagination>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="formData.id ? '编辑分类' : '新增分类'" width="50%">
      <el-form 
        ref="elFormRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="3" 
        label-width="100px"
      >
        <el-form-item label="ID" prop="id">
          <el-input v-model="formData.id" :disabled="true"></el-input>
        </el-form-item>
        <el-form-item label="分类名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入分类名称"></el-input>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="formData.status" placeholder="请选择状态">
            <el-option label="启用" value="1" />
            <el-option label="禁用" value="0" />
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
// 假设 API 文件路径为 '@/api/categories'
import { getCategoryList, createCategory, updateCategory, deleteCategory } from "@/api/categories";

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

// Element Plus Form Refs
const searchFormRef = ref(null);
const elFormRef = ref(null);

// Pagination State
const currentPage = ref(1);
const pageSize = ref(10);

// Validation Rules
const rules = reactive({
  name: [
    { required: true, message: '分类名称不能为空', trigger: 'blur' },
    { min: 2, max: 50, message: '长度必须在 2 到 50 个字符之间', trigger: 'blur' }
  ],
});

// --- Methods ---

const loadData = async () => {
  await fetchData();
};

const handleSearch = async () => {
  currentPage.value = 1; // 查询时重置页码
  await fetchData();
};

const fetchData = async (page = 1, size = 10) => {
  loading.value = true;
  try {
    // 调用 API，使用对象参数
    const res = await getCategoryList({ page: page, pageSize: size, search });
    list.value = res.data || [];
    total.value = res.total || 0;
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (size) => {
  pageSize.value = size;
  fetchData(currentPage.value, size);
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  // 搜索或重置时，需要使用当前 searchForm 的值进行查询
  if (!searchForm.name && !searchForm.status) {
    fetchData(page, pageSize.value); // 如果是点击页码，且没有明确的搜索条件，则用默认参数加载
  } else {
    // 否则，使用当前 searchForm 的值进行查询
    handleSearch();
  }
};

const handleAdd = () => {
  formData.id = null;
  formData.name = '';
  formData.status = '1';
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
    `确定要删除分类 "${row.name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteCategory(row.id); // API 调用
    ElMessage.success('删除成功');
    // 刷新列表，重置表单和页码到第一页
    list.value = [];
    total.value = 0;
    currentPage.value = 1;
    await fetchData();
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

const dialogSubmit = async () => {
  elFormRef.value.validate(async (valid) => {
    if (!valid) return;

    loading.value = true;
    try {
      let result;
      if (formData.id) {
        // 编辑逻辑
        result = await updateCategory(formData.id, formData);
        ElMessage.success('编辑成功');
      } else {
        // 新增逻辑
        result = await createCategory(formData);
        ElMessage.success('新增成功');
      }

      dialogVisible.value = false;
      // 刷新列表，重置表单和页码到第一页
      list.value = [];
      total.value = 0;
      currentPage.value = 1;
      await fetchData();

    } catch (error) {
      ElMessage.error('操作失败');
    } finally {
      loading.value = false;
    }
  });
};


// --- Watchers & Lifecycle ---

onMounted(() => {
  loadData();
});

// 监听搜索表单变化，如果用户手动修改了 searchForm 的值，需要重置页码到 1
watch(searchForm, () => {
    if (Object.keys(searchForm).length > 0) {
        currentPage.value = 1;
    }
});

</script>

<style scoped>
.category-management {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.search-card {
  margin-bottom: 20px;
}

.table-card {
  /* 确保表格卡片有足够的空间 */
}

.dialog-footer {
    padding-left: 15px;
}
</style>