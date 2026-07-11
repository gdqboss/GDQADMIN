<template>
  <MinipLayout title="会议管理" :canBack="true">
    <div class="summary-row">
      <div class="sum-cell"><strong>{{ stats.today }}</strong><span>今日</span></div>
      <div class="sum-cell"><strong>{{ stats.upcoming }}</strong><span>即将</span></div>
      <div class="sum-cell"><strong>{{ stats.total }}</strong><span>总数</span></div>
    </div>

    <button class="new-btn" @click="newMeeting">+ 预约会议室</button>

    <div class="section-title">会议列表</div>
    <div class="meeting-list">
      <div v-for="m in list" :key="m.id" class="meeting-card">
        <div class="meet-time">
          <div class="meet-date">{{ m.date }}</div>
          <div class="meet-clock">{{ m.time }}</div>
        </div>
        <div class="meet-main">
          <div class="meet-title">{{ m.title }}</div>
          <div class="meet-room">📍 {{ m.room }}</div>
          <div class="meet-attendees">👥 {{ m.attendees }} 人</div>
        </div>
      </div>
      <div v-if="!list.length" class="empty">暂无会议</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/request'
import MinipLayout from './MinipLayout.vue'

const list = ref([])
const stats = ref({ today: 0, upcoming: 0, total: 0 })

function newMeeting() {
  alert('会议室预约功能开发中')
}

onMounted(async () => {
  try {
    const r = await api.get('/oa/meetings?limit=20')
    if (r.code === 0) {
      list.value = r.data || []
      stats.value.total = list.value.length
    }
  } catch {
    list.value = [
      { id: 1, title: 'Q3 运营复盘', room: '大会议室', date: '07-11', time: '14:00-16:00', attendees: 8 },
      { id: 2, title: '财务月度会议', room: '财务室', date: '07-12', time: '10:00-11:00', attendees: 4 },
      { id: 3, title: '产品需求评审', room: '小会议室', date: '07-12', time: '15:30-17:00', attendees: 5 }
    ]
    stats.value = { today: 1, upcoming: 2, total: 12 }
  }
})
</script>

<style scoped>
.summary-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 12px;
}
.sum-cell {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  text-align: center;
}
.sum-cell strong {
  display: block;
  font-size: 20px;
  font-weight: 700;
  color: #6366f1;
}
.sum-cell span {
  font-size: 11px;
  color: #6b7280;
  margin-top: 2px;
}
.new-btn {
  width: 100%;
  background: #6366f1;
  color: #fff;
  border: 0;
  border-radius: 10px;
  padding: 12px;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 16px;
  cursor: pointer;
}
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 0 4px 8px;
}
.meeting-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.meeting-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  gap: 12px;
  align-items: center;
}
.meet-time {
  text-align: center;
  background: #eef2ff;
  color: #6366f1;
  border-radius: 8px;
  padding: 8px 6px;
  min-width: 64px;
}
.meet-date { font-size: 14px; font-weight: 700; }
.meet-clock { font-size: 10px; opacity: 0.8; margin-top: 2px; }
.meet-main { flex: 1; }
.meet-title { font-size: 14px; font-weight: 600; color: #1f2329; }
.meet-room, .meet-attendees {
  font-size: 11px;
  color: #6b7280;
  margin-top: 2px;
}
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>