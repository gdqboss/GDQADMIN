<template>
  <div class="label-container">
    <el-card class="header-card">
      <template #header>
        <div class="header-title">标签管理</div>
        <el-button type="primary" @click="handleAdd">添加</el-button>
      </template>
    </el-card>

    <el-card class="search-card">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="标签名称">
          <el-input v-model="searchForm.name" placeholder="请输入标签名称"></el-input>
        </el-form-item>
        <el-form-item label="标签类型">
          <el-select v-model="searchForm.type" placeholder="请选择标签类型">
            <el-option label="系统" value="system"></el-option>
            <el-option label="用户" value="user"></el-option>
            <el-option label="其他" value="other"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="loadData">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <el-table 
        :data="list" 
        v-loading="loading" 
        @selection-change="handleSelectionChange"
        stripe
        border
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="标签名称" width="200"></el-table-column>
        <el-table-column prop="type" label="类型" width="150">
          <template #default="{ row }">
            <el-tag :type="getLabelType(row.type)">{{ getLabelTypeName(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="描述"></el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="handleDelete(row.id, row.name)">删除</el-button>
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
      title="标签信息" 
      width="50%" 
      :before-close="handleDialogClose">
      <el-form 
        :model="formData" 
        ref="elFormRef" 
        :rules="rules"
        label-for="name"
      >
        <el-form-item label="标签名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入标签名称"></el-input>
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择标签类型" clearable>
            <el-option label="系统" value="system"></el-option>
            <el-option label="用户" value="user"></el-option>
            <el-option label="其他" value="other"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" placeholder="请输入标签描述"></el-input>
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
// 假设 API 文件结构如下，如果不存在则使用通用的 request
import getLabelList from '@/api/label'; 

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  type: 'user' // 默认值
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  type: 'user',
  description: ''
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Refs for forms and elements
const searchFormRef = ref(null);
const elFormRef = ref(null);


// --- Methods ---

const getLabelTypeName = (type) => {
  switch (type) {
    case 'system': return '系统';
    case 'user': return '用户';
    case 'other': return '其他';
    default: return '未知';
  }
};

const getLabelType = (type) => {
  if (!type) return '';
  switch (type) {
    case 'system': return 'success';
    case 'user': return 'info';
    case 'other': return 'warning';
    default: return '';
  }
};

const loadData = async () => {
  loading.value = true;
  currentPage.value = 1;
  searchForm.name = '';
  searchForm.type = 'user';
  await fetchData();
};

const handleSearch = async () => {
  if (!searchFormRef.value) return;
  searchFormRef.value.validate(async (valid) => {
    if (valid) {
      loading.value = true;
      currentPage.value = 1;
      await fetchData();
    } else {
      console.error('Validation failed');
      return false;
    }
  });
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  loadData();
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  // 实际项目中，这里需要根据当前搜索条件重新调用API，但为了简化，我们直接调用 loadData 来重置状态并加载新页数据
  loadData(); 
};

const fetchData = async () => {
    try {
        const params = {
            page: currentPage.value,
            pageSize: pageSize.value,
            search: searchForm // API期望的参数结构
        };
        // 调用API，注意：这里假设 getLabelList 接受一个包含 page/pageSize/search 的对象
        const result = await getLabelList({ ...params }); 
        list.value = result.data || [];
        total.value = result.total || 0;
    } catch (error) {
        ElMessage.error('加载标签列表失败: ' + error.message);
    } finally {
        loading.value = false;
    }
};

// 监听搜索表单变化，实现实时或按需查询（这里选择点击按钮触发）
const handleSearchClick = () => {
    handleSearch();
}


const handleAdd = () => {
  formData.id = null;
  formData.name = '';
  formData.type = 'user';
  formData.description = '';
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  formData.id = row.id;
  formData.name = row.name;
  formData.type = row.type;
  formData.description = row.description;
  dialogVisible.value = true;
};

const handleDelete = async (id, name) => {
  await ElMessageBox.confirm(
    `确定要删除标签 "${name}" 吗?此操作不可恢复。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    // 假设 API 有 deleteLabelById(id) 方法
    await getLabelList({ id }); // 替换为实际的删除API调用，例如：deleteLabelById(id)
    ElMessage.success('删除成功');
    loadData();
  } catch (error) {
    ElMessage.error('删除失败: ' + error.message);
  }
};

const handleDialogClose = () => {
  dialogVisible.value = false;
  // 清空表单，防止误操作残留数据
  formData.id = null;
  formData.name = '';
  formData.type = 'user';
  formData.description = '';
};

const dialogSubmit = async () => {
  if (!elFormRef.value) return;
  await elFormRef.value.validateElForm();

  try {
    let apiCall;
    let successMessage;

    if (formData.id) {
      // 编辑逻辑
      apiCall = getLabelList({ id: formData.id, name: formData.name, type: formData.type, description: formData.description }); // 替换为实际的更新API调用
      successMessage = '编辑成功';
    } else {
      // 添加逻辑
      apiCall = getLabelList({ name: formData.name, type: formData.type, description: formData.description }); // 替换为实际的创建API调用
      successMessage = '添加成功';
    }

    await apiCall; // 执行 API 调用
    ElMessage.success(successMessage);
    handleDialogClose();
    loadData(); // 刷新列表
  } catch (error) {
    ElMessage.error('提交失败: ' + error.message);
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
.label-container {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.search-card, .table-card {
  margin-bottom: 20px;
}

.header-title {
  font-size: 1.5em;
  font-weight: bold;
}

/* 确保 el-button 在 header 中布局合理 */
.el-card__header .el-button {
    margin-left: 10px;
}
</style>