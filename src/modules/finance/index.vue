<template>
  <div class="finance-container">
    <h2>财务管理</h2>

    <!-- 搜索表单 -->
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="查询条件">
          <el-select v-model="searchForm.dateRange" placeholder="选择日期范围"></el-select>
        </el-form-item>
        <el-form-item label="名称">
          <el-input v-model="searchForm.name" placeholder="请输入名称"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 数据表格 -->
    <el-card class="table-card" shadow="never">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <div>
          <el-button type="primary" @click="handleAdd">新增</el-button>
          <el-button @click="loadData(1, 10)">刷新列表</el-button>
        </div>
      </div>

      <el-table
        :data="list"
        v-loading="loading"
        style="width: 100%"
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="名称"></el-table-column>
        <el-table-column prop="amount" label="金额" width="150" align="right"></el-table-column>
        <el-table-column prop="status" label="状态">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'warning'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页组件 -->
      <el-pagination
        @size-change="handleSizeChange"
        @current-page-change="handleCurrentPageChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        layout="bottom"
        total="total"
        adjust-size
      ></el-pagination>
    </el-card>

    <!-- 添加/编辑弹窗 -->
    <el-dialog v-model="dialogVisible"       title="财务信息{{ isEdit ? '编辑' : '添加' }}"
      width="50%"
      :before-close="handleDialogClose"
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
        <el-form-item label="名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入名称"></el-input>
        </el-form-item>
        <el-form-item label="金额" prop="amount">
          <el-input type="number" v-model.number="formData.amount" placeholder="请输入金额"></el-input>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="formData.status" placeholder="请选择状态">
            <el-option label="启用" :value="1"></el-option>
            <el-option label="禁用" :value="0"></el-option>
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
// 假设 API 文件结构，如果不存在则使用通用请求API
import { getList, add, update, remove } from '@/api/finance'; // 请根据实际情况修改导入路径

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  dateRange: '',
  name: ''
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  amount: 0,
  status: 1 // 默认启用
});
const rules = reactive({
  name: (rule, value, callback) => {
    if (!value) {
      callback(new Error('名称不能为空'));
    } else {
      callback();
    }
  },
  amount: (rule, value, callback) => {
    if (value === null || value === undefined) {
        callback(); // 允许为空，根据业务调整
    } else if (isNaN(Number(value))) {
        callback(new Error('金额必须是数字'));
    } else {
        callback();
    }
  },
});

// 分页和搜索状态
const currentPage = ref(1);
const pageSize = ref(10);
const searchFormRef = ref(null);
const formDataRef = ref(null);

// --- 方法定义 ---

const loadData = async (page, size) => {
  loading.value = true;
  try {
    // 实际调用 API，使用对象参数
    const res = await getList({ page: page, pageSize: size, search: searchForm.value });
    list.value = res.data || [];
    total.value = res.total || 0;
  } catch (error) {
    console.error('加载数据失败:', error);
    ElMessage.error('加载财务数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  await loadData(currentPage.value, pageSize.value);
};

const resetSearch = () => {
  searchForm.dateRange = '';
  searchForm.name = '';
  handleSearch();
};

// 分页事件处理
const handleSizeChange = (val) => {
  pageSize.value = val;
  loadData(currentPage.value, pageSize.value);
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  loadData(currentPage.value, pageSize.value);
};

// 弹窗和表单操作
const handleAdd = () => {
  formData.id = null;
  formData.name = '';
  formData.amount = 0;
  formData.status = 1;
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据，注意这里需要处理ID的类型和是否禁用
  formData.id = row.id;
  formData.name = row.name;
  formData.amount = row.amount;
  formData.status = row.status;
  dialogVisible.value = true;
};

const handleDialogClose = () => {
    // 无论用户如何关闭弹窗，重置状态
    dialogVisible.value = false;
    Object.assign(formData, { id: null, name: '', amount: 0, status: 1 });
};


const dialogSubmit = async () => {
  try {
    formDataRef.value.validateModel();
    if (!formDataRef.value) return;

    let apiCall;
    let successMessage;

    // 判断是新增还是编辑 (根据ID是否存在或是否为null判断，这里假设ID存在则为编辑)
    if (formData.id) {
      apiCall = update({ id: formData.id, name: formData.name, amount: formData.amount, status: formData.status });
      successMessage = '编辑成功';
    } else {
      apiCall = add({ name: formData.name, amount: formData.amount, status: formData.status });
      successMessage = '添加成功';
    }

    await apiCall;
    ElMessage.success(successMessage);
    handleDialogClose();
    loadData(currentPage.value, pageSize.value); // 刷新列表
  } catch (error) {
    console.error('提交失败:', error);
    ElMessage.error('操作失败，请检查表单信息');
  }
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除 ${row.name} 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    await remove({ id: row.id });
    ElMessage.success('删除成功');
    loadData(currentPage.value, pageSize.value); // 刷新列表
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

// 表格选中行变化（如果需要根据选择项做额外处理）
const handleSelectionChange = (selection) => {
  console.log('选中的行:', selection);
};


// --- 生命周期钩子 ---
onMounted(() => {
  loadData(1, 10);
});

</script>

<style scoped>
.finance-container {
  padding: 20px;
}

/* 调整搜索卡片和表格的间距 */
.search-card {
  margin-bottom: 20px;
}

.table-card {
  margin-top: 10px;
}

/* 确保操作按钮在右侧对齐，如果需要更精细的布局可以调整此处的 flex/grid */
</style>