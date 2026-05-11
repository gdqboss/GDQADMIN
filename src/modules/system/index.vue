<template>
  <div class="system-index">
    <el-form :model="searchForm" @submit.native.prevent="handleSearch">
      <el-form-item label="Search">
        <el-input v-model="searchForm.query" placeholder="Enter search term"></el-input>
      </el-form-item>
      <el-button type="primary" @click="handleSearch">Search</el-button>
    </el-form>

    <el-table :data="list" style="width: 100%">
      <el-table-column prop="name" label="Name"></el-table-column>
      <el-table-column prop="value" label="Value"></el-table-column>
      <el-table-column label="Actions">
        <template #default="scope">
          <el-button type="text" @click="handleEdit(scope.row)">Edit</el-button>
          <el-button type="text" @click="handleDelete(scope.row)">Delete</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      @size-change="handleSearch"
      @current-change="handleSearch"
      :current-page="currentPage"
      :page-sizes="[10, 20, 50, 100]"
      :page-size="pageSize"
      layout="total, sizes, prev, pager, next, jumper"
      :total="total">
    </el-pagination>

    <el-dialog :title="dialogTitle" v-model="dialogVisible">
      <el-form v-model="formData">
        <el-form-item label="Name">
          <el-input v-model="formData.name"></el-input>
        </el-form-item>
        <el-form-item label="Value">
          <el-input v-model="formData.value"></el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancel</el-button>
        <el-button type="primary" @click="dialogSubmit">Submit</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { ElMessage } from 'element-plus';
import { getSystemSettings, updateSystemSettings } from "@/api/system";

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = ref({ query: '', page: 1, pageSize: 10 });
const dialogVisible = ref(false);
const formData = ref({});
const dialogTitle = ref('Add System Setting');
const currentPage = ref(1);
const pageSize = ref(10);

const loadData = async () => {
  loading.value = true;
  try {
    const response = await getSystemSettings(searchForm.value);
    list.value = response.data;
    total.value = response.total;
  } catch (error) {
    ElMessage.error('Failed to load system settings');
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  searchForm.value.page = currentPage.value;
  loadData();
};

const handleAdd = () => {
  dialogVisible.value = true;
  dialogTitle.value = 'Add System Setting';
  formData.value = {};
};

const handleEdit = (row) => {
  dialogVisible.value = true;
  dialogTitle.value = 'Edit System Setting';
  formData.value = { ...row };
};

const handleDelete = (row) => {
  // Implement delete logic here
};

const dialogSubmit = async () => {
  try {
    await updateSystemSettings(formData.value);
    ElMessage.success('System setting updated successfully');
    dialogVisible.value = false;
    loadData();
  } catch (error) {
    ElMessage.error('Failed to update system setting');
  }
};

loadData();
</script>

<style scoped>
.system-index {
  padding: 20px;
}
</style>