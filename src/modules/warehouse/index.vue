<template>
  <div class="warehouse-container">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="header-title">仓库管理</div>
        <el-button type="primary" @click="handleAdd">添加仓库</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="仓库名称">
          <el-input v-model="searchForm.name" placeholder="请输入仓库名称"></el-input>
        </el-form-item>
        <el-form-item label="仓库编码">
          <el-input v-model="searchForm.code" placeholder="请输入仓库编码"></el-input>
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
        style="width: 100%" 
        stripe 
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="80"></el-table-column>
        <el-table-column prop="name" label="仓库名称" width="200"></el-table-column>
        <el-table-column prop="code" label="仓库编码" width="150"></el-table-column>
        <el-table-column prop="address" label="地址" width="300"></el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="handleDelete(row)">删除</el-button>
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

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" 
      title="仓库信息管理" 
      width="50%" 
      :append-to-body="true"
    >
      <el-form 
        ref="formDataRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="4" 
        label-width="100px"
      >
        <el-form-item label="ID" prop="id">
          <el-input v-model="formData.id" :disabled="true"></el-input>
        </el-form-item>
        <el-form-item label="仓库名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入仓库名称"></el-input>
        </el-form-item>
        <el-form-item label="仓库编码" prop="code">
          <el-input v-model="formData.code" placeholder="请输入仓库编码"></el-input>
        </el-form-item>
        <el-form-item label="地址" prop="address">
          <el-input v-model="formData.address" placeholder="请输入详细地址"></el-input>
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
import getWarehouseList from "@/api/warehouse/getWarehouseList"; // 请替换为你的实际API调用函数名
import createWarehouse from "@/api/warehouse/createWarehouse";
import updateWarehouse from "@/api/warehouse/updateWarehouse";
import deleteWarehouse from "@/api/warehouse/deleteWarehouse";

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  code: ''
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  code: '',
  address: ''
});

// Element Plus Form Refs
const searchFormRef = ref(null);
const formDataRef = ref(null);

// Pagination State
const currentPage = ref(1);
const pageSize = ref(10);

// Validation Rules
const rules = reactive({
  name: [{ required: true, message: '仓库名称不能为空', trigger: 'blur' }],
  code: [{ required: true, message: '仓库编码不能为空', trigger: 'blur' }],
  address: []
});

// --- Methods ---

const loadData = async () => {
  loading.value = true;
  try {
    await getWarehouseList({ page: 1, pageSize: 10, search: {} }); // 初始加载，不带搜索条件
    console.log("Initial data loaded successfully.");
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  loading.value = true;
  try {
    await getWarehouseList({ 
      page: 1, 
      pageSize: pageSize.value, 
      search: searchForm 
    });
    console.log("Search executed successfully.");
  } catch (error) {
    ElMessage.error('查询失败');
  } finally {
    loading.value = false;
  }
};

const resetSearch = () => {
  searchForm.name = '';
  searchForm.code = '';
  handleSearch(); // 重置后重新加载数据
};

const handleAdd = () => {
  formData.id = null;
  formData.name = '';
  formData.code = '';
  formData.address = '';
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.id = row.id;
  formData.name = row.name;
  formData.code = row.code;
  formData.address = row.address;
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除仓库 "${row.name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteWarehouse(row.id); // 调用API删除
    ElMessage.success('删除成功');
    // 刷新列表（这里需要重新调用一次查询或加载数据）
    handleSearch(); 
  } catch (error) {
    ElMessage.error('删除失败');
  }
};

const dialogSubmit = async () => {
  formDataRef.value.validate(async (valid) => {
    if (!valid) return;

    loading.value = true;
    try {
      let apiCall;
      // 简单的判断：如果ID存在，认为是编辑；否则是新增
      if (formData.id) {
        apiCall = updateWarehouse(formData); // 编辑API调用
        ElMessage.info('正在更新...');
      } else {
        apiCall = createWarehouse(formData); // 新增API调用
        ElMessage.info('正在添加...');
      }

      await apiCall; 
      ElMessage.success(`${formData.name} ${formData.id ? '编辑' : '添加'}成功!`);
      dialogVisible.value = false;
      // 刷新列表
      handleSearch(); 

    } catch (error) {
      console.error(error);
      ElMessage.error('提交失败，请检查网络或数据。');
    } finally {
      loading.value = false;
    }
  });
};


// --- Pagination Handlers ---

const handleSizeChange = (val) => {
  pageSize.value = val;
  handleSearch(); // 改变页大小后，重新查询第一页
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  handleSearch();
};

// --- Selection Handler (If needed for bulk actions) ---
const handleSelectionChange = (selection) => {
  console.log('Selected rows:', selection);
};


// --- Lifecycle Hooks ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.warehouse-container {
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
.el-form-item :deep(.el-button) {
    margin-right: 10px;
}
</style>