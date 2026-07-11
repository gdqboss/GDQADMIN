<template>
  <MinipLayout title="排班管理" :canBack="true">
    <div class="week-nav">
      <button @click="shiftWeek(-1)">‹</button>
      <span>{{ weekLabel }}</span>
      <button @click="shiftWeek(1)">›</button>
    </div>

    <div class="shift-grid">
      <div class="shift-cell header"></div>
      <div v-for="d in weekDays" :key="d.date" class="shift-cell header">{{ d.label }}</div>

      <template v-for="emp in employees" :key="emp.id">
        <div class="shift-cell name">{{ emp.name }}</div>
        <div v-for="d in weekDays" :key="d.date + emp.id" class="shift-cell slot" :class="getShift(emp.id, d.date)">
          <span class="shift-code">{{ getShiftCode(emp.id, d.date) }}</span>
        </div>
      </template>
    </div>

    <div class="legend">
      <span class="lg-cell"><i class="dot ok"></i>早班</span>
      <span class="lg-cell"><i class="dot mid"></i>中班</span>
      <span class="lg-cell"><i class="dot night"></i>晚班</span>
      <span class="lg-cell"><i class="dot off"></i>休息</span>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/request'
import MinipLayout from './MinipLayout.vue'

const weekOffset = ref(0)
const weekDays = ref([])
const weekLabel = ref('')
const employees = ref([])
const schedule = ref({})

function buildWeek() {
  const now = new Date()
  const monday = new Date(now)
  monday.setDate(now.getDate() - now.getDay() + 1 + weekOffset.value * 7)
  const days = []
  for (let i = 0; i < 7; i++) {
    const d = new Date(monday)
    d.setDate(monday.getDate() + i)
    days.push({
      date: d.toISOString().slice(0, 10),
      label: ['一', '二', '三', '四', '五', '六', '日'][i]
    })
  }
  weekDays.value = days
  weekLabel.value = `${monday.getMonth() + 1}月${monday.getDate()}日 周`
}
function shiftWeek(d) {
  weekOffset.value += d
  buildWeek()
  load()
}
function getShift(empId, date) {
  return schedule.value[`${empId}_${date}`] || ''
}
function getShiftCode(empId, date) {
  const code = schedule.value[`${empId}_${date}`]
  return { morning: '早', noon: '中', night: '晚', off: '休' }[code] || ''
}

onMounted(async () => {
  buildWeek()
  try {
    const r = await api.get('/oa/employees?limit=10')
    if (r.code === 0) employees.value = r.data || []
  } catch {
    employees.value = [
      { id: 1, name: '小李' },
      { id: 2, name: '王芳' },
      { id: 3, name: '老张' }
    ]
  }
  await load()
})

async function load() {
  try {
    const r = await api.get(`/oa/shifts?start=${weekDays.value[0]?.date}&end=${weekDays.value[6]?.date}`)
    if (r.code === 0) {
      const map = {}
      ;(r.data || []).forEach(s => { map[`${s.employee_id}_${s.date}`] = s.shift })
      schedule.value = map
    }
  } catch {
    // 模拟数据
    const map = {}
    employees.value.forEach((e, i) => {
      weekDays.value.forEach((d, j) => {
        const r = (i + j) % 4
        map[`${e.id}_${d.date}`] = ['morning', 'noon', 'night', 'off'][r]
      })
    })
    schedule.value = map
  }
}
</script>

<style scoped>
.week-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  border-radius: 10px;
  padding: 8px 12px;
  margin-bottom: 8px;
}
.week-nav button {
  width: 28px;
  height: 28px;
  background: #f3f4f6;
  border: 0;
  border-radius: 50%;
  font-size: 16px;
  color: #4b5563;
  cursor: pointer;
}
.week-nav span { font-size: 13px; color: #1f2329; font-weight: 500; }

.shift-grid {
  display: grid;
  grid-template-columns: 60px repeat(7, 1fr);
  gap: 2px;
  background: #fff;
  border-radius: 10px;
  overflow: hidden;
  font-size: 11px;
}
.shift-cell {
  padding: 8px 4px;
  text-align: center;
  border-right: 1px solid #f5f6f8;
}
.shift-cell.header {
  background: #f9fafb;
  font-weight: 600;
  color: #4b5563;
}
.shift-cell.name {
  background: #f9fafb;
  font-weight: 500;
  color: #1f2329;
}
.shift-cell.slot {
  font-weight: 600;
  color: #fff;
}
.shift-cell.slot.morning { background: #d1fae5; color: #065f46; }
.shift-cell.slot.noon { background: #dbeafe; color: #1e40af; }
.shift-cell.slot.night { background: #ede9fe; color: #5b21b6; }
.shift-cell.slot.off { background: #f3f4f6; color: #6b7280; }
.shift-code { font-size: 12px; font-weight: 700; }

.legend {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-top: 12px;
  flex-wrap: wrap;
}
.lg-cell {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: #6b7280;
}
.lg-cell .dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.lg-cell .dot.ok { background: #d1fae5; }
.lg-cell .dot.mid { background: #dbeafe; }
.lg-cell .dot.night { background: #ede9fe; }
.lg-cell .dot.off { background: #f3f4f6; }
</style>