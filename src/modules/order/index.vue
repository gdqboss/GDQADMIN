<template>
  <div class="order-management">
    <el-card class="header-card" shadow="hover">
      <template #header>
        <div class="header-title">订单管理</div>
      </template>
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <el-button type="primary" @click="handleAdd">新增订单</el-button>
      </div>
    </el-card>

    <!-- Search and Table -->
    <el-card class="data-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="订单号"><el-input v-model="searchForm.orderNo" placeholder="请输入订单号"></el-input></el-form-item>
        <el-form-item label="客户名称"><el-input v-model="searchForm.customerName" placeholder="请输入客户名称"></el-input></el-form-item>
        <el-form-item label="订单状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态">
            <el-option label="待支付" value="PENDING"></el-option>
            <el-option label="已支付" value="PAID"></el-option>
            <el-option label="已完成" value="COMPLETED"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- Table -->
      <el-table 
        :data="list" 
        v-loading="loading" 
        style="margin-top: 20px;"
        @selection-change="handleSelectionChange"
      >
        <el-icon class="el-icon--selection" :class="{ 'is-selected': $event }">
          <template #reference>
            <el-checkbox></el-checkbox>
          </template>
        </el-icon>
        <el-table-column prop="orderNo" label="订单号" width="150"></el-table-column>
        <el-table-column prop="customerName" label="客户名称"></el-table-column>
        <el-table-column prop="totalAmount" label="总金额" width="120" align="right">
          <template #default="{ row }">
            {{ row.totalAmount ? '¥' + parseFloat(row.totalAmount).toFixed(2) : '0.00' }}
          </template>
        </el-table-column>
        <el-table-column prop="status" label="订单状态" width="120">
          <template #default="{ row }">
            <el-tag :type="getStatusTagType(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="$emit('edit', row)">编辑</el-button>
            <el-button link type="warning" @click="$emit('delete', row)">删除</el-button>
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
        layout="total, bottom"
        total="total"
      ></el-pagination>
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible" 
      title="订单信息管理" 
      width="50%" 
      :before-close="handleDialogClose"
    >
      <el-form 
        ref="formDataRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="3" 
        wrapper-impact
      >
        <el-form-item label="订单号" prop="orderNo">
          <el-input v-model="formData.orderNo" placeholder="请输入订单号"></el-input>
        </el-form-item>
        <el-form-item label="客户名称" prop="customerName">
          <el-input v-model="formData.customerName" placeholder="请输入客户名称"></el-input>
        </el-form-item>
        <el-form-item label="总金额" prop="totalAmount">
          <el-input type="number" v-model.number="formData.totalAmount" placeholder="请输入总金额"></el-input>
        </el-form-item>
        <el-form-item label="订单状态" prop="status">
          <el-select v-model="formData.status" placeholder="请选择状态">
            <el-option label="待支付" value="PENDING"></el-option>
            <el-option label="已支付" value="PAID"></el-option>
            <el-option label="已完成" value="COMPLETED"></el-option>
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// Assuming the API call is structured like this:
import { getOrderList, createOrder, updateOrder, deleteOrder } from '@/api/order'; 

const props = defineProps({
  // Expose events for parent component to handle actions on table rows
});

const emit = defineEmits(['edit', 'delete', 'cancel']);

// --- State Management ---
const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  orderNo: '',
  customerName: '',
  status: 'PENDING' // Default status for search
});
const dialogVisible = ref(false);
const formData = reactive({
  orderNo: '',
  customerName: '',
  totalAmount: 0,
  status: 'PENDING',
});
const currentPage = ref(1);
const pageSize = ref(10);

// --- Refs for Forms ---
const searchFormRef = ref(null);
const formDataRef = ref(null);

// --- Methods ---

const getStatusText = (status) => {
  switch (status) {
    case 'PENDING': return '待支付';
    case 'PAID': return '已支付';
    case 'COMPLETED': return '已完成';
    default: return '未知';
  }
};

const getStatusTagType = (status) => {
  switch (status) {
    case 'PENDING': return 'warning';
    case 'PAID': return 'success';
    case 'COMPLETED': return 'primary';
    default: return 'info';
  }
};

const loadData = async () => {
  loading.value = true;
  try {
    // API Call Example: Using object parameters for pagination and search criteria
    await getOrderList({ 
      page: currentPage.value, 
      pageSize: pageSize.value, 
      search: {
        orderNo: searchForm.orderNo,
        customerName: searchForm.customerName,
        status: searchForm.status
      }
    });
  } catch (error) {
    ElMessage.error('加载订单列表失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  currentPage.value = 1; // Reset to first page on search
  await loadData();
};

const resetSearch = () => {
  searchForm.orderNo = '';
  searchForm.customerName = '';
  searchForm.status = 'PENDING';
  handleSearch();
};

const handleAdd = () => {
  formData.orderNo = '';
  formData.customerName = '';
  formData.totalAmount = 0;
  formData.status = 'PENDING';
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // Populate form data with selected row data
  formData.orderNo = row.orderNo;
  formData.customerName = row.customerName;
  formData.totalAmount = row.totalAmount;
  formData.status = row.status;
  dialogVisible.value = true;
};

const handleDialogClose = () => {
  // Clear form data when dialog closes, unless it was an edit operation that needs persistence (handled by parent/logic)
  if (!props.isEditing) { // Simple check if we are not explicitly editing in this component scope
    formData.orderNo = '';
    formData.customerName = '';
    formData.totalAmount = 0;
    formData.status = 'PENDING';
  }
};

const dialogSubmit = async () => {
  if (!searchFormRef.value || !formDataRef.value) return;

  // Basic validation check (Element Plus handles most of this via :rules)
  try {
    let isUpdate = !!formData.orderNo && formData.orderNo !== ''; // Simple heuristic for update vs add

    if (isUpdate) {
      await updateOrder(formData);
      ElMessage.success('编辑成功');
    } else {
      await createOrder(formData);
      ElMessage.success('新增成功');
    }
    dialogVisible.value = false;
    handleSearch(); // Reload data after successful operation
  } catch (error) {
    ElMessage.error('提交失败，请检查表单信息。');
  }
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除订单 ${row.orderNo} 吗? 此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteOrder(row.id); // Assuming API takes ID for deletion
    ElMessage.success('删除成功');
    handleSearch();
  } catch (e) {
    // Do nothing, error handled by ElMessageBox if needed
  }
};

// --- Pagination Handlers ---

const handleSizeChange = (val) => {
  pageSize.value = val;
  currentPage.value = 1;
  loadData();
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  loadData();
};

// --- Watchers and Lifecycle Hooks ---

watch([searchForm, currentPage, pageSize], () => {
    // Only load data if the search form or pagination parameters change explicitly via buttons/pagination controls
}, { deep: true });


onMounted(() => {
  loadData();
});

</script>

<style scoped>
.order-management {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.data-card {
  /* Ensures the card background doesn't interfere with table styling */
}

.el-table :deep(.el-checkbox) {
    cursor: pointer;
}

.dialog-footer {
    text-align: right;
}
</style>