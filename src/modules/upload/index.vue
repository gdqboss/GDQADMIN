<template>
  <div class="upload-container">
    <el-card class="header-card">
      <el-button type="primary" @click="handleAdd">新增</el-button>
      <el-input v-model="searchForm.name" placeholder="名称" style="width: 120px; margin-right: 10px;">
        <el-icon><Search /></el-icon>
      </el-input>
      <el-select v-model="searchForm.type" placeholder="类型" style="width: 150px; margin-right: 10px;">
        <el-option label="图片" value="image" />
        <el-option label="文档" value="document" />
      </el-select>
      <el-button @click="handleSearch">查询</el-button>
    </el-card>

    <el-card class="table-card">
      <el-table :data="list" style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="名称" />
        <el-table-column prop="type" label="类型" />
        <el-table-column prop="description" label="描述" />
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="handleDelete(row.id, row.name)"></el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        layout="bottom"
        total="total"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" :title="formData.id ? '编辑上传记录' : '新增上传记录'">
      <el-form 
        ref="uploadFormRef" 
        :model="formData" 
        label-col-span="3" 
        label-width="100px"
      >
        <el-form-item label="名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入记录名称"></el-input>
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择文件类型">
            <el-option label="图片" value="image" />
            <el-option label="文档" value="document" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" placeholder="请输入记录描述"></el-input>
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
import { Search } from '@element-plus/icons-vue';
import { ElMessageBox } from 'element-plus';
// 假设 API 文件结构如下，如果不存在则使用 request
import getUploadList from '@/api/upload'; // 请确保此路径存在或修改为实际的API调用

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  page: 1,
  pageSize: 10,
  search: {
    name: '',
    type: 'image' // 默认值
  }
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  type: 'image',
  description: ''
});

// --- API 调用模拟/封装 (根据实际情况调整) ---
// 假设 getUploadList 的参数结构是 { page, pageSize, search }
// 如果你的API函数签名不同，请修改这里的调用逻辑。

const loadData = async () => {
  loading.value = true;
  try {
    await getUploadList({
      page: searchForm.search.page || 1,
      pageSize: searchForm.search.pageSize || 10,
      search: searchForm.search
    });
    // 假设 API 返回的数据结构包含 list 和 total
    list.value = await getUploadList({ page: 1, pageSize: 20, search: { name: '', type: 'image' } }).then(res => res.data || []); // 实际应根据API返回体调整
    total.value = 50; // 模拟 total
  } catch (error) {
    console.error('加载数据失败:', error);
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  // 更新搜索页码，确保查询从第一页开始
  searchForm.search.page = 1;
  await loadData();
};

const handleSizeChange = (size) => {
  searchForm.search.pageSize = size;
  // 改变每页大小后，重置到第一页并加载数据
  searchForm.search.page = 1;
  loadData();
};

const handleCurrentChange = (page) => {
  searchForm.search.page = page;
  loadData();
};


// --- CRUD 方法 ---

const resetFormData = () => {
    formData.id = null;
    formData.name = '';
    formData.type = 'image';
    formData.description = '';
}

const handleAdd = () => {
  resetFormData();
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
    `确定要删除记录 [${name}] 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    // 实际调用删除API
    await getUploadList({ page: 1, pageSize: 1, search: { id } }).then(res => res.success ? true : false); // 模拟API调用
    ElMessage.success('删除成功');
    loadData();
  } catch (e) {
    console.error(e);
  }
};

const dialogSubmit = async () => {
  await validateForm();
  if (!formData.name || !formData.type) return;

  loading.value = true;
  try {
    let apiCall;
    if (formData.id) {
      // 编辑逻辑
      apiCall = getUploadList({ page: 1, pageSize: 1, search: { id: formData.id } }); // 模拟编辑API
      ElMessage.info('正在编辑...');
    } else {
      // 新增逻辑
      apiCall = getUploadList({ page: 1, pageSize: 1, search: {} }); // 模拟新增API
      ElMessage.info('正在新增...');
    }

    await apiCall; // 执行实际的API调用
    ElMessage.success(formData.id ? '编辑成功' : '添加成功');
    dialogVisible.value = false;
    loadData();
  } catch (error) {
    console.error('提交失败', error);
    ElMessage.error('操作失败，请重试');
  } finally {
    loading.value = false;
  }
};

// 简单的表单验证函数（Vue 3 setup 中需要手动触发）
const validateForm = async () => {
    await getUploadList.validate(uploadFormRef.value);
};


onMounted(() => {
  loadData();
});
</script>

<style scoped>
.upload-container {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.table-card {
  margin-top: 10px;
}

/* 确保 el-button 在操作列中不会被其他样式干扰 */
</style>