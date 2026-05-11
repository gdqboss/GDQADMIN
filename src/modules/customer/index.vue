<template>
  <div class="customer-management">
    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="客户名称">
          <el-input v-model="searchForm.name" placeholder="请输入客户名称"></el-input>
        </el-form-item>
        <el-form-item label="手机号">
          <el-input v-model="searchForm.phone" placeholder="请输入手机号"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch" :loading="loading">搜索</el-button>
          <el-button @click="loadData">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="hover">
      <el-table :data="list" style="width: 100%" v-loading="loading" element-loading-text="加载中...">
        <el-table-column prop="name" label="客户名称" width="200"></el-table-column>
        <el-table-column prop="phone" label="手机号" width="150"></el-table-column>
        <el-table-column prop="email" label="邮箱" width="200"></el-table-column>
        <el-table-column prop="address" label="地址" min-width="300"></el-table-column>
        <el-table-column prop="status" label="状态">
          <template #default="{ row }">
            <el-tag :type="getStatusTag(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="handleDelete(row.id, row.name)"></el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-pagination
      @size-change="loadData"
      @current-change="loadData"
      :current-page="currentPage"
      :page-sizes="[10, 20, 50]"
      :page-size="pageSize"
      layout="bottom"
      adjust-size
      total-text="总页数: "
    ></el-pagination>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible"       title="客户信息管理"
      width="50%"
      :append-to-body="true"
    >
      <el-form :model="formData" ref="dialogFormRef" element-required>
        <el-form-item label="客户名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入客户名称"></el-input>
        </el-form-item>
        <el-form-item label="手机号" prop="phone">
          <el-input v-model="formData.phone" placeholder="请输入手机号"></el-input>
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="formData.email" placeholder="请输入电子邮箱"></el-input>
        </el-form-item>
        <el-form-item label="地址" prop="address">
          <el-input v-model="formData.address" placeholder="请输入客户地址"></el-input>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="formData.status" placeholder="请选择状态" clearable style="width: 100%;">
            <el-option label="正常" value="active"></el-option>
            <el-option label="禁用" value="inactive"></el-option>
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
// Assume these imports are correctly set up in the project structure
import { getCustomerList, addCustomer, updateCustomer, deleteCustomer } from "@/api/customer";

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  phone: '',
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  phone: '',
  email: '',
  address: '',
  status: 'active', // Default status
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

const searchFormRef = ref(null);
const dialogFormRef = ref(null);

const getStatusTag = (status) => {
  if (status === 'active') return 'success';
  return 'danger';
};

const getStatusText = (status) => {
    return status === 'active' ? '正常' : '禁用';
}

// --- Data Loading and Search ---

const loadData = async () => {
  loading.value = true;
  try {
    await fetchCustomerList();
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  currentPage.value = 1; // Reset to first page on search
  await fetchCustomerList();
};

const fetchCustomerList = async (page = currentPage.value, size = pageSize.value) => {
    loading.value = true;
    try {
        // API call using object parameters
        const result = await getCustomerList({ 
            page: page, 
            pageSize: size, 
            search: searchForm 
        });
        list.value = result.data || [];
        total.value = result.total || 0;
    } catch (error) {
        ElMessage.error('加载客户列表失败');
    } finally {
        loading.value = false;
    }
};

// Wrapper to handle pagination changes and search form updates
const loadDataWrapper = async () => {
    await fetchCustomerList();
}


// --- Dialog Handlers (Add/Edit) ---

const openDialog = (record = null) => {
  if (record) {
    formData.id = record.id;
    formData.name = record.name;
    formData.phone = record.phone;
    formData.email = record.email;
    formData.address = record.address;
    formData.status = record.status;
  } else {
    // Reset form for adding new customer
    formData.id = null;
    formData.name = '';
    formData.phone = '';
    formData.email = '';
    formData.address = '';
    formData.status = 'active';
  }
  dialogVisible.value = true;
};

const handleAdd = () => {
  openDialog(null);
};

const handleEdit = (row) => {
  openDialog(row);
};

const dialogSubmit = async () => {
  if (!dialogFormRef.value || !dialogFormRef.value.validateModel) return;

  try {
    if (formData.id) {
      // Edit existing customer
      await updateCustomer({ 
        id: formData.id, 
        name: formData.name, 
        phone: formData.phone, 
        email: formData.email, 
        address: formData.address, 
        status: formData.status 
      });
      ElMessage.success('编辑成功');
    } else {
      // Add new customer
      await addCustomer({ 
        name: formData.name, 
        phone: formData.phone, 
        email: formData.email, 
        address: formData.address, 
        status: formData.status 
      });
      ElMessage.success('添加成功');
    }
    dialogVisible.value = false;
    loadDataWrapper(); // Reload list after success
  } catch (error) {
    ElMessage.error('操作失败，请检查输入信息。');
  }
};

// --- Delete Handler ---

const handleDelete = async (id, name) => {
  await ElMessageBox.confirm(
    `确定要删除客户 "${name}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteCustomer(id);
    ElMessage.success('删除成功');
    loadDataWrapper(); // Reload list after success
  } catch (e) {
    // Error handled by ElMessageBox if user cancels, otherwise caught here
  }
};

// --- Lifecycle Hooks ---

onMounted(() => {
  loadDataWrapper();
});
</script>

<style scoped>
.customer-management {
  padding: 20px;
}

.search-card {
  margin-bottom: 20px;
}

.table-card {
  margin-top: 10px;
}

.dialog-footer {
    text-align: right;
}
</style>