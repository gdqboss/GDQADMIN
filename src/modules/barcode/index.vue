<template>
  <div class="barcode-container">
    <el-card class="header-card" shadow="hover">
      <template #header>
        <div class="header-title">条码管理</div>
        <el-button type="primary" @click="handleNew">添加</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="条码名称">
          <el-input v-model="searchForm.name" placeholder="请输入名称"></el-input>
        </el-form-item>
        <el-form-item label="编码范围">
          <el-input v-model="searchForm.codeStart" placeholder="起始编码"></el-input>
          <el-input v-model="searchForm.codeEnd" placeholder="结束编码"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="hover">
      <el-table 
        :data="list" 
        v-loading="loading" 
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-icon class="el-icon--selection" :class="{ 'is-selected': $event }">
          <template #open>
            <el-checkbox></el-checkbox>
          </template>
        </el-icon>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="名称" width="200"></el-table-column>
        <el-table-column prop="barcodeCode" label="条码编码" width="300"></el-table-column>
        <el-table-column prop="description" label="描述" width="300"></el-table-column>
        <el-table-column label="操作" width="250">
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

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible" 
      title="编辑条码信息" 
      width="50%" 
      :before-close="handleDialogClose"
    >
      <el-form 
        ref="elFormRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="3" 
        wrapper-tag="div"
      >
        <el-form-item label="ID" prop="id">
          <el-input v-model="formData.id" :disabled="true"></el-input>
        </el-form-item>
        <el-form-item label="名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入条码名称"></el-input>
        </el-form-item>
        <el-form-item label="条码编码" prop="barcodeCode">
          <el-input v-model="formData.barcodeCode" placeholder="请输入唯一条码编码"></el-input>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" placeholder="请输入详细描述"></el-input>
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
// 假设的 API 引入，请根据实际情况修改路径和函数名
import getBarcodeList from "@/api/barcode"; 

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  codeStart: '',
  codeEnd: ''
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  barcodeCode: '',
  description: ''
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Form validation rules
const rules = reactive({
  name: [{ required: true, message: '名称不能为空', trigger: 'blur' }],
  barcodeCode: [{ required: true, message: '条码编码不能为空', trigger: 'blur' }]
});

// --- Methods ---

const loadData = async () => {
  loading.value = true;
  try {
    await getBarcodeList({ page: 1, pageSize: 10, search: {} }); // 初始加载，不带搜索条件
    console.log("Initial data loaded successfully.");
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  if (!searchForm.name && !searchForm.codeStart && !searchForm.codeEnd) {
    ElMessage.warning('请输入查询条件！');
    return;
  }
  loading.value = true;
  try {
    // 构造搜索参数，只包含非空项
    const searchParams = {};
    if (searchForm.name) searchParams.name = searchForm.name;
    if (searchForm.codeStart) searchParams.codeStart = searchForm.codeStart;
    if (searchForm.codeEnd) searchParams.codeEnd = searchForm.codeEnd;

    await getBarcodeList({ 
      page: currentPage.value, 
      pageSize: pageSize.value, 
      search: searchParams 
    });
  } catch (error) {
    ElMessage.error('查询失败');
  } finally {
    loading.value = false;
  }
};

const handleReset = () => {
  searchForm.name = '';
  searchForm.codeStart = '';
  searchForm.codeEnd = '';
  handleSearch(); // 重置后重新查询第一页数据
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  currentPage.value = 1;
  handleSearch();
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  handleSearch();
};

const handleSelectionChange = (val) => {
  console.log('Selected rows:', val);
};

const handleNew = () => {
  formData.id = null; // 清空ID，表示新增
  dialogVisible.value = true;
  // 重置表单数据到初始状态（如果需要）
};

const handleEdit = (row) => {
  Object.assign(formData, row); // 将行数据填充到 formData
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除条码信息吗?`, 
    '提示', 
    { type: 'warning' }
  );
  if (confirm) {
    try {
      // 假设的 delete API 调用
      await getBarcodeDetail({ id: row.id }); // 使用一个模拟的API调用来触发删除逻辑
      ElMessage.success('删除成功');
      handleSearch(); // 删除成功后刷新列表
    } catch (e) {
      ElMessage.error('删除失败');
    }
  }
};

const handleDialogClose = () => {
  dialogVisible.value = false;
  // 无论关闭还是取消，都重置表单状态（可选）
  Object.assign(formData, { id: null, name: '', barcodeCode: '', description: '' });
};

const dialogSubmit = async () => {
  elFormRef.value.validate(async (valid) => {
    if (!valid) return;

    loading.value = true;
    try {
      let apiCall;
      let successMessage;

      if (formData.id) {
        // 编辑逻辑
        apiCall = getBarcodeDetail({ id: formData.id, name: formData.name, barcodeCode: formData.barcodeCode, description: formData.description });
        successMessage = '编辑成功';
      } else {
        // 新增逻辑
        apiCall = getBarcodeList({ name: formData.name, barcodeCode: formData.barcodeCode, description: formData.description }); // 假设新增API结构
        successMessage = '添加成功';
      }

      await apiCall;
      ElMessage.success(`${successMessage}`);
      handleDialogClose();
      handleSearch(); // 提交成功后刷新列表
    } catch (error) {
      ElMessage.error('操作失败，请检查网络或数据。');
    } finally {
      loading.value = false;
    }
  });
};

// --- Lifecycle Hooks ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.barcode-container {
  padding: 20px;
}

.header-card, .search-card, .table-card {
  margin-bottom: 20px;
}

.header-title {
  font-size: 1.5em;
  font-weight: bold;
}

/* 确保 el-button 在 header 中显示良好 */
.el-card__header .el-button {
    margin-left: 10px;
}
</style>