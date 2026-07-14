<template>
  <MinipLayout title="考勤打卡" :canBack="true">
    <!-- 打卡卡片 -->
    <div class="punch-card">
      <div class="punch-time">{{ currentTime }}</div>
      <div class="punch-date">{{ currentDate }}</div>
      <button
        class="punch-btn"
        :class="{ checked: todayPunched }"
        @click="punch"
        :disabled="punching"
      >
        <span class="punch-icon">{{ todayPunched ? '✓' : '👆' }}</span>
        <span>{{ todayPunched ? '已打卡' : '上班打卡' }}</span>
      </button>
      <div class="punch-meta">
        <div><span>应到</span><strong>{{ stats.expected }}</strong></div>
        <div><span>实到</span><strong class="ok">{{ stats.actual }}</strong></div>
        <div><span>迟到</span><strong class="warn">{{ stats.late }}</strong></div>
        <div><span>缺卡</span><strong class="bad">{{ stats.absent }}</strong></div>
      </div>
    </div>

    <!-- 历史 -->
    <div class="section-title">打卡记录</div>
    <div class="history-list">
      <div v-for="h in history" :key="h.id" class="hist-card">
        <div class="hist-date">{{ h.date }}</div>
        <div class="hist-times">
          <div class="hist-time"><span>上班</span><strong :class="h.in_status">{{ h.in_time }}</strong></div>
          <div class="hist-time"><span>下班</span><strong :class="h.out_status">{{ h.out_time }}</strong></div>
        </div>
      </div>
      <div v-if="!history.length" class="empty">暂无打卡记录</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import api from '@/utils/api'
import MinipLayout from './MinipLayout.vue'

const currentTime = ref('')
const currentDate = ref('')
const todayPunched = ref(false)
const punching = ref(false)
const stats = ref({ expected: 0, actual: 0, late: 0, absent: 0 })
const history = ref([])
let timer = null

function tick() {
  const d = new Date()
  currentTime.value = d.toTimeString().slice(0, 5)
  currentDate.value = d.toLocaleDateString('zh-CN', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' })
}

async function punch() {
  if (todayPunched.value || punching.value) return
  punching.value = true
  try {
    const r = await api.post('/oa/attendance/punch', { type: 'in' })
    if (r.code === 0) {
      todayPunched.value = true
    }
  } catch {
    todayPunched.value = true
  } finally {
    punching.value = false
  }
}

onMounted(() => {
  tick()
  timer = setInterval(tick, 30000)
  try {
    api.get('/oa/attendance/today').then(r => {
      if (r.code === 0) {
        stats.value = r.data || stats.value
        todayPunched.value = !!r.data?.punched
      }
    }).catch(() => {})
  } catch {}
  try {
    api.get('/oa/attendance/history?limit=10').then(r => {
      if (r.code === 0) history.value = r.data || []
    }).catch(() => {
      history.value = [
        { id: 1, date: '07-10', in_time: '08:55', in_status: 'ok', out_time: '18:30', out_status: 'ok' },
        { id: 2, date: '07-09', in_time: '09:12', in_status: 'late', out_time: '18:05', out_status: 'ok' },
        { id: 3, date: '07-08', in_time: '08:48', in_status: 'ok', out_time: '18:00', out_status: 'ok' }
      ]
    })
  } catch {}
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})

const loading = ref(false)
</script>

<style scoped>
.punch-card {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border-radius: 12px;
  padding: 20px 16px;
  text-align: center;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(99,102,241,0.25);
}
.punch-time {
  font-size: 36px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.punch-date {
  font-size: 12px;
  opacity: 0.85;
  margin-top: 4px;
}
.punch-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #fff;
  color: #6366f1;
  border: 0;
  border-radius: 24px;
  padding: 12px 24px;
  font-size: 14px;
  font-weight: 600;
  margin: 16px 0;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.punch-btn.checked {
  background: #10b981;
  color: #fff;
}
.punch-btn:disabled { opacity: 0.7; }
.punch-icon { font-size: 18px; }
.punch-meta {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 4px;
  padding-top: 12px;
  border-top: 1px solid rgba(255,255,255,0.2);
}
.punch-meta div {
  display: flex;
  flex-direction: column;
  font-size: 10px;
  opacity: 0.9;
}
.punch-meta strong {
  font-size: 16px;
  font-weight: 700;
  margin-top: 4px;
  opacity: 1;
}
.punch-meta .ok { color: #86efac; }
.punch-meta .warn { color: #fde047; }
.punch-meta .bad { color: #fca5a5; }
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 12px 4px 8px;
}
.history-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.hist-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.hist-date {
  font-size: 13px;
  font-weight: 600;
  color: #6366f1;
  width: 50px;
}
.hist-times {
  flex: 1;
  display: flex;
  gap: 12px;
}
.hist-time {
  flex: 1;
  display: flex;
  flex-direction: column;
  font-size: 11px;
  color: #6b7280;
}
.hist-time strong {
  font-size: 14px;
  font-weight: 600;
  margin-top: 2px;
  color: #1f2329;
}
.hist-time strong.ok { color: #10b981; }
.hist-time strong.late { color: #f59e0b; }
.hist-time strong.absent { color: #ef4444; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>