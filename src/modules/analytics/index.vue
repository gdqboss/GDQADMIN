<template>
  <div class="analytics-container">
    <el-card class="header-card">
      <el-button type="primary" @click="handleAdd">添加</el-button>
      <el-input v-model="searchForm.name" placeholder="名称" style="width: 120px; margin-right: 10px;">
        <el-icon><Search /></el-icon>
      </el-input>
      <el-select v-model="searchForm.type" placeholder="类型" style="width: 150px; margin-right: 10px;">
        <el-option label="A类" value="A"></el-option>
        <el-option label="B类" value="B"></el-option>
      </el-select>
      <el-button type="primary" @click="handleSearch">查询</el-button>
    </el-card>

    <el-card class="table-card">
      <el-table :data="list" style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="名称" />
        <el-table-column prop="type" label="类型" />
        <el-table-column prop="value" label="值" />
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="showDeleteDialog(row.id, row.name)"></el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-pagination
      @size-change="handleSizeChange"
      @current-page-change="handlePageChange"
      :current-page="currentPage"
      :page-sizes="[10, 20, 50]"
      :page-size="pageSize"
      layout="bottom"
      adjust-size
    ></el-pagination>

    <el-dialog v-model="dialogVisible" :title="formData.id ? '编辑分析项' : '添加分析项'">
      <el-form :model="formData" ref="ruleFormRef" :rules="rules" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="formData.name" />
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择类型">
            <el-option label="A类" value="A"></el-option>
            <el-option label="B类" value="B"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="值" prop="value">
          <el-input v-model.number="formData.value" />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="$emit('cancel')">取消</el-button>
          <el-button type="primary" @click="dialogSubmit()">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Search } from '@element-plus/icons-vue';
import { ElMessageBox } from 'element-plus';

// 假设的 API 引入，请根据实际情况修改
import getAnalyticsList from '@/api/analytics/getAnalyticsList'; // 请替换为你的实际API路径
import createAnalyticsItem from '@/api/analytics/createAnalyticsItem';
import updateAnalyticsItem from '@/api/analytics/updateAnalyticsItem';
import deleteAnalyticsItem from '@/api/analytics/deleteAnalyticsItem';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  type: 'A', // 默认值
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  type: 'A',
  value: null,
});

// 分页和表单状态
const currentPage = ref(1);
const pageSize = ref(10);
const ruleFormRef = ref(null);

// 规则校验 (简化处理)
const rules = reactive({
  name: [
    { required: true, message: '名称不能为空', trigger: 'blur' },
  ],
  type: [
    { required: true, message: '类型不能为空', trigger: 'change' },
  ],
});

// --- 方法定义 ---

const loadData = async () => {
  await handleSearch();
};

const handleSearch = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      search: searchForm,
    };
    // 假设 API 返回结构包含 list 和 total
    const result = await getAnalyticsList(params); 
    list.value = result.data || [];
    total.value = result.total || 0;
  } catch (error) {
    console.error('加载数据失败:', error);
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  handleSearch();
};

const handlePageChange = (val) => {
  currentPage.value = val;
  handleSearch();
};

const resetForm = () => {
  formData.id = null;
  formData.name = '';
  formData.type = 'A';
  formData.value = null;
  ruleFormRef.value.resetFields();
};

const handleAdd = () => {
  resetForm();
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.id = row.id;
  formData.name = row.name;
  formData.type = row.type;
  formData.value = row.value;
  dialogVisible.value = true;
};

const showDeleteDialog = (id, name) => {
  ElMessageBox.confirm(
    `确定要删除分析项 "${name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  ).then(async () => {
    try {
      await deleteAnalyticsItem({ id });
      ElMessageBox.alert('删除成功！', '消息提示', { type: 'success' });
      handleSearch(); // 刷新列表
    } catch (error) {
      console.error('删除失败:', error);
      ElMessageBox.alert('删除失败，请重试。', '错误', { type: 'error' });
    }
  }).catch(() => {
    // 用户点击取消
  });
};

const dialogSubmit = async () => {
  ruleFormRef.value.validate(async (valid) => {
    if (!valid) {
      return false;
    }

    loading.value = true;
    try {
      let result;
      if (formData.id) {
        // 编辑逻辑
        result = await updateAnalyticsItem({ id: formData.id, ...formData });
        ElMessageBox.successNotification('编辑成功', '分析项信息已更新。');
      } else {
        // 添加逻辑
        result = await createAnalyticsItem({ ...formData });
        ElMessageBox.successNotification('添加成功', '新的分析项已创建。');
      }

      dialogVisible.value = false;
      handleSearch(); // 刷新列表
    } catch (error) {
      console.error('提交失败:', error);
      ElMessageBox.errorNotification('操作失败', '数据提交失败，请检查网络或参数。');
    } finally {
      loading.value = false;
    }
  });
};

// --- 生命周期钩子 ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.analytics-container {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.table-card {
  margin-bottom: 20px;
}

/* 确保 el-button 在操作列中样式良好 */
:deep(.el-table__row :deep(.el-button--link)) {
    margin-right: 10px;
}
</style>