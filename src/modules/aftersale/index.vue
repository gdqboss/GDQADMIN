<template>
  <div class="aftersale-container">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header-title">售后管理</div>
        <el-button type="primary" @click="handleNew">新增</el-button>
      </template>
    </el-card>

    <!-- Search Form -->
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="售后单号" prop="afterSaleNo">
          <el-input v-model="searchForm.afterSaleNo" placeholder="请输入售后单号"></el-input>
        </el-form-item>
        <el-form-item label="产品名称" prop="productName">
          <el-input v-model="searchForm.productName" placeholder="请输入产品名称"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
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
        <el-table-column type="selection" width="55" @change="handleSelectionChange" />
        <el-table-column prop="afterSaleNo" label="售后单号" width="180"></el-table-column>
        <el-table-column prop="productName" label="产品名称"></el-table-column>
        <el-table-column prop="reason" label="故障原因" min-width="200"></el-table-column>
        <el-table-column prop="status" label="处理状态">
          <template #default="{ row }">
            <el-tag :type="getStatusTag(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="$emit('edit', row)">编辑</el-button>
            <el-button link type="warning" @click="$emit('delete', row)">删除</el-button>
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
        layout="top right"
        total="total"
      ></el-pagination>
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible" 
      title="售后单信息管理" 
      width="60%" 
      :before-close="handleDialogClose">
      <el-form 
        :model="formData" 
        ref="elFormRef" 
        :rules="rules"
        label-for="afterSaleNo"
      >
        <el-form-item label="售后单号" prop="afterSaleNo">
          <el-input v-model="formData.afterSaleNo" placeholder="请输入售后单号"></el-input>
        </el-form-item>
        <el-form-item label="产品名称" prop="productName">
          <el-input v-model="formData.productName" placeholder="请输入产品名称"></el-input>
        </el-form-item>
        <el-form-item label="故障原因" prop="reason">
          <el-input v-model="formData.reason" type="textarea" placeholder="请输入详细的故障原因"></el-input>
        </el-form-item>
        <el-form-item label="处理状态" prop="status">
          <el-select v-model="formData.status" placeholder="请选择状态" clearable>
            <el-option label="待处理" value="0"></el-option>
            <el-option label="处理中" value="1"></el-option>
            <el-option label="已完成" value="2"></el-option>
          </el-select>
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="$emit('cancel')">取消</el-button>
          <el-button type="primary" @click="dialogSubmit">提交</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 路径是 '@/api/aftersale'，如果不存在则使用通用请求API
import { getList, addAftersale, updateAftersale, removeAftersale } from '@/api/aftersale';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  afterSaleNo: '',
  productName: '',
});
const dialogVisible = ref(false);
const formData = reactive({
  afterSaleNo: '',
  productName: '',
  reason: '',
  status: '0', // 默认值：待处理
});

// Element Plus Form Ref
const elFormRef = ref(null);

// Pagination State
const currentPage = ref(1);
const pageSize = ref(10);

// --- Methods ---

const getStatusTag = (status) => {
  switch (status) {
    case '0': return 'info'; // 待处理
    case '1': return 'warning'; // 处理中
    case '2': return 'success'; // 已完成
    default: return 'info';
  }
};

const getStatusText = (status) => {
  switch (status) {
    case '0': return '待处理';
    case '1': return '处理中';
    case '2': return '已完成';
    default: return '未知';
  }
};

// 1. Load Data / Search Handler
const loadData = async (params) => {
  loading.value = true;
  try {
    // 使用 API 调用，参数结构应匹配 getList 的期望
    const result = await getList({
      page: params.page || 1,
      pageSize: params.pageSize || 10,
      search: {
        afterSaleNo: searchForm.afterSaleNo,
        productName: searchForm.productName,
      }
    });

    list.value = result.data; // 假设 API 返回结构包含 data 数组
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载数据失败: ' + error.message);
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  currentPage.value = 1; // 查询时重置页码到第一页
  await loadData({ page: currentPage.value, pageSize: pageSize.value });
};

const handleReset = async () => {
  searchForm.afterSaleNo = '';
  searchForm.productName = '';
  currentPage.value = 1;
  await loadData({ page: currentPage.value, pageSize: pageSize.value });
};

// Pagination Change Handlers
const handleSizeChange = (val) => {
  pageSize.value = val;
  loadData({ page: currentPage.value, pageSize: val });
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  loadData({ page: val, pageSize: pageSize.value });
};


// 2. Dialog Handlers (Add/Edit)
const handleNew = () => {
  formData.afterSaleNo = '';
  formData.productName = '';
  formData.reason = '';
  formData.status = '0'; // 重置状态为待处理
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.afterSaleNo = row.afterSaleNo;
  formData.productName = row.productName;
  formData.reason = row.reason;
  formData.status = row.status;
  dialogVisible.value = true;
};

const handleDialogClose = () => {
  // 无论用户如何关闭，都重置表单状态（可选）
  elFormRef.value?.resetFields();
  dialogVisible.value = false;
};

const dialogSubmit = async () => {
  await elFormRef.value.validateElForm();
  if (!elFormRef.value) return;

  // 确定是新增还是编辑，这里简化处理为总是调用 update/add，实际应根据上下文判断
  const isEdit = !!formData.afterSaleNo && list.value.some(item => item.afterSaleNo === formData.afterSaleNo);

  try {
    if (isEdit) {
      await update({ ...formData, id: 'mockId' }); // 假设更新需要ID
      ElMessage.success('编辑成功');
    } else {
      await add(formData);
      ElMessage.success('新增成功');
    }
    handleSearch(); // 提交成功后刷新列表
    dialogVisible.value = false;
  } catch (error) {
    ElMessage.error('操作失败: ' + error.message);
  }
};

// 3. Delete Handler
const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `您确定要删除售后单 ${row.afterSaleNo} 吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    await remove(row.id || row.afterSaleNo); // 使用ID或单号作为删除参数
    ElMessage.success('删除成功');
    handleSearch(); // 删除成功后刷新列表
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

// 4. Selection Change Handler (用于批量操作，此处仅作占位)
const handleSelectionChange = (val) => {
  console.log('Selected rows:', val);
};


// --- Lifecycle Hooks ---
onMounted(() => {
  // 初始化加载数据（默认查询第一页）
  loadData({ page: 1, pageSize: 10 });
});

</script>

<style scoped>
.aftersale-container {
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