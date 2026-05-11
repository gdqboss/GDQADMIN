<template>
  <div class="stock-module">
    <el-form :model="searchForm" @submit.native.prevent="handleSearch">
      <el-form-item label="Search">
        <el-input v-model="searchForm.keyword" placeholder="Enter keyword"></el-input>
      </el-form-item>
      <el-button type="primary" @click="handleSearch">Search</el-button>
    </el-form>

    <el-table :data="list" style="width: 100%">
      <el-table-column prop="id" label="ID" width="180"></el-table-column>
      <el-table-column prop="name" label="Name" width="180"></el-table-column>
      <el-table-column prop="quantity" label="Quantity" width="180"></el-table-column>
      <el-table-column label="Actions">
        <template #default="scope">
          <el-button size="mini" @click="handleEdit(scope.row)">Edit</el-button>
          <el-button size="mini" type="danger" @click="handleDelete(scope.row.id)">Delete</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      @size-change="handleSearch"
      @current-change="handleSearch"
      :current-page="searchForm.page"
      :page-sizes="[10, 20, 50, 100]"
      :page-size="searchForm.pageSize"
      layout="total, sizes, prev, pager, next, jumper"
      :total="total">
    </el-pagination>

    <el-dialog :title="dialogVisible ? 'Edit Stock' : 'Add Stock'" v-model="dialogVisible">
      <el-form v-model="formData" ref="dialogForm">
        <el-form-item label="Name" :rules="[{ required: true, message: 'Please input name', trigger: 'blur' }]">
          <el-input v-model="formData.name"></el-input>
        </el-form-item>
        <el-form-item label="Quantity">
          <el-input-number v-model="formData.quantity" :min="0"></el-input-number>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">Cancel</el-button>
          <el-button type="primary" @click="dialogSubmit">Submit</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { getStockList, addStock, updateStock, deleteStock } from "@/api/stock";

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  keyword: '',
  page: 1,
  pageSize: 10
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  quantity: 0
});

const loadData = async () => {
  loading.value = true;
  try {
    const response = await getStockList(searchForm);
    list.value = response.data;
    total.value = response.total;
  } catch (error) {
    ElMessage.error('Failed to load stock list');
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  loadData();
};

const handleAdd = () => {
  formData.id = null;
  formData.name = '';
  formData.quantity = 0;
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  formData.id = row.id;
  formData.name = row.name;
  formData.quantity = row.quantity;
  dialogVisible.value = true;
};

const handleDelete = async (id) => {
  try {
    await deleteStock(id);
    ElMessage.success('Stock deleted successfully');
    loadData();
  } catch (error) {
    ElMessage.error('Failed to delete stock');
  }
};

const dialogSubmit = async () => {
  const isValid = await dialogForm.value.validate();
  if (!isValid) return;

  try {
    if (formData.id) {
      await updateStock(formData);
      ElMessage.success('Stock updated successfully');
    } else {
      await addStock(formData);
      ElMessage.success('Stock added successfully');
    }
    dialogVisible.value = false;
    loadData();
  } catch (error) {
    ElMessage.error('Failed to submit stock');
  }
};

watch(() => searchForm, (newVal) => {
  loadData();
}, { deep: true });

loadData();
</script>

<style scoped>
.stock-module {
  padding: 20px;
}
</style>