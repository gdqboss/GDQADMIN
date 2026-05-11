<template>
  <el-card class="sms-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2>短信管理</h2>
      <el-button type="primary" @click="handleAdd">添加</el-button>
    </div>

    <!-- Search Form -->
    <el-card class="search-card" style="margin-bottom: 20px;">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="手机号">
          <el-input v-model="searchForm.phone"></el-input>
        </el-form-item>
        <el-form-item label="内容关键字">
          <el-input v-model="searchForm.keyword"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Table -->
    <el-table 
      :data="list" 
      v-loading="loading" 
      style="width: 100%"
      border
    >
      <el-column prop="id" label="ID" width="80"></el-column>
      <el-column prop="phone" label="手机号" width="150"></el-column>
      <el-column prop="content" label="内容" min-width="200"></el-column>
      <el-column prop="status" label="状态">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'warning'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
        </template>
      </el-column>
      <el-column label="操作" width="200">
        <template #default="{ row }">
          <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
          <el-button link type="warning" @click="handleDelete(row.id, row.phone)">删除</el-button>
        </template>
      </el-column>
    </el-table>

    <!-- Pagination -->
    <el-pagination
      @size-change="handleSizeChange"
      @current-page-change="handleCurrentPageChange"
      :current-page="currentPage"
      :page-sizes="[10, 20, 50]"
      :page-size="pageSize"
      layout="total, bottom"
    ></el-pagination>
  </el-card>

  <!-- Dialog for Add/Edit -->
  <el-dialog v-model="dialogVisible" 
    title="短信信息管理" 
    width="50%" 
    :before-close="handleDialogClose"
  >
    <el-form 
      ref="formDataRef" 
      :model="formData" 
      :rules="rules" 
      label-col-span="3" 
      wrapper-foreground
    >
      <el-form-item label="ID (仅展示)" prop="id">
        <el-input v-model="formData.id" :disabled="true"></el-input>
      </el-form-item>
      <el-form-item label="手机号" prop="phone">
        <el-input v-model="formData.phone" placeholder="请输入目标手机号"></el-input>
      </el-form-item>
      <el-form-item label="内容关键字" prop="keyword">
        <el-input v-model="formData.keyword" placeholder="请输入短信内容关键词"></el-input>
      </el-form-item>
      <el-form-item label="状态" prop="status">
        <el-select v-model="formData.status" placeholder="请选择状态" clearable style="width: 100%;">
          <el-option label="启用" :value="1"></el-option>
          <el-option label="禁用" :value="0"></el-option>
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
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设的API引入，请根据实际情况调整路径和函数名
import { getSmsList, createSms, updateSms, deleteSms } from '@/api/sms';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  phone: '',
  keyword: ''
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  phone: '',
  keyword: '',
  status: 1 // 默认启用
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Form Refs
const searchFormRef = ref(null);
const formDataRef = ref(null);

// Rules for validation
const rules = reactive({
  phone: [{ required: true, message: '请输入手机号', trigger: 'blur' }],
  keyword: [],
  status: []
});

// --- Methods ---

const loadData = async () => {
  loading.value = true;
  try {
    await getSmsList({ page: 1, pageSize: 10, search: {} }); // 初始加载，不带搜索条件
    console.log('数据加载成功');
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  loadData(1, pageSize.value);
};

const resetSearch = () => {
  searchForm.phone = '';
  searchForm.keyword = '';
  handleSearch(); // 重置后重新加载数据
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  // 实际项目中，这里应该触发一次带分页参数的API调用
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  // 实际项目中，这里应该触发一次带分页参数的API调用
};


const handleAdd = () => {
  formData.id = null; // 清空ID，表示新增
  dialogVisible.value = true;
  // 重置表单数据到默认值（如果需要）
  formData.phone = '';
  formData.keyword = '';
  formData.status = 1;
};

const handleEdit = (row) => {
  Object.assign(formData, {
    id: row.id,
    phone: row.phone,
    keyword: row.content, // 假设内容字段对应编辑的关键字
    status: row.status
  });
  dialogVisible.value = true;
};

const handleDelete = async (id, phone) => {
  await ElMessageBox.confirm(
    `确定要删除ID为 ${id}，手机号为 ${phone} 的短信记录吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  if (confirm) {
    try {
      await deleteSms(id); // 调用API删除
      ElMessage.success('删除成功');
      // 刷新列表
      loadData(); 
    } catch (error) {
      ElMessage.error('删除失败');
    }
  }
};

const handleDialogClose = () => {
  dialogVisible.value = false;
  // 清空表单，防止误操作残留数据
  formData.id = null;
  formData.phone = '';
  formData.keyword = '';
  formData.status = 1;
};

const dialogSubmit = async () => {
  try {
    const valid = await formDataRef.value.validate();
    if (!valid) return;
    loading.value = true;
    const payload = {
      phone: formData.phone,
      keyword: formData.keyword,
      status: formData.status
    };
    if (formData.id) {
      await updateSms(formData.id, payload);
      ElMessage.success('编辑成功');
    } else {
      await createSms(payload);
      ElMessage.success('添加成功');
    }
    handleDialogClose();
    loadData();
  } catch (error) {
    ElMessage.error('操作失败');
  } finally {
    loading.value = false;
  }
};

// --- Lifecycle Hooks ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.sms-card {
  margin-bottom: 20px;
}
.search-card :deep(.el-form-item) {
    margin-bottom: 10px;
}
/* 确保 el-table 的操作列按钮样式美观 */
.el-button--link {
    padding: 5px 8px;
    font-size: 13px;
}
</style>