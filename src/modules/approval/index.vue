<template>
  <div class="approval-container">
    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" v-model:modelValue="searchForm" ref="searchFormRef">
        <el-form-item label="审批类型">
          <el-select v-model="searchForm.approvalType" placeholder="请选择"></el-select>
        </el-form-item>
        <el-form-item label="申请人">
          <el-input v-model="searchForm.applicantName" placeholder="请输入申请人姓名"></el-input>
        </el-form-item>
        <el-form-item label="搜索">
          <el-button type="primary" @click="handleSearch">查询</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="data-card" shadow="hover">
      <el-table v-loading="loading" :data="list" style="width: 100%" @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" @change="handleSelectionChange"></el-table-column>
        <el-table-column prop="id" label="审批单号" width="120"></el-table-column>
        <el-table-column prop="applicantName" label="申请人"></el-table-column>
        <el-table-column prop="approvalType" label="审批类型"></el-table-column>
        <el-table-column prop="status" label="状态">
          <template #default="{ row }">
            <el-tag :type="getStatusTag(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="创建时间"></el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">&nbsp;编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row.id)">&nbsp;删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <div class="actions">
      <el-button type="primary" @click="handleAdd">新增审批单</el-button>
    </div>

    <el-dialog v-model="dialogVisible" 
      :title="formData.isEdit ? '编辑审批单' : '新增审批单'" 
      width="50%"
      @close="resetDialog">
      <el-form 
        ref="dialogFormRef" 
        :model="formData" 
        label-col-span="3" 
        label-width="100px"
        :rules="rules"
      >
        <el-form-item label="审批单号" prop="id">
          <el-input v-model="formData.id" :disabled="true"></el-input>
        </el-form-item>
        <el-form-item label="申请人" prop="applicantName">
          <el-input v-model="formData.applicantName" placeholder="请输入申请人姓名"></el-input>
        </el-form-item>
        <el-form-item label="审批类型" prop="approvalType">
          <el-select v-model="formData.approvalType" placeholder="请选择"></el-select>
        </el-form-item>
        <el-form-item label="流程节点" prop="processNode">
          <el-input v-model="formData.processNode" placeholder="请输入流程节点"></el-input>
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="formData.remark" type="textarea" placeholder="请输入备注信息"></el-input>
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="$emit('close')">取消</el-button>
          <el-button type="primary" @click="dialogSubmit">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// Assume these API calls are correctly set up in @/api
import { getApprovalList, addApproval, updateApproval, deleteApproval } from "@/api/approval";

const searchFormRef = ref(null);
const dialogFormRef = ref(null);

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  page: 1,
  pageSize: 10,
  approvalType: '',
  applicantName: ''
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  applicantName: '',
  approvalType: '',
  processNode: '',
  remark: '',
  isEdit: false
});

const rules = reactive({
  applicantName: [
    { required: true, message: '请输入申请人姓名', trigger: 'blur' },
  ],
  approvalType: [
    { required: true, message: '请选择审批类型', trigger: 'change' },
  ]
});

const getStatusTag = (status) => {
  if (status === 1) return 'success';
  if (status === 0) return 'warning';
  return 'info';
};

const getStatusText = (status) => {
  switch (status) {
    case 1: return '已通过';
    case 0: return '待审批';
    default: return '未知';
  }
};

const handleSelectionChange = (val) => {
  console.log('Selected rows:', val);
};

const loadData = async () => {
  loading.value = true;
  try {
    await getApprovalList({ page: 1, pageSize: 10, search: {} }); // Initial load with no specific search params if needed
    // For initial load, we might just call handleSearch without setting form values first
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  if (!searchFormRef.value) return;
  await searchFormRef.value.validate();
  loading.value = true;
  try {
    const params = {
      page: searchForm.page,
      pageSize: searchForm.pageSize,
      search: {
        approvalType: searchForm.approvalType,
        applicantName: searchForm.applicantName,
      }
    };
    // Assuming getApprovalList handles the structure { page, pageSize, search }
    const result = await getApprovalList(params); 
    list.value = result.data || [];
    total.value = result.total || 0;
  } catch (error) {
    ElMessage.error('查询失败');
  } finally {
    loading.value = false;
  }
};

const handleAdd = () => {
  formData.isEdit = false;
  resetDialog();
  dialogVisible.value = true;
};

const handleEdit = (row) => {
  formData.isEdit = true;
  formData.id = row.id;
  formData.applicantName = row.applicantName;
  formData.approvalType = row.approvalType;
  formData.processNode = '流程节点'; // Example default value for edit
  formData.remark = '';
  dialogVisible.value = true;
};

const handleDelete = async (id) => {
  await ElMessageBox.confirm(
    '确定删除此审批单吗？', 
    '提示', 
    { affirmativeButton: '确定', cancelButtonText: '取消', type: 'warning' }
  );
  try {
    await deleteApproval(id);
    ElMessage.success('删除成功');
    // Re-load data after deletion
    handleSearch(); 
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

const resetDialog = () => {
  dialogFormRef.value?.resetFields();
  formData.id = null;
  formData.applicantName = '';
  formData.approvalType = '';
  formData.processNode = '';
  formData.remark = '';
  formData.isEdit = false;
};

const dialogSubmit = async () => {
  await dialogFormRef.value.validate();
  if (!dialogFormRef.value) return;

  try {
    let result;
    if (formData.isEdit && formData.id) {
      result = await updateApproval(formData);
      ElMessage.success('编辑成功');
    } else {
      result = await addApproval(formData);
      ElMessage.success('新增成功');
    }
    dialogVisible.value = false;
    resetDialog();
    handleSearch(); // Refresh list after submit
  } catch (error) {
    ElMessage.error('提交失败，请检查表单信息。');
  }
};

onMounted(() => {
  loadData();
});
</script>

<style scoped>
.approval-container {
  padding: 20px;
}

.search-card, .data-card {
  margin-bottom: 20px;
}

.actions {
  text-align: right;
  margin-bottom: 20px;
}

/* Adjusting element plus table width for better layout */
:deep(.el-table__body) {
    min-width: 100%;
}
</style>