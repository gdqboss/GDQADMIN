<template>
  <div class="card-container">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="header-title">卡片管理</div>
        <el-button type="primary" @click="handleAdd">新增</el-button>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="名称">
          <el-input v-model="searchForm.name" placeholder="请输入卡片名称"></el-input>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="searchForm.type" placeholder="请选择卡片类型">
            <el-option label="会员卡" value="MEMBER_CARD"></el-option>
            <el-option label="积分卡" value="POINT_CARD"></el-option>
            <el-option label="工牌" value="WORK_CARD"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="loadData(1, 10)">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="never">
      <el-table
        :data="list"
        v-loading="loading"
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" @selection-change="handleSelectionChange"></el-table-column>
        <el-table-column prop="cardName" label="卡片名称" width="180"></el-table-column>
        <el-table-column prop="cardType" label="卡片类型" width="120"></el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'warning'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="创建时间"></el-table-column>
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
      ></el-pagination>
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible"       title="卡片信息管理"
      width="50%"
      :before-close="handleDialogClose"
    >
      <el-form 
        ref="elFormRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="4" 
        label-width="80px"
      >
        <el-form-item label="卡片名称" prop="cardName">
          <el-input v-model="formData.cardName" placeholder="请输入卡片名称"></el-input>
        </el-form-item>
        <el-form-item label="卡片类型" prop="cardType">
          <el-select v-model="formData.cardType" placeholder="请选择卡片类型">
            <el-option label="会员卡" value="MEMBER_CARD"></el-option>
            <el-option label="积分卡" value="POINT_CARD"></el-option>
            <el-option label="工牌" value="WORK_CARD"></el-option>
          </el-select>
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
import { ref, reactive, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 文件结构为 @/api/card.js，并导出 getCardList, createCard, updateCard, deleteCard 等函数
import { getCardList, createCard, updateCard, deleteCard } from '@/api/card';

const props = defineProps({
  // 如果组件需要接收父组件传递的事件处理函数，可以在这里定义
});

const emit = defineEmits(['edit', 'delete', 'cancel']);

// --- 状态管理 ---
const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  name: '',
  type: '',
});
const dialogVisible = ref(false);
const formData = reactive({
  cardName: '',
  cardType: 'MEMBER_CARD', // 默认值
  status: 1, // 默认启用
});

// 分页和搜索状态
const currentPage = ref(1);
const pageSize = ref(10);
const searchFormRef = ref(null);
const elFormRef = ref(null);


// --- 方法定义 ---

/**
 * 加载数据 (用于初始化或重置查询)
 * @param {number} page 当前页码
 * @param {number} size 每页大小
 */
const loadData = async (page, size) => {
  loading.value = true;
  try {
    // 模拟 API 调用，使用对象参数
    const res = await getCardList({
      page: page,
      pageSize: size,
      search: searchForm.value, // 将搜索表单作为查询条件传递
    });
    list.value = res.data || [];
    total.value = res.total || 0;
  } catch (error) {
    ElMessage.error('加载数据失败');
    console.error(error);
  } finally {
    loading.value = false;
  }
};

/**
 * 执行搜索操作
 */
const handleSearch = async () => {
  // 重置页码到第一页，并调用 loadData
  currentPage.value = 1;
  await loadData(currentPage.value, pageSize.value);
};

/**
 * 处理分页大小变化
 * @param {number} size 新的每页大小
 */
const handleSizeChange = (size) => {
  pageSize.value = size;
  currentPage.value = 1; // 切换页大小后，重置到第一页
  loadData(currentPage.value, pageSize.value);
};

/**
 * 处理当前页码变化
 * @param {number} page 新的页码
 */
const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  loadData(currentPage.value, pageSize.value);
};


// --- 弹窗和表单操作 ---

/**
 * 打开新增/编辑对话框
 * @param {Object} row 如果是编辑，传入当前行数据
 */
const openDialog = (row = null) => {
  if (row) {
    // 编辑模式
    formData.cardName = row.cardName;
    formData.cardType = row.cardType;
    formData.status = row.status;
    dialogVisible.value = true;
    // 可以在这里设置一个编辑状态标志，以便提交时区分是新增还是编辑
  } else {
    // 新增模式
    formData.cardName = '';
    formData.cardType = 'MEMBER_CARD';
    formData.status = 1;
    dialogVisible.value = true;
  }
};

/**
 * 关闭对话框，重置表单状态
 */
const handleDialogClose = () => {
  dialogVisible.value = false;
  // 清空表单数据，防止残留值影响下次操作
  formData.cardName = '';
  formData.cardType = 'MEMBER_CARD';
  formData.status = 1;
};

/**
 * 提交表单（新增或编辑）
 */
const dialogSubmit = async () => {
  if (!elFormRef.value) return;
  
  // 校验表单
  await elFormRef.value.validateElForm();
  
  try {
    let apiCall;
    let successMessage;

    // 简单的判断逻辑：如果formData中存在一个特定的ID字段，则认为是编辑；否则是新增。
    // 在实际项目中，应根据组件的调用上下文来判断。这里假设没有明确的ID字段，我们先模拟为新增/更新流程。
    if (formData.cardName && formData.cardType) { 
        // 假设我们总是尝试更新或创建，此处需要更精确的业务逻辑来区分是编辑还是新增
        // 为了演示，我们统一使用 updateCard API，并传入一个占位ID（实际应从父组件/props获取）
        const mockId = '123'; // 替换为实际的记录ID
        apiCall = await updateCard({ id: mockId, ...formData });
        successMessage = '更新成功！';
    } else {
        ElMessage.warning('请填写完整的卡片信息');
        return;
    }

    ElMessage.success(successMessage);
    handleDialogClose();
    // 刷新列表数据，回到第一页
    await loadData(1, pageSize.value); 

  } catch (error) {
    ElMessage.error('提交失败: ' + error.message || '请检查网络或参数');
  }
};


/**
 * 删除操作（通过事件冒泡触发）
 * @param {Object} row 要删除的行数据
 */
const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定要删除卡片 "${row.cardName}" 吗?此操作不可撤销。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );

  try {
    // 调用删除API，传入记录ID
    await deleteCard({ id: row.id }); 
    ElMessage.success('删除成功');
    // 刷新列表数据
    loadData(currentPage.value, pageSize.value);
  } catch (e) {
    ElMessage.error('删除失败');
  }
};

/**
 * 处理行选择变化（如果需要批量操作）
 */
const handleSelectionChange = (selection) => {
  console.log('选中的行数据:', selection);
};


// --- 监听和初始化 ---

// 1. 初始化加载数据
loadData(currentPage.value, pageSize.value);

// 2. 暴露给父组件的事件处理函数（通过 v-on:click 或 @emit 调用）
defineExpose({
    handleSearch,
    openDialog // 如果需要外部调用打开弹窗
});

</script>

<style scoped>
.card-container {
  padding: 20px;
}

/* 头部卡片样式 */
.header-card {
  margin-bottom: 20px;
}

/* 查询搜索卡片样式 */
.search-card {
  margin-bottom: 20px;
}

/* 表格卡片样式 */
.table-card {
  padding-top: 10px;
}

/* 对话框底部操作按钮的样式调整 */
.dialog-footer {
    text-align: right;
}
</style>