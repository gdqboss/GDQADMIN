<template>
  <MinipLayout title="员工名册" :canBack="true">
    <div class="search-bar">
      <input v-model="kw" @input="load" placeholder="搜索姓名/手机号" />
    </div>

    <div class="emp-list">
      <div v-for="e in list" :key="e.id" class="emp-card">
        <div class="emp-avatar">{{ (e.name || '?').charAt(0) }}</div>
        <div class="emp-main">
          <div class="emp-name">{{ e.name }}</div>
          <div class="emp-meta">{{ e.department || '未分配' }} · {{ e.position || '-' }}</div>
          <div class="emp-phone">{{ e.phone || '-' }}</div>
        </div>
        <span class="emp-status" :class="e.status">{{ statusLabel(e.status) }}</span>
      </div>
      <div v-if="!list.length" class="empty">无匹配员工</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/request'
import { ElMessage } from 'element-plus'
import MinipLayout from './MinipLayout.vue'

const list = ref([])
const kw = ref('')

function statusLabel(s) {
  return { active: '在职', leave: '休假', inactive: '离职' }[s] || '在职'
}

async function load() {
  try {
    const r = await api.get(`/oa/employees?q=${encodeURIComponent(kw.value)}&limit=50`)
    if (r.code === 0) list.value = r.data || []
  } catch (e) {
    ElMessage.error('加载失败,请稍后重试')
    list.value = []
  }
}

onMounted(() => load())

const loading = ref(false)
</script>

<style scoped>
.search-bar {
  background: #fff;
  border-radius: 10px;
  padding: 8px 12px;
  margin-bottom: 12px;
}
.search-bar input {
  width: 100%;
  border: 0;
  outline: none;
  font-size: 14px;
  padding: 4px 0;
  background: transparent;
}
.emp-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.emp-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.emp-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  flex-shrink: 0;
}
.emp-main { flex: 1; min-width: 0; }
.emp-name { font-size: 14px; font-weight: 600; color: #1f2329; }
.emp-meta { font-size: 11px; color: #6b7280; margin-top: 2px; }
.emp-phone { font-size: 11px; color: #9ca3af; margin-top: 2px; }
.emp-status {
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 10px;
}
.emp-status.active { background: #d1fae5; color: #065f46; }
.emp-status.leave { background: #fef3c7; color: #b45309; }
.emp-status.inactive { background: #f3f4f6; color: #6b7280; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>