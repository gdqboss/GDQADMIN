<template>
  <div class="asset-management">
    <el-card class="header-card">
      <el-button type="primary" @click="handleAdd">新增资产</el-button>
      <el-divider content-append="搜索条件"></el-divider>
      <el-form :inline="true" v-model="searchForm" class="search-form">
        <el-form-item label="资产名称">
          <el-input v-model="searchForm.name" placeholder="请输入资产名称"></el-input>
        </el-form-item>
        <el-form-item label="资产编号">
          <el-input v-model="searchForm.assetCode" placeholder="请输入资产编号"></el-input>
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
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @change="handleSelectionChange" />
        <el-table-column prop="assetName" label="资产名称" width="200"></el-table-column>
        <el-table-column prop="assetCode" label="资产编号" width="180"></el-table-column>
        <el-table-column prop="owner" label="所属部门" width="150"></el-table-column>
        <el-table-column prop="purchaseDate" label="购置日期" width="150"></el-table-column>
        <el-table-column prop="status" label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="getStatusTag(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="$emit('edit', row)">编辑</el-button>
            <el-button link type="warning" @click="$emit('delete', row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        @size-change="handleSizeChange"
        @current-page-change="handleCurrentPageChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        layout="total, bottom"
        total="total"
      />
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" 
      title="资产信息管理" 
      width="50%" 
      :before-close="handleDialogClose">
      <el-form 
        ref="assetFormRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="3" 
        label-required>
        <el-form-item label="资产名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入资产名称"></el-input>
        </el-form-item>
        <el-form-item label="资产编号" prop="assetCode">
          <el-input v-model="formData.assetCode" placeholder="请输入资产编号"></el-input>
        </el-form-item>
        <el-form-item label="所属部门" prop="owner">
          <el-select v-model="formData.owner" placeholder="请选择所属部门">
            <el-option label="行政部" value="admin"></el-option>
            <el-option label="技术部" value="tech"></el-option>
            <el-option label="财务部" value="finance"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="购置日期" prop="purchaseDate">
          <el-date-picker v-model="formData.purchaseDate" 
            type="date" 
            placeholder="选择购置日期"
            value-format="yyyy-MM-dd"
          ></el-date-picker>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="formData.status">
            <el-radio-button label="1">启用</el-radio-button>
            <el-radio-button label="0">禁用</el-radio-button>
          </el-radio-group>
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
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 文件结构如下，如果不存在则使用 request
import getAssetList from '@/api/asset'; 

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  assetCode: ''
});
const dialogVisible = ref(false);
const formData = reactive({
    id: null, // 用于编辑时携带ID
    name: '',
    assetCode: '',
    owner: 'admin',
    purchaseDate: new Date().toISOString().substring(0, 10),
    status: '1'
});

// Pagination State
const currentPage = ref(1);
const pageSize = ref(10);

// --- Methods ---

const loadData = async () => {
  await fetchData();
};

const handleSearch = async () => {
  currentPage.value = 1; // 查询时重置页码
  await fetchData();
};

const handleSizeChange = (val) => {
  pageSize.value = val;
  currentPage.value = 1;
  fetchData();
};

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  fetchData();
};

// 模拟 API 调用，实际应传入当前页码和每页大小
const fetchData = async () => {
    loading.value = true;
    try {
        await getAssetList({
            page: currentPage.value,
            pageSize: pageSize.value,
            search: searchForm // 假设API支持直接传递搜索对象
        });
        // 实际接收到数据后，需要更新 list.value 和 total.value
        list.value = []; // 仅为示例，请替换为实际的 API 返回值
        total.value = 100; // 模拟总数
    } catch (error) {
        ElMessage.error('加载资产列表失败');
    } finally {
        loading.value = false;
    }
};

const handleAdd = () => {
  // 重置表单数据，准备新增
  Object.assign(formData, { id: null, name: '', assetCode: '', owner: 'admin', status: '1' });
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  // 填充编辑表单数据
  Object.assign(formData, {
    id: row.id,
    name: row.assetName,
    assetCode: row.assetCode,
    owner: row.owner,
    purchaseDate: row.purchaseDate,
    status: row.status
  });
  dialogVisible.value = true;
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除资产 "${row.assetName}" 吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  try {
    // 调用 delete API
    await getAssetDetail({ id: row.id }); // 假设删除API需要ID
    ElMessage.success('删除成功');
    // 刷新列表
    handleSearch(); 
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

const handleSelectionChange = (val) => {
    console.log('Selected rows:', val);
};

const handleDialogClose = () => {
    dialogVisible.value = false;
    // 确保关闭时重置表单状态，防止误操作
    Object.assign(formData, { id: null, name: '', assetCode: '', owner: 'admin', status: '1' });
};

const dialogSubmit = async () => {
  if (!validateForm()) return;

  try {
    if (formData.id) {
      // 编辑逻辑
      await getAssetDetail({ id: formData.id, data: formData }); // 假设编辑API
      ElMessage.success('编辑成功');
    } else {
      // 新增逻辑
      await getAssetDetail({ data: formData }); // 假设新增API
      ElMessage.success('添加成功');
    }
    dialogVisible.value = false;
    handleSearch(); // 刷新列表
  } catch (error) {
    ElMessage.error('提交失败，请检查表单信息。');
  }
};

// --- Validation & Helpers ---

const rules = reactive({
    name: [
        { required: true, message: '资产名称不能为空', trigger: 'blur' },
    ],
    assetCode: [
        { required: true, message: '资产编号不能为空', trigger: 'blur' },
    ],
    owner: [
        { required: true, message: '所属部门不能为空', trigger: 'change' }
    ]
});

const validateForm = () => {
    // 触发一次验证，确保所有规则都检查到
    return new Promise((resolve) => {
        setTimeout(() => {
            this.$refs.assetFormRef.validateModel((valid) => {
                resolve(valid);
            });
        }, 10);
    });
};

const getStatusTag = (status) => {
  return status === '1' ? 'success' : 'danger';
};

const getStatusText = (status) => {
  return status === '1' ? '启用' : '禁用';
};


// --- Lifecycle Hooks ---
onMounted(() => {
  loadData();
});
</script>

<style scoped>
.asset-management {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.search-form {
    display: flex;
    align-items: flex-end;
}

.el-form-item {
    margin-right: 15px !important; /* 调整表单项间距 */
}

.table-card {
  margin-top: 20px;
}

/* 适配 el-button 在操作列的布局 */
:deep(.el-table__row :nth-child(6) .el-button--link) {
    margin-right: 10px;
}

.dialog-footer {
    text-align: right;
}
</style>