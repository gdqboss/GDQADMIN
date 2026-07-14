<template>
  <MinipLayout title="流程设计" :canBack="true">
    <div class="summary-card">
      <div class="row"><span>流程总数</span><strong>{{ workflows.length }}</strong></div>
      <div class="row"><span>本月发起</span><strong>{{ totalInstances }}</strong></div>
    </div>

    <div class="section-title">流程列表</div>
    <div class="wf-list">
      <div v-for="w in workflows" :key="w.id" class="wf-card">
        <div class="wf-head">
          <span class="wf-icon">{{ w.icon }}</span>
          <span class="wf-name">{{ w.name }}</span>
        </div>
        <div class="wf-meta">
          <span>{{ w.steps }} 步骤</span>
          <span class="dot">·</span>
          <span>{{ w.instances }} 实例</span>
        </div>
      </div>
      <div v-if="!workflows.length" class="empty">暂无流程</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const loading = ref(false)
import api from '@/api/request'
import { ElMessage } from 'element-plus'
import MinipLayout from './MinipLayout.vue'

const workflows = ref([])
const totalInstances = computed(() => workflows.value.reduce((s, w) => s + (w.instances || 0), 0))

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/oa/workflows')
    if (r.code === 0) workflows.value = r.data || []
  } catch (e) {
    ElMessage.error('加载失败,请稍后重试')
    workflows.value = []
  }
  finally {
    loading.value = false
  }
})
</script>

<style scoped>
.summary-card {
  background: #fff;
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 12px;
}
.summary-card .row {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  font-size: 13px;
}
.summary-card .row + .row { border-top: 1px solid #f3f4f6; }
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 12px 4px 8px;
}
.wf-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.wf-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
}
.wf-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}
.wf-icon { font-size: 22px; }
.wf-name { font-size: 14px; font-weight: 600; color: #1f2329; }
.wf-meta {
  font-size: 11px;
  color: #6b7280;
  display: flex;
  gap: 4px;
  align-items: center;
}
.wf-meta .dot { opacity: 0.5; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>