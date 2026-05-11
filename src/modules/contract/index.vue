<template>
  <div class="contract-container">
    <el-card class="header-card" shadow="hover">
      <template #header>
        <div class="card-header-title">合同管理</div>
        <el-button type="primary" @click="handleAdd">新增合同</el-button>
      </template>
    </el-card>

    <!-- Search Form -->
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="合同编号">
          <el-input v-model="searchForm.contractNumber" placeholder="请输入合同编号"></el-input>
        </el-form-item>
        <el-form-item label="甲方名称">
          <el-input v-model="searchForm.partyAName" placeholder="请输入甲方名称"></el-input>
        </el-form-item>
        <el-form-item label="乙方名称">
          <el-input v-model="searchForm.partyBName" placeholder="请输入乙方名称"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="loadData(1, 10)">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Table -->
    <el-card class="table-card" shadow="never">
      <el-table
        :data="list"
        v-loading="loading"
        style="width: 100%"
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="contractNumber" label="合同编号" width="180"></el-table-column>
        <el-table-column prop="partyAName" label="甲方名称" width="200"></el-table-column>
        <el-table-column prop="partyBName" label="乙方名称" width="200"></el-table-column>
        <el-table-column prop="contractAmount" label="合同金额" width="150" align="right"></el-table-column>
        <el-table-column prop="concludeDate" label="签订日期" width="120"></el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- Pagination -->
      <div class="pagination-container">
        <el-pagination
          @size-change="handleSizeChange"
          @current-page-change="handleCurrentPageChange"
          :current-page="currentPage"
          :page-sizes="[10, 20, 50]"
          :page-size="pageSize"
          layout="total,bottom"
          total="total"
        ></el-pagination>
      </div>
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible"       title="合同信息管理"
      width="60%"
      :append-to-body="true"
    >
      <el-form 
        ref="elFormRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="2" 
        wrapper-impact
      >
        <el-form-item label="合同编号" prop="contractNumber">
          <el-input v-model="formData.contractNumber" placeholder="请输入合同编号"></el-input>
        </el-form-item>
        <el-form-item label="甲方名称" prop="partyAName">
          <el-input v-model="formData.partyAName" placeholder="请输入甲方名称"></el-input>
        </el-form-item>
        <el-form-item label="乙方名称" prop="partyBName">
          <el-input v-model="formData.partyBName" placeholder="请输入乙方名称"></el-input>
        </el-form-item>
        <el-form-item label="合同金额" prop="contractAmount">
          <el-input type="number" v-model.number="formData.contractAmount" placeholder="请输入合同金额"></el-input>
        </el-form-item>
        <el-form-item label="签订日期" prop="concludeDate">
          <el-date-picker v-model="formData.concludeDate" 
            type="date" 
            value-format="YYYY-MM-DD"
            placeholder="选择签订日期"
          ></el-date-picker>
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
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 文件结构如下，如果不存在则使用 request
import getContractList from '@/api/contract/getContractList'; 

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  contractNumber: '',
  partyAName: '',
  partyBName: ''
});
const dialogVisible = ref(false);
const formData = reactive({
  contractNumber: '',
  partyAName: '',
  partyBName: '',
  contractAmount: 0,
  concludeDate: ''
});

// Pagination State
const currentPage = ref(1);
const pageSize = ref(10);

// Refs for form validation and search form
const elFormRef = ref(null);
const searchFormRef = ref(null);


// --- Methods ---

const loadData = async (page, size) => {
  loading.value = true;
  try {
    const params = {
      page: page,
      pageSize: size,
      search: searchForm.contractNumber || searchForm.partyAName || searchForm.partyBName ? 
             { contractNumber: searchForm.contractNumber, partyAName: searchForm.partyAName, partyBName: searchForm.partyBName } : null
    };

    // 调用 API，注意参数结构必须是对象
    const result = await getContractList({ page, pageSize, ...params }); 
    list.value = result.data || [];
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('加载数据失败: ' + error.message);
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  // 重置页码到第一页进行搜索
  currentPage.value = 1;
  await loadData(currentPage.value, pageSize.value);
};

const handleAdd = () => {
  formData.contractNumber = '';
  formData.partyAName = '';
  formData.partyBName = '';
  formData.contractAmount = 0;
  formData.concludeDate = '';
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充表单数据
  formData.contractNumber = row.contractNumber;
  formData.partyAName = row.partyAName;
  formData.partyBName = row.partyBName;
  formData.contractAmount = row.contractAmount;
  formData.concludeDate = row.concludeDate;
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除合同编号为 ${row.contractNumber} 的记录吗?`, 
    '提示', 
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning'
    }
  );
  // 实际的删除API调用应放在这里，例如：await deleteContract(row.id);
  ElMessage.success('删除成功（模拟）');
};

const dialogSubmit = async () => {
  elFormRef.value.validate(async (valid) => {
    if (!valid) {
      return false;
    }

    // 假设这里是新增或编辑的逻辑，根据是否为新增来判断API调用
    try {
      const isEdit = !!formData.contractNumber && list.value.some(item => item.contractNumber === formData.contractNumber); // 简单判断是否已存在
      
      if (isEdit) {
        // 调用编辑API
        await getContractList({ /* ... edit params */ }); 
        ElMessage.success('编辑成功（模拟）');
      } else {
        // 调用新增API
        await getContractList({ /* ... add params */ }); 
        ElMessage.success('添加成功（模拟）');
      }

      dialogVisible.value = false;
      handleSearch(); // 提交后刷新列表
    } catch (error) {
      ElMessage.error('操作失败: ' + error.message);
    }
  });
};


// --- Pagination Handlers ---

const handleSizeChange = (val) => {
  pageSize.value = val;
  currentPage.value = 1; // 改变每页大小，重置到第一页
  loadData(1, val);
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  loadData(page, pageSize.value);
};

// --- Selection Handler ---
const handleSelectionChange = (selection) => {
  console.log('Selected rows:', selection);
};


// --- Lifecycle Hooks ---
onMounted(() => {
  // 页面加载时，默认查询第一页数据
  loadData(1, 10);
});

</script>

<style scoped>
.contract-container {
  padding: 20px;
}

/* Header Card Styling */
.header-card {
  margin-bottom: 20px;
}

.card-header-title {
  font-size: 1.2em;
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

/* Pagination Container Styling */
.pagination-container {
  display: flex;
  justify-content: flex-end;
  padding-bottom: 15px;
}

/* Dialog Footer Styling */
.dialog-footer {
    text-align: right;
}
</style>