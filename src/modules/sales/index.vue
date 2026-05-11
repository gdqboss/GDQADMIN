<template>
  <div class="sales-module">
    <el-form :model="searchForm" @submit.native.prevent="handleSearch">
      <el-form-item label="Search">
        <el-input v-model="searchForm.keyword" placeholder="Enter keyword"></el-input>
      </el-form-item>
      <el-button type="primary" @click="handleSearch">Search</el-button>
    </el-form>
    <el-table :data="list" style="width: 100%">
      <el-table-column prop="id" label="ID" width="180"></el-table-column>
      <el-table-column prop="product" label="Product" width="180"></el-table-column>
      <el-table-column prop="quantity" label="Quantity" width="180"></el-table-column>
      <el-table-column prop="price" label="Price" width="180"></el-table-column>
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
    <el-dialog :title="dialogVisible ? 'Edit Sale' : 'Add Sale'" v-model="dialogVisible">
      <el-form v-model="formData">
        <el-form-item label="Product">
          <el-input v-model="formData.product"></el-input>
        </el-form-item>
        <el-form-item label="Quantity">
          <el-input v-model.number="formData.quantity"></el-input>
        </el-form-item>
        <el-form-item label="Price">
          <el-input v-model.number="formData.price"></el-input>
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
import { ref } from 'vue';
import { ElMessage } from 'element-plus';
import { getSalesList, addSale, updateSale, deleteSale } from "@/api/sales";

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = ref({
  keyword: '',
  page: 1,
  pageSize: 10
});
const dialogVisible = ref(false);
const formData = ref({
  id: null,
  product: '',
  quantity: 0,
  price: 0
});

const loadData = async () => {
  loading.value = true;
  try {
    const response = await getSalesList(searchForm.value);
    list.value = response.data;
    total.value = response.total;
  } catch (error) {
    ElMessage.error('Failed to load sales list');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  await loadData();
};

const handleAdd = () => {
  formData.value = {
    id: null,
    product: '',
    quantity: 0,
    price: 0
  };
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  formData.value = { ...row };
  dialogVisible.value = true;
};

const handleDelete = async (id) => {
  try {
    await deleteSale(id);
    await loadData();
    ElMessage.success('Sale deleted successfully');
  } catch (error) {
    ElMessage.error('Failed to delete sale');
  }
};

const dialogSubmit = async () => {
  if (formData.value.id) {
    try {
      await updateSale(formData.value);
      await loadData();
      ElMessage.success('Sale updated successfully');
    } catch (error) {
      ElMessage.error('Failed to update sale');
    }
  } else {
    try {
      await addSale(formData.value);
      await loadData();
      ElMessage.success('Sale added successfully');
    } catch (error) {
      ElMessage.error('Failed to add sale');
    }
  }
  dialogVisible.value = false;
};

loadData();
</script>

<style scoped>
.sales-module {
  padding: 20px;
}
</style>