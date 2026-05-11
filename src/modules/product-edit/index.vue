<template>
  <div class="product-edit">
    <el-card class="header-card">
      <template #header>
        <div class="header-title">产品管理</div>
        <el-button type="primary" @click="handleAdd">添加产品</el-button>
      </template>
    </el-card>

    <el-card class="search-card">
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

    <el-card class="table-card">
      <el-table 
        :data="list" 
        style="width: 100%" 
        v-loading="loading"
        @selection-change="handleSelectionChange"
      >
        
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="产品名称"></el-table-column>
        <el-table-column prop="sku" label="SKU"></el-table-column>
        <el-table-column prop="price" label="价格" width="120" />
        <el-table-column prop="stock" label="库存" width="120" />
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
      title="产品信息" 
      width="50%" 
      :append-to-body="true"
    >
      <el-form 
        ref="formDataRef" 
        :model="formData" 
        :rules="rules" 
        label-width="100px"
      >
        <el-form-item label="产品名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入产品名称"></el-input>
        </el-form-item>
        <el-form-item label="SKU" prop="sku">
          <el-input v-model="formData.sku" placeholder="请输入SKU"></el-input>
        </el-form-item>
        <el-form-item label="价格" prop="price">
          <el-input type="number" v-model.number="formData.price" placeholder="请输入价格"></el-input>
        </el-form-item>
        <el-form-item label="库存" prop="stock">
          <el-input type="number" v-model.number="formData.stock" placeholder="请输入库存"></el-input>
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="$emit('close')">取消</el-button>
          <el-button type="primary" @click="dialogSubmit">确认提交</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Plus } from '@element-plus/icons-vue';
import { ElMessageBox } from 'element-plus';

// 引入 API (假设 product-edit.js 存在)
import getProductList from '@/api/product-edit/getProductList';
import createProduct from '@/api/product-edit/createProduct';
import updateProduct from '@/api/product-edit/updateProduct';
import deleteProductItem from '@/api/product-edit/deleteProductItem';

// --- 状态管理 ---
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

// --- 表单和分页引用 ---
const searchFormRef = ref(null);
const formDataRef = ref(null);

// --- 分页状态 ---
const currentPage = ref(1);
const pageSize = ref(10);

// --- 方法定义 ---

const loadData = async () => {
  currentPage.value = 1;
  await handleSearch();
};

const handleSearch = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      search: searchForm // API期望的参数结构
    };
    // 假设 getProductList 接收 { page, pageSize, search } 格式的对象
    const result = await getProductList({ 
        page: currentPage.value, 
        pageSize: pageSize.value, 
        search: searchForm 
    });
    list.value = result.data || [];
    total.value = result.total || 0;
  } catch (error) {
    console.error('查询产品列表失败:', error);
    ElMessageBox.alert('查询失败，请检查网络或API配置。', '错误');
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  handleSearch();
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  handleSearch();
};

// --- CRUD 操作 ---

const handleAdd = () => {
  formData.id = null; // 清空ID，表示新增
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  Object.assign(formData, row); // 将行数据填充到表单模型
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定删除产品 [${row.name}] 吗? 此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteProductItem({ id: row.id }); // 假设API接收 { id }
    ElMessageBox.alert('删除成功！', '成功');
    handleSearch(); // 删除后刷新列表
  } catch (error) {
    console.error('删除失败:', error);
    ElMessageBox.alert('删除失败，请重试。', '错误');
  }
};

const handleSelectionChange = (val) => {
  // 如果需要根据选中项执行操作，可以在这里处理
};


const dialogSubmit = async () => {
  if (!formDataRef.value || !formDataRef.value.validateModel) {
    return;
  }

  try {
    let result;
    if (formData.id) {
      // 编辑逻辑
      result = await updateProduct({ id: formData.id, ...formData });
      ElMessageBox.alert('编辑成功！', '成功');
    } else {
      // 添加逻辑
      result = await createProduct({ ...formData });
      ElMessageBox.alert('添加成功！', '成功');
    }
    
    dialogVisible.value = false;
    handleSearch(); // 提交成功后刷新列表
  } catch (error) {
    console.error('提交失败:', error);
    ElMessageBox.alert('操作失败，请检查表单数据和网络连接。', '错误');
  }
};

// --- 生命周期钩子 ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.product-edit {
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

.dialog-footer :deep(.el-button--primary) {
    background-color: var(--el-color-primary);
    border-color: var(--el-color-primary);
}
</style>