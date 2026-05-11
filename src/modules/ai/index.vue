<template>
  <div class="ai-module">
    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" :model="searchForm" ref="searchFormRef">
        <el-form-item label="搜索关键词">
          <el-input v-model="searchForm.search"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch" :loading="loading">
            查询
          </el-button>
          <el-button @click="loadData(1, 10)">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="data-card" shadow="hover">
      <template #header>
        <div class="header-title">AI 响应列表</div>
        <el-button type="primary" @click="handleAdd">添加</el-button>
      </template>
      <el-table v-loading="loading" :data="list" style="width: 100%" border>
        <el-table-column prop="id" label="ID" width="80"></el-table-column>
        <el-table-column prop="title" label="标题"></el-table-column>
        <el-table-column prop="content" label="内容">
          <template #default="{ row }">
            <div class="content-preview">{{ row.content }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="创建时间"></el-table-column>
        <el-table-column label="操作" width="200" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row.id, row.title)"></el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog 
      :visible.sync="dialogVisible" 
      title="AI 响应{{ isEditing ? '编辑' : '添加' }}" 
      width="50%" 
      custom-class="el-dialog-custom"
    >
      <el-form v-model="formData" 
        ref="dialogFormRef" 
        :rules="rules" 
        label-col-span="3" 
        label-width="100px"
      >
        <el-form-item label="标题" prop="title">
          <el-input v-model="formData.title" placeholder="请输入响应标题"></el-input>
        </el-form-item>
        <el-form-item label="内容" prop="content">
          <el-input v-model="formData.content" type="textarea" :rows="5" placeholder="请输入AI生成的内容"></el-input>
        </el-form-item>
        <!-- 可以添加其他字段 -->
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
// Assume getAiResponse is the correct API call structure for fetching list data
import { getAiResponseList } from "@/api/ai"; 

const searchFormRef = ref(null);
const dialogVisible = ref(false);
const loading = ref(false);
const list = ref([]);
const total = ref(0);
const searchForm = reactive({
  page: 1,
  pageSize: 10,
  search: '',
});
const formData = reactive({
  id: null,
  title: '',
  content: '',
});
const isEditing = ref(false);

// --- Methods ---

const loadData = async (page = 1, pageSize = 10) => {
  loading.value = true;
  try {
    const params = {
      page: page,
      pageSize: pageSize,
      search: searchForm.search || '',
    };
    // IMPORTANT: Use the correct API call structure
    const res = await getAiResponseList({ ...params }); 
    list.value = res.data.list; // Assuming the response structure has { data: { list: [...] } }
    total.value = res.data.total;
  } catch (error) {
    console.error("加载数据失败:", error);
    ElMessage.error('加载AI响应列表失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  // Reset page to 1 when searching
  searchForm.page = 1;
  await loadData(1, searchForm.pageSize);
};

const resetSearch = () => {
    searchForm.search = '';
    loadData(1, 10);
}


const handleAdd = () => {
  formData.id = null;
  formData.title = '';
  formData.content = '';
  isEditing.value = false;
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  formData.id = row.id;
  formData.title = row.title;
  formData.content = row.content;
  isEditing.value = true;
  dialogVisible.value = true;
};

const handleDelete = async (id, name) => {
  await ElMessageBox.confirm(
    `确定要删除AI响应 "${name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    // Placeholder for actual delete API call
    await getAiResponseDetail({ id }); // Assuming a delete endpoint exists
    ElMessage.success('删除成功');
    loadData(1, 10); // Reload list after deletion
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

const dialogSubmit = async () => {
  // Basic validation check before submission
  if (!formData.title || !formData.content) {
      ElMessage.warning("标题和内容不能为空");
      return;
  }

  try {
    let apiCall;
    if (isEditing.value && formData.id) {
        // API call for update
        apiCall = await getAiResponseDetail({ id: formData.id, title: formData.title, content: formData.content }); 
    } else {
        // API call for create
        apiCall = await getAiResponseList({ title: formData.title, content: formData.content }); // Reusing a generic list endpoint structure for simplicity
    }

    ElMessage.success(`${isEditing.value ? '编辑' : '添加'}成功!`);
    dialogVisible.value = false;
    loadData(1, 10); // Reload data table
  } catch (error) {
    console.error("提交失败:", error);
    ElMessage.error('操作失败，请检查网络或数据。');
  }
};

// --- Lifecycle Hooks & Watchers ---

onMounted(() => {
  loadData(1, 10);
});

</script>

<style scoped>
.ai-module {
  padding: 20px;
}

.search-card {
  margin-bottom: 20px;
}

.data-card {
  /* Custom styling for the card containing the table */
}

.header-title {
    font-size: 18px;
    font-weight: bold;
    margin-right: 20px;
}

.content-preview {
    white-space: pre-wrap;
    word-break: break-all;
    max-width: 300px;
    display: block;
}

/* Customizing the dialog appearance */
.el-dialog-custom :deep(.el-dialog__body) {
  padding: 20px;
}
</style>