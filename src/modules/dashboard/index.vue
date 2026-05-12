<template>
  <div class="dashboard-container">
    <h2>工作台</h2>

    <!-- Row 1: 4 cards -->
    <div class="stats-row" v-loading="loading">
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div class="stat-label">今日销售额</div>
          <div class="stat-value">{{ formatNumber(stats.todaySales) }}</div>
        </div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div class="stat-label">本月销售额</div>
          <div class="stat-value">{{ formatNumber(stats.monthSales) }}</div>
        </div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div class="stat-label">总库存</div>
          <div class="stat-value">{{ formatNumber(stats.totalStock) }}</div>
        </div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div class="stat-label">库存预警</div>
          <div class="stat-value warning">{{ formatNumber(stats.alertCount) }}</div>
        </div>
      </el-card>
    </div>

    <!-- Row 2: 3 cards -->
    <div class="stats-row stats-row-3">
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div class="stat-label">待审批</div>
          <div class="stat-value">{{ formatNumber(stats.pendingApprovals) }}</div>
        </div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div class="stat-label">今日入库</div>
          <div class="stat-value">{{ formatNumber(stats.todayInbound) }}</div>
        </div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div class="stat-label">今日出库</div>
          <div class="stat-value">{{ formatNumber(stats.todayOutbound) }}</div>
        </div>
      </el-card>
    </div>

    <!-- Row 3: 2 cards centered -->
    <div class="stats-row stats-row-2">
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div class="stat-label">商品总数</div>
          <div class="stat-value">{{ formatNumber(stats.totalProducts) }}</div>
        </div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div class="stat-label">二维码总数</div>
          <div class="stat-value">{{ formatNumber(stats.totalQrcodes) }}</div>
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import request from '@/api/request';

const loading = ref(false);
const stats = reactive({
  todaySales: 0,
  monthSales: 0,
  totalStock: 0,
  alertCount: 0,
  pendingApprovals: 0,
  todayInbound: 0,
  todayOutbound: 0,
  totalProducts: 0,
  totalQrcodes: 0,
});

const formatNumber = (num) => {
  if (num == null) return '0';
  return num.toLocaleString();
};

const loadStats = async () => {
  loading.value = true;
  try {
    const data = await request.get('/dashboard/stats');
    Object.assign(stats, {
      todaySales: data.todaySales ?? 0,
      monthSales: data.monthSales ?? 0,
      totalStock: data.totalStock ?? 0,
      alertCount: data.alertCount ?? 0,
      pendingApprovals: data.pendingApprovals ?? 0,
      todayInbound: data.todayInbound ?? 0,
      todayOutbound: data.todayOutbound ?? 0,
      totalProducts: data.totalProducts ?? 0,
      totalQrcodes: data.totalQrcodes ?? 0,
    });
  } catch (error) {
    ElMessage.error('加载统计数据失败');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadStats();
});
</script>

<style scoped>
.dashboard-container {
  padding: 20px;
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

/* Row 2: 3 cards centered */
.stats-row-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 16px;
  justify-content: center;
  max-width: 75%;
  margin-left: auto;
  margin-right: auto;
}

/* Row 3: 2 cards centered */
.stats-row-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-bottom: 16px;
  justify-content: center;
  max-width: 50%;
  margin-left: auto;
  margin-right: auto;
}

.stat-card {
  min-height: 100px;
}

.stat-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 10px 0;
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  color: #303133;
}

.stat-value.warning {
  color: #e6a23c;
}
</style>
