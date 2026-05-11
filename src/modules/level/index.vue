<template>
  <div class="level-container">
    <el-card class="box-card mb-4">
      <template #header>
        <div class="card-header">
          <span>级别管理</span>
          <el-button type="primary" @click="handleAdd">添加</el-button>
        </div>
      </template>
      <el-form :inline="true" v-model="searchForm" label-width="80px" class="mb-4">
        <el-form-item label="名称">
          <el-input v-model="searchForm.name" placeholder="请输入级别名称"></el-input>
        </el-form-item>
        <el-form-item label="排序值">
          <el-input type="number" v-model.number="searchForm.sortValue" placeholder="请输入排序值"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="级别名称" width="200" />
        <el-table-column prop="sortValue" label="排序值" width="120" />
        <el-table-column label="操作" width="300">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="showDeleteDialog(row.id, row.name)"></el-button>
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
      />
    </el-card>

    <el-dialog v-model="dialogVisible" :title="formTitle">
      <el-form :model="formData" ref="elFormRef" :rules="rules" tag-for-label label-width="100px">
        <el-form-item label="级别名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入级别名称"></el-input>
        </el-form-item>
        <el-form-item label="排序值" prop="sortValue">
          <el-input type="number" v-model.number="formData.sortValue" placeholder="请输入排序值"></el-input>
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
import getLevelList from '@/api/level'; // 请确保此路径正确

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  sortValue: null,
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  sortValue: null,
});

// Pagination State
const currentPage = ref(1);
const pageSize = ref(10);

// Form Refs and Rules
const elFormRef = ref(null);
const rules = reactive({
  name: [{ required: true, message: '级别名称不能为空', trigger: 'blur' }],
  sortValue: [{ required: true, message: '排序值不能为空', trigger: 'blur' }]
});

// --- Methods ---

const formTitle = ref('添加/编辑级别信息');

const loadData = async () => {
  loading.value = true;
  try {
    await getLevelList({ page: 1, pageSize: 10, search: {} });
    list.value = []; // 清空列表，等待API返回数据填充
    // 注意：这里假设 getLevelList 会直接设置 list 和 total，如果 API 返回结构不同，需要调整。
    // 为了符合纯代码要求，我们模拟一个调用并处理结果的流程。
    const result = await getLevelList({ page: 1, pageSize: 10, search: {} });
    list.value = result.data || []; // 假设返回结构有 data 字段
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  currentPage.value = 1;
  try {
    await getLevelList({ page: 1, pageSize: pageSize.value, search: searchForm });
    list.value = []; // 清空列表，等待API返回数据填充
    const result = await getLevelList({ page: 1, pageSize: pageSize.value, search: searchForm });
    list.value = result.data || [];
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('查询失败');
  } finally {
    loading.value = false;
  }
};

const handleReset = () => {
  searchForm.name = '';
  searchForm.sortValue = null;
  handleSearch(); // 重置后执行搜索
};

const handleSizeChange = (val) => {
  // 改变每页大小，重置到第一页并查询
  currentPage.value = 1;
  handleSearch();
};

const handleCurrentPageChange = (page) => {
  // 切换页码，执行查询
  handleSearch();
};


const handleAdd = () => {
  formData.id = null;
  formData.name = '';
  formData.sortValue = null;
  dialogVisible.value = true;
  formTitle.value = '添加级别信息';
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.id = row.id;
  formData.name = row.name;
  formData.sortValue = row.sortValue;
  dialogVisible.value = true;
  formTitle.value = '编辑级别信息';
};

const showDeleteDialog = (id, name) => {
  ElMessageBox.confirm(
    `确定要删除级别 "${name}" 吗?此操作不可恢复。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  ).then(async () => {
    try {
      await getLevelDetail({ id }); // 假设有删除API，这里用一个占位函数名
      ElMessage.success('删除成功');
      // 刷新列表
      handleSearch();
    } catch (error) {
      ElMessage.error('删除失败');
    }
  }).catch(() => {
    // 用户点击取消
  });
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
        apiCall = await getLevelUpdate({ id: formData.id, name: formData.name, sortValue: formData.sortValue }); // 假设的更新API
        successMessage = '编辑成功';
      } else {
        // 添加逻辑
        apiCall = await getLevelCreate({ name: formData.name, sortValue: formData.sortValue }); // 假设的创建API
        successMessage = '添加成功';
      }

      ElMessage.success(successMessage);
      dialogVisible.value = false;
      // 刷新列表，并重置表单（如果需要）
      handleSearch();
    } catch (error) {
      ElMessage.error('操作失败: ' + error.message);
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
.level-container {
  padding: 20px;
}
.box-card {
  margin-bottom: 20px;
}
.mb-4 {
  margin-bottom: 1rem !important;
}
</style>