<template>
  <div class="product-management">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="header-title">产品管理</div>
        <el-button type="primary" @click="handleAdd">新增产品</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="产品名称">
          <el-input v-model="searchForm.name" placeholder="请输入产品名称"></el-input>
        </el-form-item>
        <el-form-item label="SKU">
          <el-input v-model="searchForm.sku" placeholder="请输入SKU"></el-input>
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
        <el-table-column type="selection" width="55" @change="handleSelectionChange" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="产品名称" />
        <el-table-column prop="sku" label="SKU" />
        <el-table-column prop="price" label="价格" width="120" />
        <el-table-column prop="stock" label="库存" width="100%" />
        <el-table-column label="操作" width="300">
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
        layout="total, bottom"
        total="total"
      ></el-pagination>
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" 
      title="产品信息管理" 
      width="50%" 
      :append-to-body="true"
    >
      <el-form 
        :model="formData" 
        ref="productFormRef" 
        :rules="rules"
        label="产品名称" :required="true" prop="name">
        <el-form-item label="产品名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入产品名称"></el-input>
        </el-form-item>
        <el-form-item label="SKU" prop="sku">
          <el-input v-model="formData.sku" placeholder="请输入唯一SKU"></el-input>
        </el-form-item>
        <el-form-item label="价格" prop="price">
          <el-input type="number" v-model.number="formData.price" placeholder="请输入价格"></el-input>
        </el-form-item>
        <el-form-item label="库存" prop="stock">
          <el-input type="number" v-model.number="formData.stock" placeholder="请输入当前库存"></el-input>
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
import * as productApi from '@/api/product-new'; 

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
  price: 0,
  stock: 0
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Refs for forms and search
const productFormRef = ref(null);
const searchFormRef = ref(null);


// --- Methods ---

const loadData = async () => {
  currentPage.value = 1;
  await fetchData();
};

const handleSearch = async () => {
  currentPage.value = 1;
  await fetchData();
};

const handleAdd = () => {
  formData.id = null; // 清空ID，表示新增
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  Object.assign(formData, row); // 填充当前行数据到表单
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除产品 [${row.name}] 吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    await productApi.deleteProduct(row.id); // 假设 API 有 deleteProduct 方法
    ElMessage.success('删除成功');
    // 刷新列表，并重置搜索条件（或保持当前查询）
    searchForm.name = '';
    searchForm.sku = '';
    await fetchData();
  } catch (error) {
    ElMessage.error('删除失败');
  }
};

const dialogSubmit = async () => {
  // 1. 验证表单
  try {
    await productFormRef.value.validate();
  } catch (error) {
    console.log('Validation failed', error);
    return;
  }

  loading.value = true;
  let apiCall;
  let successMessage;

  if (!formData.id) {
    // 新增逻辑
    apiCall = productApi.createProduct(formData); // 假设 API 有 createProduct 方法
    successMessage = '新增成功';
  } else {
    // 编辑逻辑
    apiCall = productApi.updateProduct(formData.id, formData); // 假设 API 有 updateProduct 方法
    successMessage = '编辑成功';
  }

  try {
    await apiCall;
    ElMessage.success(`${successMessage}`);
    dialogVisible.value = false;
    // 清空表单数据，并刷新列表
    Object.assign(formData, { id: null, name: '', sku: '', price: 0, stock: 0 });
    searchForm.name = '';
    searchForm.sku = '';
    await fetchData();

  } catch (error) {
    ElMessage.error('操作失败，请检查网络或数据。');
  } finally {
    loading.value = false;
  }
};


// --- Pagination Handlers ---

const handleSizeChange = (val) => {
  pageSize.value = val;
  loadData();
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  fetchData();
};

// --- Selection Handler (Optional, but good practice) ---
const handleSelectionChange = (val) => {
    console.log('Selected rows:', val);
}


// --- Data Fetching Logic ---

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      search: searchForm // 传递搜索条件对象
    };
    // 调用 API，使用 object 参数
    const result = await productApi.getProductList(params); 
    list.value = result.data || [];
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载数据失败');
    console.error(error);
  } finally {
    loading.value = false;
  }
};

// --- Lifecycle Hook ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.product-management {
  padding: 20px;
}

.header-card, .search-card, .table-card {
  margin-bottom: 20px;
}

.header-title {
  font-size: 1.5em;
  font-weight: bold;
}

/* Dialog Footer Styling */
.dialog-footer {
    padding-right: 16px;
}
</style>