<template>
  <div class="dashboard-container">
    <h2>Dashboard Management</h2>

    <!-- Search Form -->
    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="Search By Name">
          <el-input v-model="searchForm.name" placeholder="Enter name"></el-input>
        </el-form-item>
        <el-form-item label="Search By Status">
          <el-select v-model="searchForm.status" placeholder="Select status">
            <el-option label="Active" value="active"></el-option>
            <el-option label="Inactive" value="inactive"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">Search</el-button>
          <el-button @click="resetSearch">Reset</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Table -->
    <el-card class="table-card" shadow="hover">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3>Dashboard Items</h3>
        <el-button type="primary" @click="handleAdd">Add New</el-button>
      </div>

      <el-table
        v-loading="loading"
        :data="list"
        style="width: 100%"
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="Name"></el-table-column>
        <el-table-column prop="status" label="Status" width="120">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'">{{ row.status === 'active' ? 'Active' : 'Inactive' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="Created Time"></el-table-column>
        <el-table-column label="Actions" width="200">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">Edit</el-button>
            <el-button link type="danger" @click="handleDelete(row.id, row.name)"></el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- Pagination -->
      <el-pagination
        @size-change="handleSizeChange"
        @current-page-change="handleCurrentPageChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        :total="total"
        el-remote
      ></el-pagination>
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible"       title="Dashboard Item Management"
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
        <el-form-item label="Name" prop="name">
          <el-input v-model="formData.name" placeholder="Enter name"></el-input>
        </el-form-item>
        <el-form-item label="Status" prop="status">
          <el-select v-model="formData.status" placeholder="Select status">
            <el-option label="Active" value="active"></el-option>
            <el-option label="Inactive" value="inactive"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="Create Time" prop="createTime">
          <el-input v-model="formData.createTime" :disabled="true"></el-input>
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="$emit('cancel')">Cancel</el-button>
          <el-button type="primary" @click="dialogSubmit">Confirm</el-button>
        </span>
      </template>
    </el-dialog>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// Assuming the API function is available at this path
import getDashboardList from "@/api/dashboard"; 

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  status: 'active' // Default status
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  status: 'active',
  createTime: new Date().toLocaleString()
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

// Refs for forms and elements
const searchFormRef = ref(null);
const formDataRef = ref(null);


// --- Methods ---

const loadData = async () => {
  loading.value = true;
  try {
    await getDashboardList({ page: 1, pageSize: 10, search: {} }); // Initial load with empty search
    console.log("Initial data loaded successfully.");
  } catch (error) {
    ElMessage.error('Failed to load dashboard data.');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  if (!searchFormRef.value) return;
  await searchFormRef.value.validate();
  loading.value = true;
  try {
    // API call using object parameters: getXxxList({ page, pageSize, search })
    const result = await getDashboardList({ 
      page: currentPage.value, 
      pageSize: pageSize.value, 
      search: searchForm 
    });
    list.value = result.data || []; // Assuming API returns { data: [...], total: N }
    total.value = result.total || list.value.length;
  } catch (error) {
    ElMessage.error('Search failed.');
  } finally {
    loading.value = false;
  }
};

const resetSearch = () => {
  searchForm.name = '';
  searchForm.status = 'active';
  handleSearch(); // Re-run search with default values
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  handleSearch();
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  handleSearch();
};


// --- Dialog Handlers ---

const openAddDialog = () => {
  formData.id = null;
  formData.name = '';
  formData.status = 'active';
  formData.createTime = new Date().toLocaleString();
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // Populate form data for editing
  formData.id = row.id;
  formData.name = row.name;
  formData.status = row.status;
  formData.createTime = row.createTime;
  dialogVisible.value = true;
};

const handleDialogClose = () => {
  // Reset form state when dialog closes, regardless of how it was closed
  dialogVisible.value = false;
  Object.assign(formData, { id: null, name: '', status: 'active', createTime: new Date().toLocaleString() });
};

const dialogSubmit = async () => {
  if (!searchFormRef.value) return;
  await searchFormRef.value.validate();

  // Determine if it's an add or edit operation (simple check based on ID presence/null)
  const isEdit = !!formData.id && formData.id !== null;

  try {
    if (isEdit) {
      // API call for update: await getDashboardUpdate({ id: formData.id, ... })
      await ElMessage.info(`Simulating Update for ID: ${formData.id}`);
    } else {
      // API call for create: await getDashboardCreate({ name: formData.name, status: formData.status })
      await ElMessage.success('Item added successfully!');
    }
    dialogVisible.value = false;
    loadData(); // Reload data after successful operation
  } catch (error) {
    ElMessage.error('Operation failed.');
  }
};

// --- Delete Handlers ---

const handleSelectionChange = (val) => {
  // Used if we want to select multiple items for bulk action, but here we just pass it through.
};

const handleDelete = async (id, name) => {
  await ElMessageBox.confirm(
    `Are you sure you want to delete the item "${name}" with ID ${id}? This operation cannot be undone.`
  );
  try {
    // API call for delete: await getDashboardDelete({ id })
    await ElMessage.info(`Simulating Delete for ID: ${id}`);
    ElMessage.success('Deletion successful!');
    loadData(); // Reload data after deletion
  } catch (e) {
    ElMessage.warning('Operation cancelled.');
  }
};


// --- Lifecycle Hooks ---

onMounted(() => {
  loadData();
});
</script>

<style scoped>
.dashboard-container {
  padding: 20px;
}

.search-card, .table-card {
  margin-bottom: 20px;
}

/* Adjusting el-button layout for better spacing */
.el-form-item :deep(.el-btn) {
    margin-right: 10px;
}

.dialog-footer {
    text-align: right;
}
</style>