<template>
  <div class="product-management">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="title">产品管理</div>
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
          <el-button @click="loadData(1, 10)">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="never">
      <el-table
        :data="list"
        v-loading="loading"
        @selection-change="handleSelectionChange"
        stripe-width
        border
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="80"></el-table-column>
        <el-table-column prop="name" label="产品名称" width="200"></el-table-column>
        <el-table-column prop="sku" label="SKU" width="150"></el-table-column>
        <el-table-column prop="price" label="价格" width="120" align="right"></el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : row.status === 0 ? 'warning' : 'info'">{{ row.status === 1 ? '启用' : row.status === 0 ? '禁用' : '未知' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" align="center">
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
        :page-params="{ page: currentPage, size: pageSize }"
      ></el-pagination>
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" 
      title="产品信息" 
      width="50%" 
      :append-to-body="true"
    >
      <el-form 
        ref="productFormRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="3" 
        wrapper-offsetLeft="20" 
        wrapper-offsetTop="20"
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
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="formData.status">
            <el-radio-button label="1">启用</el-radio-button>
            <el-radio-button label="0">禁用</el-radio-button>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="$emit('close')">取消</el-button>
          <el-button type="primary" @click="dialogSubmit">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 文件结构如下，如果不存在则使用通用请求API
import { getList, add, update, remove } from '@/api/products'; // 请确保此路径存在或修改为实际的API导入

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
  status: '1' // 默认启用
});

// Pagination State
const currentPage = ref(1);
const pageSize = ref(10);

// Refs for forms and elements
const searchFormRef = ref(null);
const productFormRef = ref(null);


// --- Methods ---

const loadData = async (page, size) => {
  loading.value = true;
  try {
    // 假设 getList API 接受 page, pageSize, search 参数
    const result = await getList({
      page: page,
      pageSize: size,
      search: searchForm.value,
    });

    list.value = result.data || []; // 假设返回结构包含 data 数组
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载数据失败');
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  // 重置页码到第一页进行搜索
  currentPage.value = 1;
  await loadData(currentPage.value, pageSize.value);
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
  formData.price = row.price;
  formData.status = String(row.status); // 确保状态是字符串匹配radio-group
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定删除产品 [${row.name}] 吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    await remove({ id: row.id }); // 调用删除API
    ElMessage.success('删除成功');
    // 刷新列表，重新加载第一页数据
    currentPage.value = 1;
    await loadData(currentPage.value, pageSize.value);
  } catch (error) {
    ElMessage.error('删除失败');
  }
};

const dialogSubmit = async () => {
  if (!productFormRef.value.validateModel()) {
    return;
  }

  const isEdit = !!formData.id;
  let apiCall;
  let successMsg;

  try {
    if (isEdit) {
      // 调用更新API
      apiCall = await update({ id: formData.id, ...formData });
      successMsg = '编辑成功';
    } else {
      // 调用新增API
      apiCall = await add({ ...formData });
      successMsg = '添加成功';
    }

    ElMessage.success(successMsg);
    dialogVisible.value = false;
    // 刷新列表，重新加载第一页数据
    currentPage.value = 1;
    await loadData(currentPage.value, pageSize.value);

  } catch (error) {
    ElMessage.error('操作失败: ' + error.message || '请检查网络或参数');
  }
};


// --- Pagination Handlers ---

const handleSizeChange = (val) => {
  pageSize.value = val;
  currentPage.value = 1; // 改变页大小后，重置到第一页
  loadData(1, val);
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  // 如果是点击分页器切换页面，则不需要重新搜索，直接加载当前页数据
  loadData(page, pageSize.value);
};


// --- Lifecycle Hooks & Watchers ---

onMounted(() => {
  // 初始加载数据
  loadData(1, 10);
});

const handleSelectionChange = (val) => {
    console.log('Selected rows:', val);
}
</script>

<style scoped>
.product-management {
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

.title {
  font-size: 1.2em;
  font-weight: bold;
}

.dialog-footer :deep(.el-button--primary) {
    background-color: var(--el-color-primary);
    border-color: var(--el-color-primary);
}
</style>