<template>
  <div class="point-management">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header-title">积分管理</div>
        <el-button type="primary" @click="handleAdd">添加</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="积分名称">
          <el-input v-model="searchForm.name" placeholder="请输入积分名称"></el-input>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="searchForm.type" placeholder="请选择类型">
            <el-option label="消费" value="CONSUME"></el-option>
            <el-option label="奖励" value="REWARD"></el-option>
            <el-option label="其他" value="OTHER"></el-option>
          </el-select>
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
        style="width: 100%" 
        stripe 
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="积分名称"></el-table-column>
        <el-table-column prop="type" label="类型" width="120">
          <template #default="{ row }">
            <el-tag :type="{ 'success': row.type === 'REWARD', 'warning': row.type === 'CONSUME' }">{{ row.type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="描述"></el-table-column>
        <el-table-column prop="createTime" label="创建时间" width="180"></el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row.id, row.name)"></el-button>
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
        adjust-size
      ></el-pagination>
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible" 
      :title="isEdit ? '编辑积分信息' : '添加新积分类型'" 
      width="50%" 
      @close="resetDialog">
      <el-form 
        ref="dialogFormRef" 
        :model="formData" 
        :rules="rules" 
        label-col="{ labelWidth: '100px' }">
        <el-form-item label="积分名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入积分名称"></el-input>
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择类型" clearable>
            <el-option label="消费" value="CONSUME"></el-option>
            <el-option label="奖励" value="REWARD"></el-option>
            <el-option label="其他" value="OTHER"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" placeholder="请输入积分描述"></el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" @click="dialogSubmit">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 文件结构为 @/api/point
import { getPointList, createPoint, updatePoint, deletePoint } from '@/api/point';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  type: 'CONSUME', // 默认值
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  type: 'CONSUME',
  description: '',
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);
const searchFormRef = ref(null); // 用于表单引用

// --- Methods ---

const loadData = async () => {
  loading.value = true;
  currentPage.value = 1;
  await fetchData();
};

const handleSearch = async () => {
  if (!searchFormRef.value) return;
  await fetchData();
};

const fetchData = async (page = currentPage.value, size = pageSize.value, searchParams = {}) => {
  loading.value = true;
  try {
    // 构造 API 参数，包含搜索条件和分页信息
    const params = {
      page: page,
      pageSize: size,
      search: {
        name: searchParams.name || '',
        type: searchParams.type || 'CONSUME',
      }
    };

    // 实际调用 API，这里假设 getPointList 支持传入对象参数
    const result = await getPointList({ ...params }); 
    
    list.value = result.data; // 假设返回结构包含 data 数组
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载数据失败: ' + error.message);
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (size) => {
  pageSize.value = size;
  currentPage.value = 1;
  fetchData(1, size, searchForm.value);
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  fetchData(page, pageSize.value, searchForm.value);
};

// --- Dialog Handlers ---

const resetDialog = () => {
  dialogVisible.value = false;
  formData.id = null;
  formData.name = '';
  formData.type = 'CONSUME';
  formData.description = '';
  Object.getValue(searchForm).name = ''; // 清空搜索表单部分字段
};

const handleAdd = () => {
  resetDialog();
  dialogVisible.value = true;
  // 确保新添加时，formData是干净的初始状态
  formData.id = null; 
};

const handleEdit = (row) => {
  Object.assign(formData, row); // 将当前行数据填充到 formData
  dialogVisible.value = true;
  // 如果需要，可以在这里设置一个标志位来区分是编辑还是新增
};

const dialogSubmit = async () => {
  if (!searchFormRef.value) return;
  await searchFormRef.value.validateModel();

  try {
    let result;
    if (formData.id) {
      // 编辑逻辑
      result = await updatePoint({ id: formData.id, name: formData.name, type: formData.type, description: formData.description });
      ElMessage.success('编辑成功');
    } else {
      // 添加逻辑
      result = await createPoint({ name: formData.name, type: formData.type, description: formData.description });
      ElMessage.success('添加成功');
    }

    resetDialog();
    await loadData(); // 刷新列表数据
  } catch (error) {
    ElMessage.error('操作失败: ' + error.message);
  }
};

// --- Delete Handler ---

const handleDelete = async (id, name) => {
  await ElMessageBox.confirm(
    `确定要删除积分类型 "${name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );

  try {
    await deletePoint({ id }); // 调用删除 API
    ElMessage.success('删除成功');
    // 刷新列表，并重置搜索条件（可选）
    searchForm.name = '';
    searchForm.type = 'CONSUME';
    loadData();
  } catch (error) {
    ElMessage.error('删除失败: ' + error.message);
  }
};

// --- Selection Change Handler ---
const handleSelectionChange = (selection) => {
  console.log('Selected rows:', selection);
};


onMounted(() => {
  loadData();
});
</script>

<style scoped>
.point-management {
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
  margin-top: 10px;
}

/* Dialog Footer 调整 */
.dialog-footer {
  padding-right: 16px;
}
</style>