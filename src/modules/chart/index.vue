<template>
  <div class="chart-management">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header-title">图表管理</div>
        <el-button type="primary" @click="handleAdd">新增</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="图表名称">
          <el-input v-model="searchForm.name" placeholder="请输入图表名称"></el-input>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="searchForm.type" placeholder="请选择图表类型">
            <el-option label="柱状图" value="bar"></el-option>
            <el-option label="折线图" value="line"></el-option>
            <el-option label="饼图" value="pie"></el-option>
          </el-select>
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
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="图表名称" width="200"></el-table-column>
        <el-table-column prop="type" label="类型" width="150"></el-table-column>
        <el-table-column prop="description" label="描述" width="300"></el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row.id, row.name)">删除</el-button>
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
      title="图表信息管理" 
      width="50%" 
      :before-close="handleDialogClose">
      <el-form 
        ref="formDataRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="3" 
        wrapper-impact>
        <el-form-item label="图表名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入图表名称"></el-input>
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择图表类型">
            <el-option label="柱状图" value="bar"></el-option>
            <el-option label="折线图" value="line"></el-option>
            <el-option label="饼图" value="pie"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" placeholder="请输入图表描述"></el-input>
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="$emit('cancel')">取消</el-button>
          <el-button type="primary" @click="dialogSubmit">确认提交</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 文件结构为 @/api/chart.js，并导出 getChartList, createChart, updateChart, deleteChart 等函数
import { getChartList, createChart, updateChart, deleteChart } from '@/api/chart';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  type: '',
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  type: 'bar',
  description: ''
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Form Refs
const searchFormRef = ref(null);
const formDataRef = ref(null);

// Rules for validation
const rules = reactive({
  name: [
    { required: true, message: '图表名称不能为空', trigger: 'blur' },
  ],
  type: [
    { required: true, message: '请选择图表类型', trigger: 'change' },
  ],
  description: [],
});

// --- Methods ---

const loadData = async () => {
  loading.value = true;
  try {
    await getChartList({ page: 1, pageSize: 10, search: {} }); // 初始加载，不带搜索条件
    console.log('Initial data loaded');
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  loading.value = true;
  try {
    await getChartList({ 
      page: 1, 
      pageSize: pageSize.value, 
      search: searchForm 
    });
    console.log('Search executed');
  } catch (error) {
    ElMessage.error('查询失败');
  } finally {
    loading.value = false;
  }
};

const resetSearch = () => {
  searchForm.name = '';
  searchForm.type = '';
  handleSearch(); // 重置后执行搜索
};

const handleAdd = () => {
  formData.id = null;
  formData.name = '';
  formData.type = 'bar';
  formData.description = '';
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.id = row.id;
  formData.name = row.name;
  formData.type = row.type;
  formData.description = row.description;
  dialogVisible.value = true;
};

const handleDelete = async (id, name) => {
  await ElMessageBox.confirm(
    `确定要删除图表 "${name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteChart(id);
    ElMessage.success('删除成功');
    // 刷新列表（这里需要重新调用一次加载或搜索）
    handleSearch(); 
  } catch (error) {
    ElMessage.error('删除失败');
  }
};

const handleSelectionChange = (val) => {
  console.log('Selected rows:', val);
};

// Pagination Handlers
const handleSizeChange = (size) => {
  pageSize.value = size;
  handleSearch();
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  handleSearch();
};

// Dialog Submission
const handleDialogClose = () => {
  dialogVisible.value = false;
  // 清空表单，防止误操作残留数据
  formData.id = null;
  formData.name = '';
  formData.type = 'bar';
  formData.description = '';
};

const dialogSubmit = async () => {
  if (!searchFormRef.value || !formDataRef.value) return;

  // 触发表单验证
  await formDataRef.value.$refs.el-form.validate();

  try {
    let result;
    if (formData.id) {
      // 编辑逻辑
      result = await updateChart(formData);
      ElMessage.success('编辑成功');
    } else {
      // 新增逻辑
      result = await createChart(formData);
      ElMessage.success('新增成功');
    }
    
    dialogVisible.value = false;
    handleSearch(); // 提交成功后刷新列表
  } catch (error) {
    ElMessage.error('操作失败，请检查网络或数据。');
  }
};

// --- Lifecycle Hooks ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.chart-management {
  padding: 20px;
}

/* 头部卡片样式 */
.header-card {
  margin-bottom: 20px;
}

/* 搜索卡片样式 */
.search-card {
  margin-bottom: 20px;
}

/* 表格卡片样式 */
.table-card {
  padding-top: 10px;
}

/* Dialog Footer 调整 */
.dialog-footer {
  text-align: right;
}
</style>