<template>
  <div class="module-page module-system">
    <el-card>
      <template #header>
        <span class="module-title">system</span>
      </template>
      <el-empty description="模块建设中..." />
    </el-card>
  </div>
</template>

<script setup>
import { ElMessage } from 'element-plus'
// TODO: 实现 system 模块功能
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
