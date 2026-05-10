<template>
  <div class="module-page module-finance-dashboard">
    <el-card>
      <template #header>
        <span class="module-title">finance-dashboard</span>
      </template>
      <el-empty description="模块建设中..." />
    </el-card>
  </div>
</template>

<script setup>
import { ElMessage } from 'element-plus'
// TODO: 实现 finance-dashboard 模块功能
</script>

<style scoped>
.module-page {
  padding: 20px;
}
.module-title {
  font-size: 18px;
  font-weight: 600;
}
</style>
