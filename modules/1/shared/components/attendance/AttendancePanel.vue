<!--
  AttendancePanel - 出勤共享面板 (2026-09-02 立)

  三端共用: 打卡按钮 + 今日记录 + 统计卡
  Props:
   - api: axios instance
   - adapter: 'gdqadmin' | 'minip' | 'labor'
   - userId: 当前用户ID
   - showStats: 是否显示统计
-->
<template>
  <div :class="style.cardClass">
    <!-- 打卡按钮 (主交互) -->
    <div class="grid grid-cols-2 gap-3 mb-4">
      <button
        @click="handleCheckIn"
        :disabled="saving || hasCheckedIn"
        :class="style.buttonCheckIn + (hasCheckedIn || saving ? ' opacity-50' : '')"
      >
        {{ hasCheckedIn ? '✓ 已上班打卡' : '🟢 上班打卡' }}
      </button>
      <button
        @click="handleCheckOut"
        :disabled="saving || !hasCheckedIn || hasCheckedOut"
        :class="style.buttonCheckOut + (!hasCheckedIn || hasCheckedOut || saving ? ' opacity-50' : '')"
      >
        {{ hasCheckedOut ? '✓ 已下班打卡' : '🟡 下班打卡' }}
      </button>
    </div>

    <!-- 今日打卡记录 -->
    <div v-if="todayRecords.length" class="space-y-2 mb-4">
      <div v-for="r in todayRecords" :key="r.id" :class="style.listItemClass">
        <div class="flex items-center justify-between">
          <span class="text-sm">
            <span v-if="r.check_in_at">📍 {{ formatTime(r.check_in_at) }}</span>
            <span v-if="r.check_out_at"> → {{ formatTime(r.check_out_at) }}</span>
          </span>
          <span class="text-xs text-slate-500">{{ calcHours(r) }}h</span>
        </div>
        <div v-if="r.location" class="text-xs text-slate-400 mt-1">📍 {{ r.location }}</div>
      </div>
    </div>

    <!-- 统计 -->
    <div v-if="showStats && stats" class="grid grid-cols-3 gap-3 pt-3 border-t border-slate-100">
      <div class="text-center">
        <div class="text-2xl font-bold text-slate-900">{{ stats.total_days || 0 }}</div>
        <div class="text-xs text-slate-500">出勤天数</div>
      </div>
      <div class="text-center">
        <div class="text-2xl font-bold text-green-600">{{ stats.late_count || 0 }}</div>
        <div class="text-xs text-slate-500">迟到</div>
      </div>
      <div class="text-center">
        <div class="text-2xl font-bold text-amber-600">{{ stats.early_count || 0 }}</div>
        <div class="text-xs text-slate-500">早退</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAttendance } from '../../composables/useAttendance.js'

const props = defineProps({
  api: { type: Object, required: true },
  adapter: { type: String, default: 'gdqadmin' },
  userId: { type: [Number, String], default: null },
  showStats: { type: Boolean, default: true }
})

const emit = defineEmits(['checked-in', 'checked-out', 'error'])

const {
  todayRecords, stats, saving,
  checkIn, checkOut, fetchToday, fetchStats, calcWorkHours, getAdapter
} = useAttendance(props.api)

const style = computed(() => getAdapter(props.adapter))

const hasCheckedIn = computed(() => todayRecords.value.some(r => r.check_in_at))
const hasCheckedOut = computed(() => todayRecords.value.some(r => r.check_out_at))

onMounted(() => {
  fetchToday()
  if (props.showStats) fetchStats({ user_id: props.userId })
})

async function handleCheckIn() {
  const result = await checkIn({ user_id: props.userId })
  if (result.ok) {
    emit('checked-in', result.data)
    await fetchToday()
  } else {
    emit('error', result)
    alert(result.message || '打卡失败')
  }
}

async function handleCheckOut() {
  const result = await checkOut({ user_id: props.userId })
  if (result.ok) {
    emit('checked-out', result.data)
    await fetchToday()
  } else {
    emit('error', result)
    alert(result.message || '打卡失败')
  }
}

function formatTime(d) {
  if (!d) return ''
  return new Date(d).toLocaleTimeString('zh-CN', { hour12: false }).slice(0, 5)
}
</script>