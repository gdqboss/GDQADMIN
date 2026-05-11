<template>
  <div class="product-skus-container">
    <el-card class="header-card">
      <template #header>
        <div class="card-header-title">产品SKU管理</div>
        <el-button type="primary" @click="handleAdd">添加</el-button>
      </template>
    </el-card>

    <el-card class="search-card">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="SKU名称">
          <el-input v-model="searchForm.skuName" placeholder="请输入SKU名称"></el-input>
        </el-form-item>
        <el-form-item label="产品ID">
          <el-input v-model="searchForm.productId" placeholder="请输入产品ID"></el-input>
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
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        
        <el-table-column type="selection" width="55" />
        <el-table-column prop="skuId" label="SKU ID" width="120" />
        <el-table-column prop="productName" label="产品名称" />
        <el-table-column prop="skuName" label="SKU 名称" />
        <el-table-column prop="skuCode" label="SKU 编码" />
        <el-table-column prop="stockQuantity" label="库存数量" width="120" />
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="handleDelete(row.skuId, row.skuName)">删除</el-button>
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
      ></el-pagination>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="formTitle">
      <el-form 
        :model="formData" 
        ref="elFormRef" 
        :rules="rules"
        label-col-span="3"
        label-width="100px"
      >
        <el-form-item label="产品ID" prop="productId">
          <el-input v-model="formData.productId" />
        </el-form-item>
        <el-form-item label="SKU名称" prop="skuName">
          <el-input v-model="formData.skuName" />
        </el-form-item>
        <el-form-item label="SKU编码" prop="skuCode">
          <el-input v-model="formData.skuCode" />
        </el-form-item>
        <el-form-item label="库存数量" prop="stockQuantity">
          <el-input-number v-model.lazy="formData.stockQuantity" :min="0"></el-input-number>
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
import { Plus } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 文件结构如下，请根据实际情况调整导入路径和函数名
import { getProductSkusList, createProductSku, updateProductSku, deleteProductSku } from '@/api/product-skus';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  skuName: '',
  productId: ''
});
const dialogVisible = ref(false);
const formData = reactive({
  skuId: null,
  productId: '',
  skuName: '',
  skuCode: '',
  stockQuantity: 0
});
const formTitle = ref('添加SKU');

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);
const searchFormRef = ref(null);

// Rules for validation
const rules = reactive({
  skuName: [{ required: true, message: '请输入SKU名称', trigger: 'blur' }],
  productId: [{ required: true, message: '请输入产品ID', trigger: 'blur' }],
  skuCode: [{ required: true, message: '请输入SKU编码', trigger: 'blur' }],
  stockQuantity: [{ required: true, message: '请输入库存数量', trigger: 'change' }]
});

// --- Methods ---

const loadData = async () => {
  loading.value = true;
  currentPage.value = 1;
  searchForm.skuName = '';
  searchForm.productId = '';
  await fetchList();
};

const handleSearch = async () => {
  if (!searchForm.skuName && !searchForm.productId) {
    ElMessage.warning('请输入查询条件');
    return;
  }
  currentPage.value = 1;
  await fetchList();
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  currentPage.value = 1;
  fetchList();
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  fetchList();
};

// Core API call function
const fetchList = async () => {
  loading.value = true;
  try {
    await getProductSkusList({
      page: currentPage.value,
      pageSize: pageSize.value,
      search: searchForm
    });
    // 假设 getProductSkusList 返回了 { list: [...], total: N } 的结构
    list.value = await getProductSkusList({ page: currentPage.value, pageSize: pageSize.value, search: searchForm }); // 实际应处理API返回的列表数据
    total.value = 100; // 模拟总数，请替换为API返回值
  } catch (error) {
    ElMessage.error('加载SKU列表失败');
  } finally {
    loading.value = false;
  }
};

const handleAdd = () => {
  formData.skuId = null; // 清空ID，表示新增
  formTitle.value = '添加SKU';
  // 重置表单数据到默认值（或根据业务逻辑设置）
  formData.productId = '';
  formData.skuName = '';
  formData.skuCode = '';
  formData.stockQuantity = 0;
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.skuId = row.skuId;
  formData.productId = row.productId;
  formData.skuName = row.skuName;
  formData.skuCode = row.skuCode;
  formData.stockQuantity = row.stockQuantity;
  formTitle.value = '编辑SKU';
  dialogVisible.value = true;
};

const handleDelete = async (id, name) => {
  await ElMessageBox.confirm(
    `确定删除 ${name} (ID: ${id}) 的记录吗?此操作不可恢复。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  if (confirm) {
    try {
      await deleteProductSku(id); // 调用删除API
      ElMessage.success('删除成功');
      // 刷新列表
      handleSearch();
    } catch (error) {
      ElMessage.error('删除失败');
    }
  }
};

const dialogSubmit = async () => {
  await elFormRef.value.validateElForm();
  if (!elFormRef.value) return;

  try {
    let result;
    if (formData.skuId) {
      // 编辑逻辑
      result = await updateProductSku(formData);
      ElMessage.success('编辑成功');
    } else {
      // 添加逻辑
      result = await createProductSku(formData);
      ElMessage.success('添加成功');
    }
    dialogVisible.value = false;
    handleSearch(); // 提交后刷新列表
  } catch (error) {
    ElMessage.error('操作失败，请检查网络或数据。');
  }
};

// --- Selection Handling (Optional but good practice) ---
const handleSelectionChange = (selection) => {
  console.log('选中的行:', selection);
};


onMounted(() => {
  loadData();
});
</script>

<style scoped>
.product-skus-container {
  padding: 20px;
}

.header-card, .search-card, .table-card {
  margin-bottom: 20px;
}

/* 调整 el-button 在 header 中的样式 */
.el-card__header > div:last-child {
    margin-left: auto; /* 将按钮推到右侧 */
}

/* 确保表单布局美观 */
.el-form-item {
  margin-right: 20px;
}

.dialog-footer {
  text-align: right;
}
</style>