<template>
  <div class="inventory-container">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header-title">库存管理</div>
        <el-button type="primary" @click="handleAdd">添加</el-button>
      </template>
    </el-card>

    <!-- Search Form -->
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="商品名称">
          <el-input v-model="searchForm.name" placeholder="请输入商品名称"></el-input>
        </el-form-item>
        <el-form-item label="SKU">
          <el-input v-model="searchForm.sku" placeholder="请输入SKU号"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="loadData">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Table -->
    <el-card class="table-card" shadow="never">
      <el-table 
        :data="list" 
        v-loading="loading" 
        @selection-change="handleSelectionChange"
        stripe
        border
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="80"></el-table-column>
        <el-table-column prop="name" label="商品名称"></el-table-column>
        <el-table-column prop="sku" label="SKU号"></el-table-column>
        <el-table-column prop="stock" label="库存数量" width="120"></el-table-column>
        <el-table-column prop="supplier" label="供应商"></el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- Pagination -->
      <el-pagination
        @size-change="handleSizeChange"
        @current-page-change="handleCurrentPageChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        layout="total,bottom"
        total="total"
      ></el-pagination>
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible" 
      title="商品信息管理" 
      width="50%" 
      :before-close="handleDialogClose">
      <el-form 
        ref="formDataRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="4" 
        wrapper-foreground
      >
        <el-form-item label="商品名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入商品名称"></el-input>
        </el-form-item>
        <el-form-item label="SKU号" prop="sku">
          <el-input v-model="formData.sku" placeholder="请输入SKU号"></el-input>
        </el-form-item>
        <el-form-item label="库存数量" prop="stock">
          <el-input-number v-model="formData.stock" :min="0"></el-input-number>
        </el-form-item>
        <el-form-item label="供应商" prop="supplier">
          <el-select v-model="formData.supplier" placeholder="请选择供应商">
            <el-option label="A公司" value="A公司"></el-option>
            <el-option label="B科技" value="B科技"></el-option>
            <el-option label="C贸易" value="C贸易"></el-option>
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
// 假设 API 文件结构如下，请根据实际情况调整导入路径
import getInventoryList from "@/api/inventory"; 

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  sku: ''
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  sku: '',
  stock: 0,
  supplier: 'A公司'
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);
const searchFormRef = ref(null);
const formDataRef = ref(null);


// --- Methods ---

const handleSelectionChange = (val) => {
  console.log('Selected rows:', val);
};

const loadData = async () => {
  currentPage.value = 1;
  await fetchInventoryList();
};

const handleSearch = async () => {
  currentPage.value = 1;
  await fetchInventoryList();
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  // 当改变页大小后，重置到第一页并重新查询
  currentPage.value = 1;
  fetchInventoryList();
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  fetchInventoryList();
};

const fetchInventoryList = async () => {
  loading.value = true;
  try {
    // API 调用必须使用对象参数: getXxxList({ page, pageSize, search })
    await getInventoryList({ 
      page: currentPage.value, 
      pageSize: pageSize.value, 
      search: searchForm.value 
    });
    // 假设 API 返回的数据结构包含 list 和 total
    list.value = await getInventoryList({ page: currentPage.value, pageSize: pageSize.value, search: searchForm.value }).then(res => res.data || []);
    total.value = await getInventoryList({ page: currentPage.value, pageSize: pageSize.value, search: searchForm.value }).then(res => res.total || 0);

  } catch (error) {
    ElMessage.error('加载库存数据失败');
    console.error(error);
  } finally {
    loading.value = false;
  }
};


const handleAdd = () => {
  formData.id = null; // 清空ID，表示新增
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.id = row.id;
  formData.name = row.name;
  formData.sku = row.sku;
  formData.stock = row.stock;
  formData.supplier = row.supplier;
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除商品 [${row.name}] 吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    // 假设 API 调用 deleteItem(id)
    await getInventoryList({ id: row.id, action: 'delete' }); // 使用一个占位API调用，实际应是删除接口
    ElMessage.success('删除成功');
    handleSearch(); // 删除后刷新列表
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

const handleDialogClose = () => {
  dialogVisible.value = false;
  // 清空表单，防止误提交
  formData.id = null;
  formData.name = '';
  formData.sku = '';
  formData.stock = 0;
  formData.supplier = 'A公司';
};

const dialogSubmit = async () => {
  if (!searchFormRef.value || !formDataRef.value) return;

  // 简单的表单校验（Element Plus的v-model和rules已经处理了大部分）
  try {
    await formDataRef.value.validate();
    
    let apiCall;
    if (formData.id) {
      // 编辑逻辑：调用更新API
      apiCall = getInventoryList({ id: formData.id, name: formData.name, sku: formData.sku, stock: formData.stock, supplier: formData.supplier }); // 占位
    } else {
      // 添加逻辑：调用创建API
      apiCall = getInventoryList({ name: formData.name, sku: formData.sku, stock: formData.stock, supplier: formData.supplier }); // 占位
    }

    await apiCall; // 执行 API 调用
    ElMessage.success(formData.id ? '编辑成功' : '添加成功');
    handleSearch(); // 提交成功后刷新列表
    handleDialogClose();

  } catch (error) {
    console.error('Validation failed:', error);
  }
};


// --- Lifecycle Hooks ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.inventory-container {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.search-card {
  margin-bottom: 20px;
}

.table-card {
  /* 确保表格和分页组件在同一个卡片内，样式更统一 */
}

.dialog-footer :deep(.el-button--primary) {
    background-color: var(--el-color-primary);
    border-color: var(--el-color-primary);
}
</style>