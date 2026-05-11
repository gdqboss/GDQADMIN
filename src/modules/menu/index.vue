<template>
  <div class="menu-management">
    <el-card class="header-card">
      <template #header>
        <div class="card-header-title">菜单管理</div>
        <el-button type="primary" @click="handleAdd">添加</el-button>
      </template>
      <el-form :inline="true" v-model="searchForm" class="search-form">
        <el-form-item label="名称" prop="name">
          <el-input v-model="searchForm.name" placeholder="请输入菜单名称"></el-input>
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="searchForm.type" placeholder="请选择类型">
            <el-option label="系统" value="system"></el-option>
            <el-option label="页面" value="page"></el-option>
            <el-option label="组件" value="component"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="loadData">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <el-table 
        :data="list" 
        v-loading="loading" 
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="菜单名称"></el-table-column>
        <el-table-column prop="type" label="类型" width="150"></el-table-column>
        <el-table-column prop="parentName" label="父菜单"></el-table-column>
        <el-table-column label="操作" width="300" align="center">
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
        layout="bottom"
        total="total"
      ></el-pagination>
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" 
      title="菜单信息管理" 
      width="50%" 
      :append-to-body="true">
      <el-form 
        ref="formDataRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="3" 
        wrapper-impact>
        <el-form-item label="ID" prop="id">
          <el-input v-model="formData.id" :disabled="true"></el-input>
        </el-form-item>
        <el-form-item label="菜单名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入菜单名称"></el-input>
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择类型">
            <el-option label="系统" value="system"></el-option>
            <el-option label="页面" value="page"></el-option>
            <el-option label="组件" value="component"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="父菜单ID" prop="parentId">
          <el-select v-model="formData.parentId" placeholder="请选择父菜单">
            <el-option value="0">无父级</el-option>
            <!-- 可以在这里动态加载其他菜单作为选项 -->
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
// 假设 API 文件结构如下，如果不存在则使用 request
import getMenuList from '@/api/menu/getMenuList'; // 请根据实际API路径修改
const api = getMenuList;

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  type: 'system',
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  type: 'system',
  parentId: null,
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Form validation rules
const rules = reactive({
  name: [
    { required: true, message: '菜单名称不能为空', trigger: 'blur' },
    { min: 2, max: 50, message: '长度在 2 到 50 个字符之间', trigger: 'blur' }
  ],
  type: [
    { required: true, message: '类型不能为空', trigger: 'change' }
  ]
});

// --- Methods ---

const loadData = async () => {
  currentPage.value = 1;
  await fetchMenuList();
};

const handleSearch = async () => {
  currentPage.value = 1;
  await fetchMenuList();
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  fetchMenuList();
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  fetchMenuList();
};

// 模拟获取父菜单列表，实际应用中应调用API
const getParentMenuOptions = () => {
    return [
        { label: '无父级', value: '0' },
        { label: '系统', value: 'system' },
        { label: '页面', value: 'page' }
    ];
}

const handleAdd = () => {
  formData.id = null;
  formData.name = '';
  formData.type = 'system';
  formData.parentId = null;
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.id = row.id;
  formData.name = row.name;
  formData.type = row.type;
  formData.parentId = row.parentId || null; // 假设API返回的父级ID可能为null或undefined
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除菜单 "${row.name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  if (confirm) {
    await deleteMenu(row.id);
  }
};

const dialogSubmit = async () => {
  // 触发表单验证
  await getFormInstanceForValidation();

  if (!el_form_ref.validate()) {
    return;
  }

  try {
    if (formData.id) {
      await handleEdit(formData.id);
    } else {
      await handleAdd(formData);
    }
    ElMessage.success('操作成功');
    dialogVisible.value = false;
    loadData(); // 刷新列表
  } catch (error) {
    ElMessage.error(`操作失败: ${error}`);
  }
};

// --- API Calls & Data Fetching ---

const fetchMenuList = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      search: searchForm,
    };
    
    // 假设 API 返回结构包含 list 和 total
    const result = await api(params); 
    list.value = result.data || []; // 适配API返回的实际数据字段
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载菜单列表失败');
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const deleteMenu = async (id) => {
  try {
    // 假设 API 调用删除接口
    await api(`/menu/${id}`, { method: 'DELETE' }); 
    ElMessage.success('删除成功');
    loadData();
  } catch (error) {
    ElMessage.error('删除失败，请检查权限或数据关联。');
  }
};

// --- Selection Handling ---
const handleSelectionChange = (selection) => {
  console.log('选中的行:', selection);
};


// --- Lifecycle Hooks & Refs ---
onMounted(() => {
  loadData();
});

// Need to get form instance for validation trigger
const el_form_ref = ref(null);
const getFormInstanceForValidation = async () => {
    if (el_form_ref.value) {
        return el_form_ref.value.validate();
    }
};

</script>

<style scoped>
.menu-management {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.search-form {
  display: flex;
  gap: 15px;
}

.table-card {
  margin-top: 20px;
}

/* Dialog Footer Styling */
.dialog-footer {
    text-align: right;
}
</style>