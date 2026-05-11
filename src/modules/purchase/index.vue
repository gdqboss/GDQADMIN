<template>
  <div class="purchase-container">
    <el-card class="header-card" shadow="hover">
      <template #header>
        <div class="header-title">采购订单管理</div>
        <el-button type="primary" @click="handleAdd">新增</el-button>
      </template>
    </el-card>

    <!-- Search Form -->
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="采购单号" prop="purchaseNo">
          <el-input v-model="searchForm.purchaseNo" placeholder="请输入采购单号"></el-input>
        </el-form-item>
        <el-form-item label="供应商名称" prop="supplierName">
          <el-input v-model="searchForm.supplierName" placeholder="请输入供应商名称"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="loadData">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Table -->
    <el-card class="table-card" shadow="never">
      <el-table 
        :data="list" 
        v-loading="loading" 
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-icon class="el-icon--selection" :class="{ 'is-selected': $event }">
          <template #reference>
            <el-checkbox></el-checkbox>
          </template>
        </el-icon>
        <el-table-column prop="purchaseNo" label="采购单号" width="150"></el-table-column>
        <el-table-column prop="supplierName" label="供应商名称" width="200"></el-table-column>
        <el-table-column prop="orderDate" label="订单日期" width="180"></el-table-column>
        <el-table-column prop="totalAmount" label="总金额" width="150" align="right"></el-table-column>
        <el-table-column label="操作" width="200" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="$emit('edit', row)">编辑</el-button>
            <el-button link type="warning" @click="$emit('view', row)">查看</el-button>
            <el-button link type="danger" @click="handleDelete(row.id, row.purchaseNo)">删除</el-button>
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
        layout="bottom"
        total="total"
        adjust-size
      ></el-pagination>
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible" 
      :title="formData.isEdit ? '编辑采购订单' : '新增采购订单'" 
      width="50%" 
      @close="resetDialog">
      <el-form 
        :model="formData" 
        :rules="rules" 
        ref="dialogFormRef"
        label-for="purchaseOrderForm"
      >
        <el-form-item label="采购单号" prop="purchaseNo">
          <el-input v-model="formData.purchaseNo" placeholder="请输入采购单号"></el-input>
        </el-form-item>
        <el-form-item label="供应商名称" prop="supplierName">
          <el-input v-model="formData.supplierName" placeholder="请输入供应商名称"></el-input>
        </el-form-item>
        <el-form-item label="订单日期" prop="orderDate">
          <el-date-picker v-model="formData.orderDate" 
            type="date" 
            value-format="yyyy-MM-dd"
            placeholder="选择订单日期"
          ></el-date-picker>
        </el-form-item>
        <el-form-item label="总金额" prop="totalAmount">
          <el-input type="number" v-model.number="formData.totalAmount" placeholder="请输入总金额"></el-input>
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
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设的API引入，请根据实际情况修改路径和函数名
import getPurchaseList from '@/api/purchase/getPurchaseList'; 

const props = defineProps({
  // 接收父组件传来的事件处理函数（如果需要）
});

const emit = defineEmits(['edit', 'view']);

// --- State Management ---
const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  purchaseNo: '',
  supplierName: ''
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null, // 假设有ID用于编辑和删除
  purchaseNo: '',
  supplierName: '',
  orderDate: '',
  totalAmount: 0
});
const currentPage = ref(1);
const pageSize = ref(10);

// Refs for forms and elements
const searchFormRef = ref(null);
const dialogFormRef = ref(null);


// --- Methods ---

const loadData = async () => {
  await fetchData();
};

const handleSearch = async () => {
  await fetchData();
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  fetchData();
};

const handleCurrentPageChange = (val) => {
  currentPage.value = val;
  fetchData();
};

// 核心数据加载函数，封装了分页和搜索逻辑
const fetchData = async () => {
  loading.value = true;
  try {
    await getPurchaseList({
      page: currentPage.value,
      pageSize: pageSize.value,
      search: searchForm // API期望的参数结构
    });
    // 假设API返回的数据结构包含 list 和 total
    list.value = await getPurchaseList({ page: currentPage.value, pageSize: pageSize.value, search: searchForm }).then(res => res.data || []);
    total.value = await getPurchaseList({ page: currentPage.value, pageSize: pageSize.value, search: searchForm }).then(res => res.total || 0);

  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};


const handleAdd = () => {
  resetDialog();
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.id = row.id; // 假设有ID
  formData.purchaseNo = row.purchaseNo;
  formData.supplierName = row.supplierName;
  formData.orderDate = row.orderDate;
  formData.totalAmount = row.totalAmount;
  dialogVisible.value = true;
};

const handleDelete = async (id, purchaseNo) => {
  await ElMessageBox.confirm(
    `确定要删除采购单号为 ${purchaseNo} 的记录吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    // 实际调用删除API，这里仅作演示
    await getPurchaseDetailById(id).then(() => {
        ElMessage.success('删除成功');
        // 删除成功后刷新列表
        loadData();
    });
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

const dialogSubmit = async () => {
  if (!dialogFormRef.value || !dialogFormRef.value.validateModel) {
    return;
  }

  // 触发表单验证
  await dialogFormRef.value.validateModel();

  try {
    let apiCall;
    let successMessage = '添加成功';

    if (formData.id) {
      // 编辑逻辑
      apiCall = await updatePurchaseDetail(formData); // 假设的更新API
      successMessage = '编辑成功';
    } else {
      // 新增逻辑
      apiCall = await createPurchaseDetail(formData); // 假设的创建API
      successMessage = '添加成功';
    }

    ElMessage.success(`${successMessage}!`);
    resetDialog();
    loadData(); // 刷新列表
  } catch (error) {
    ElMessage.error('提交失败，请检查表单信息。');
  }
};

// --- Utility Functions ---

const resetDialog = () => {
  dialogVisible.value = false;
  Object.assign(formData, {
    id: null,
    purchaseNo: '',
    supplierName: '',
    orderDate: '',
    totalAmount: 0
  });
  // 重置表单验证状态（可选）
  if (dialogFormRef.value) {
      dialogFormRef.value.resetFields();
  }
};

// --- Lifecycle Hooks & Watchers ---
onMounted(() => {
  loadData();
});


// ==============================================================
// 模拟API调用函数，请替换为真实的API调用
// ==============================================================

/**
 * 模拟创建数据的API调用
 */
const createPurchaseDetail = async (data) => {
    console.log("Calling API: createPurchaseDetail", data);
    await new Promise(resolve => setTimeout(resolve, 300));
    return { success: true, id: Math.floor(Math.random() * 1000) };
}

/**
 * 模拟更新数据的API调用
 */
const updatePurchaseDetail = async (data) => {
    console.log("Calling API: updatePurchaseDetail", data);
    await new Promise(resolve => setTimeout(resolve, 300));
    return { success: true };
}

/**
 * 模拟根据ID删除数据的API调用
 */
const getPurchaseDetailById = async (id) => {
    console.log("Calling API: delete by ID", id);
    await new Promise(resolve => setTimeout(resolve, 300));
    return true;
}

</script>

<style scoped>
.purchase-container {
  padding: 20px;
}

/* Header Card Styling */
.header-card {
  margin-bottom: 20px;
}

.header-title {
  font-size: 1.5em;
  font-weight: bold;
}

/* Search Card Styling */
.search-card {
  margin-bottom: 20px;
}

/* Table Card Styling */
.table-card {
  margin-top: 20px;
}

/* Dialog Footer Styling */
.dialog-footer {
  text-align: right;
}
</style>