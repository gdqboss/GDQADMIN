<template>
  <div class="attendance-container">
    <el-card class="search-card" shadow="hover">
      <el-form :inline="true" v-model:modelValue="searchForm" ref="searchFormRef">
        <el-form-item label="日期范围">
          <el-date-picker v-model="searchForm.startDate"             type="date"
            placeholder="开始日期"
            @change="handleSearch"
          ></el-date-picker>
          <el-date-picker v-model="searchForm.endDate"             type="date"
            placeholder="结束日期"
            @change="handleSearch"
          ></el-date-picker>
        </el-form-item>
        <el-form-item label="员工ID">
          <el-input v-model="searchForm.employeeId" placeholder="请输入员工ID"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="data-card" v-loading="loading">
      <el-table :data="list" style="width: 100%" stripe border>
        <el-table-column prop="attendanceDate" label="日期" width="180"></el-table-column>
        <el-table-column prop="employeeId" label="员工ID" width="120"></el-table-column>
        <el-table-column prop="userName" label="姓名"></el-table-column>
        <el-table-column prop="checkInTime" label="打卡时间 (上班)"></el-table-column>
        <el-table-column prop="checkOutTime" label="打卡时间 (下班)"></el-table-column>
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          @size-change="handleSizeChange"
          @current-page-change="handlePageChange"
          :current-page="currentPage"
          :page-sizes="[10, 20, 50]"
          :page-size="pageSize"
          layout="total, bottom"
          total="total"
        ></el-pagination>
      </div>
    </el-card>

    <el-button type="success" @click="handleAdd" class="add-button">添加考勤记录</el-button>

    <!-- Add/Edit Dialog -->
    <el-dialog 
      :title="formData.isEdit ? '编辑考勤记录' : '添加考勤记录'" v-model="dialogVisible" 
      width="50%" 
      :before-close="handleDialogClose">
    
      <el-form 
        ref="dialogFormRef" 
        :model="formData" 
        label-col-span="3" 
        label-width="100px"
        rules="rules"
      >
        <el-form-item label="日期" prop="attendanceDate">
          <el-date-picker v-model="formData.attendanceDate" type="date" placeholder="选择日期"></el-date-picker>
        </el-form-item>
        <el-form-item label="员工ID" prop="employeeId">
          <el-input v-model="formData.employeeId" placeholder="请输入员工ID"></el-input>
        </el-form-item>
        <el-form-item label="姓名" prop="userName">
          <el-input v-model="formData.userName" placeholder="请输入员工姓名"></el-input>
        </el-form-item>
        <el-form-item label="上班时间" prop="checkInTime">
          <el-time-picker v-model="formData.checkInTime" placeholder="选择上班时间"></el-time-picker>
        </el-form-item>
        <el-form-item label="下班时间" prop="checkOutTime">
          <el-time-picker v-model="formData.checkOutTime" placeholder="选择下班时间"></el-time-picker>
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
// Assume these API calls are correctly set up in '@/api/attendance'
import { getAttendanceList, addAttendance, updateAttendance, deleteAttendance } from "@/api/attendance";

const searchFormRef = ref(null);
const loading = ref(false);
const list = ref([]);
const total = ref(0);
const searchForm = reactive({
  startDate: null,
  endDate: null,
  employeeId: '',
});
const dialogVisible = ref(false);
const formData = reactive({
    attendanceDate: null,
    employeeId: '',
    userName: '',
    checkInTime: null,
    checkOutTime: null,
    isEdit: false, // To track if we are editing or adding
});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);

const rules = reactive({
    attendanceDate: [{ required: true, message: '日期不能为空', trigger: 'blur' }],
    employeeId: [{ required: true, message: '员工ID不能为空', trigger: 'blur' }],
    userName: [{ required: true, message: '姓名不能为空', trigger: 'blur' }],
    checkInTime: [{ required: true, message: '上班时间不能为空', trigger: 'change' }],
    checkOutTime: [{ required: true, message: '下班时间不能为空', trigger: 'change' }],
});

const resetSearch = () => {
    searchForm.startDate = null;
    searchForm.endDate = null;
    searchForm.employeeId = '';
    handleSearch();
};

const handleSearch = async () => {
  loading.value = true;
  try {
    await getAttendanceList({ 
        page: 1, 
        pageSize: pageSize.value, 
        search: searchForm 
    });
  } catch (error) {
    ElMessage.error('查询失败');
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (val) => {
    pageSize.value = val;
    handleSearch();
};

const handlePageChange = (val) => {
    currentPage.value = val;
    handleSearch();
};

const loadData = async () => {
    await handleSearch();
}

// --- Dialog Handlers ---

const openAddDialog = () => {
    formData.isEdit = false;
    Object.assign(formData, { attendanceDate: null, employeeId: '', userName: '', checkInTime: null, checkOutTime: null });
    dialogVisible.value = true;
};

const openEditDialog = (row) => {
    formData.isEdit = true;
    // Deep copy data to form state
    Object.assign(formData, { 
        attendanceDate: row.attendanceDate, 
        employeeId: row.employeeId, 
        userName: row.userName, 
        checkInTime: row.checkInTime, 
        checkOutTime: row.checkOutTime 
    });
    dialogVisible.value = true;
};

const handleDialogClose = () => {
    dialogVisible.value = false;
    // Reset form data when closing dialog if not submitting
    if (!formData.isEdit) {
        Object.assign(formData, { attendanceDate: null, employeeId: '', userName: '', checkInTime: null, checkOutTime: null });
    }
};

const dialogSubmit = async () => {
    let success = false;
    try {
        if (formData.isEdit) {
            await updateAttendance(formData);
            ElMessage.success('编辑成功');
            success = true;
        } else {
            await addAttendance(formData);
            ElMessage.success('添加成功');
            success = true;
        }
    } catch (error) {
        ElMessage.error('操作失败，请检查网络或数据。');
    } finally {
        dialogVisible.value = false;
        // Reload data after successful operation
        await loadData(); 
    }
};

// --- Action Handlers ---

const handleAdd = () => {
    openAddDialog();
};

const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除该条考勤记录吗?`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelText: '取消',
      type: 'warning',
    }
  );
  try {
    await deleteAttendance(row.id);
    ElMessage.success('删除成功');
    // Reload data after successful deletion
    await loadData(); 
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

// --- Watchers and Lifecycle ---

watch(() => [currentPage.value, pageSize.value], () => {
    handleSearch();
}, { immediate: false });


onMounted(() => {
  loadData();
});
</script>

<style scoped>
.attendance-container {
  padding: 20px;
}

.search-card {
  margin-bottom: 20px;
}

.data-card {
  margin-top: 20px;
}

.add-button {
    margin-top: 20px;
    margin-bottom: 20px;
}

/* Adjusting el-table layout for better spacing */
:deep(.el-table__body) {
    min-height: 150px; /* Ensure table area is visible even when empty */
}

.pagination-container {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>