<template>
  <div class="coupon-container">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header-title">优惠券管理</div>
        <el-button type="primary" @click="handleAdd">新增</el-button>
      </template>
    </el-card>

    <!-- Search Form -->
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" v-model="searchForm" ref="searchFormRef">
        <el-form-item label="优惠券名称">
          <el-input v-model="searchForm.name" placeholder="请输入优惠券名称"></el-input>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="searchForm.type" placeholder="请选择类型">
            <el-option label="满减" value="full_reduction"></el-option>
            <el-option label="折扣" value="discount"></el-option>
            <el-option label="红包" value="coupon_bag"></el-option>
          </el-select>
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
        <el-icon class="el-avatar select-icon"><el-icon-type name="selection"></el-icon-type></el-icon>
        <el-table-column type="selection" width="55" align="center"></el-table-column>
        <el-table-column prop="id" label="ID" width="100"></el-table-column>
        <el-table-column prop="name" label="名称"></el-table-column>
        <el-table-column prop="type" label="类型" width="120">
          <template #default="{ row }">
            <el-tag :type="getTypeTag(row.type)">{{ getTypeName(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="condition" label="适用条件"></el-table-column>
        <el-table-column prop="discount_value" label="优惠值"></el-table-column>
        <el-table-column prop="start_time" label="开始时间"></el-table-column>
        <el-table-column prop="end_time" label="结束时间"></el-table-column>
        <el-table-column label="操作" width="200" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="$emit('edit', row)">编辑</el-button>
            <el-button link type="warning" @click="$emit('delete', row.id, row.name)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- Pagination -->
      <el-pagination
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        :page-size="pageSize"
        :page-sizes="[10, 20, 50]"
        :total="total"
        :page-params="{ page: currentPage, size: pageSize }"
        layout="bottom"
        adjust-width
        style="margin-top: 20px;"
      ></el-pagination>
    </el-card>

    <!-- Dialog for Add/Edit -->
    <el-dialog v-model="dialogVisible" 
      title="优惠券信息" 
      width="50%" 
      :before-close="handleDialogClose"
    >
      <el-form 
        ref="formDataRef" 
        :model="formData" 
        :rules="rules" 
        label-col-span="3" 
        wrapper-tag="div"
      >
        <el-form-item label="ID" prop="id">
          <el-input v-model="formData.id" :disabled="true"></el-input>
        </el-form-item>
        <el-form-item label="名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入优惠券名称"></el-input>
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择类型">
            <el-option label="满减" value="full_reduction"></el-option>
            <el-option label="折扣" value="discount"></el-option>
            <el-option label="红包" value="coupon_bag"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="适用条件" prop="condition">
          <el-input v-model="formData.condition" placeholder="例如：满100减20"></el-input>
        </el-form-item>
        <el-form-item label="优惠值" prop="discount_value">
          <el-input type="number" v-model.number="formData.discount_value" placeholder="请输入优惠金额"></el-input>
        </el-form-item>
        <el-form-item label="开始时间" prop="start_time">
          <el-date-picker v-model="formData.start_time" 
            type="datetime" 
            value-format="YYYY-MM-DDTHH:mm:ss"
            placeholder="选择开始时间"
          ></el-date-picker>
        </el-form-item>
        <el-form-item label="结束时间" prop="end_time">
          <el-date-picker v-model="formData.end_time" 
            type="datetime" 
            value-format="YYYY-MM-DDTHH:mm:ss"
            placeholder="选择结束时间"
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
import { ref, reactive, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
// 假设 API 文件结构如下，如果不存在则使用 request
import getCouponList from '@/api/coupon/getCouponList'; // 请根据实际API路径修改
import { getCouponDetail } from '@/api/coupon/getCouponDetail'; // 假设需要获取详情的API

const props = defineProps({
  // 接收父组件传来的事件处理函数，用于触发操作
});

const emit = defineEmits(['edit', 'delete', 'cancel']);

// --- State Management ---
const list = ref([]);
const total = ref(0);
const loading = ref(false);
const searchForm = reactive({
  page: 1,
  pageSize: 10,
  search: {
    name: '',
    type: '',
  }
});
const dialogVisible = ref(false);
const formData = reactive({
  id: null,
  name: '',
  type: 'full_reduction', // 默认值
  condition: '',
  discount_value: 0,
  start_time: new Date().toISOString(),
  end_time: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString(), // 默认一年后
});

// --- Refs for Forms and API Calls ---
const searchFormRef = ref(null);
const formDataRef = ref(null);


// --- Methods ---

/**
 * 格式化类型标签的颜色
 * @param {string} type - 优惠券类型
 */
const getTypeTag = (type) => {
  if (type === 'full_reduction') return 'success';
  if (type === 'discount') return 'warning';
  return 'info';
};

/**
 * 根据类型获取中文名称
 * @param {string} type - 优惠券类型
 */
const getTypeName = (type) => {
    switch(type) {
        case 'full_reduction': return '满减';
        case 'discount': return '折扣';
        case 'coupon_bag': return '红包';
        default: return '未知';
    }
};

/**
 * 模拟加载数据（实际应调用API）
 */
const loadData = async () => {
  loading.value = true;
  try {
    // 构造 API 参数，注意：这里假设 getCouponList 需要 page, pageSize, search 对象
    await getCouponList({
      page: searchForm.search.page || 1,
      pageSize: searchForm.search.pageSize || 10,
      search: searchForm.search,
    });
    // 模拟 API 返回数据结构：{ list: [...], total: N }
    list.value = await getCouponList({ page: 1, pageSize: 20, search: { name: '', type: '' } }).then(res => res.data || []);
    total.value = 55; // 模拟总数
  } catch (error) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }
};

/**
 * 处理搜索查询
 */
const handleSearch = async () => {
  // 确保 page 和 pageSize 从 searchForm 中获取，但这里我们重置到第一页进行搜索
  searchForm.search.page = 1;
  await loadData();
};

/**
 * 处理分页变化
 */
const handleCurrentChange = (page) => {
    searchForm.search.page = page;
    loadData();
};

/**
 * 处理每页大小变化
 */
const handleSizeChange = (size) => {
    searchForm.search.pageSize = size;
    loadData();
};


/**
 * 打开新增/编辑对话框
 * @param {Object} row - 当前行数据（如果是编辑）
 */
const openDialog = (row = null) => {
  if (row) {
    // 编辑模式
    formData.id = row.id;
    formData.name = row.name;
    formData.type = row.type;
    formData.condition = row.condition;
    formData.discount_value = row.discount_value;
    formData.start_time = row.start_time;
    formData.end_time = row.end_time;
  } else {
    // 新增模式，重置表单数据（保留默认值）
    formData.id = null;
    formData.name = '';
    formData.type = 'full_reduction';
    formData.condition = '';
    formData.discount_value = 0;
    formData.start_time = new Date().toISOString();
    formData.end_time = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString();
  }
  dialogVisible.value = true;
};

/**
 * 关闭对话框，并重置表单状态
 */
const handleDialogClose = () => {
    dialogVisible.value = false;
    // 清空 formData 的关键字段，防止下次误用旧数据
    formData.id = null;
    formData.name = '';
    formData.condition = '';
};

/**
 * 提交表单（新增或编辑）
 */
const dialogSubmit = async () => {
  if (!searchFormRef.value || !formDataRef.value) return;

  // 1. 验证表单
  await formDataRef.value.validateElForm();

  try {
    let apiCall;
    let successMessage;

    if (formData.id) {
      // 编辑逻辑：调用更新API
      apiCall = await getCouponDetail(formData); // 假设这是更新的API
      successMessage = '编辑成功';
    } else {
      // 新增逻辑：调用创建API
      apiCall = await getCouponList({ search: formData }); // 假设这是创建的API
      successMessage = '新增成功';
    }

    ElMessage.success(`${successMessage}！`);
    handleDialogClose();
    loadData(); // 刷新列表数据
  } catch (error) {
    console.error('提交失败', error);
    ElMessage.error('操作失败，请检查网络或参数。');
  }
};

/**
 * 处理删除确认
 * @param {number} id - 优惠券ID
 * @param {string} name - 优惠券名称
 */
const handleDelete = async (id, name) => {
  await ElMessageBox.confirm(
    `确定要删除【${name}】（ID: ${id}）吗?此操作不可恢复。`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }
  );
  if (true) { // 如果用户点击了确定
    try {
        // 实际调用删除API
        await getCouponList({ search: { id } }); // 假设这是删除的API
        ElMessage.success('删除成功');
        loadData();
    } catch (e) {
        ElMessage.error('删除失败');
    }
  }
};

/**
 * 处理选择行变化（如果需要批量操作）
 */
const handleSelectionChange = (selection) => {
  console.log('选中的行数据:', selection);
};


// --- Watchers & Lifecycle Hooks ---

// 监听搜索表单的变化，当用户输入后可以自动触发查询或等待点击按钮
watch(() => searchForm.search, () => {
    // 可以选择在这里实现防抖的自动查询，但为了清晰，我们依赖手动点击"查询"按钮。
});


// 初始化加载数据
loadData();

</script>

<style scoped>
.coupon-container {
  padding: 20px;
}

/* 卡片样式优化 */
.header-card, .search-card, .table-card {
    border-radius: 8px;
}

.card-header-title {
    font-size: 1.2em;
    font-weight: bold;
}

/* 对话框底部按钮组的样式 */
.dialog-footer {
  text-align: right;
}

/* 表单项布局调整，确保元素间距合理 */
:deep(.el-form-item__label) {
    min-width: 100px;
}
</style>