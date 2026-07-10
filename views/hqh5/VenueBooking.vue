<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部 -->
    <div class="bg-white p-4 sticky top-0 z-10 shadow-sm">
      <h1 class="text-lg font-bold text-gray-800">会议室预约</h1>
    </div>

    <!-- 会议室列表 -->
    <div class="p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-bold text-gray-800">选择会议室</h2>
        <span class="text-xs text-gray-500">今日 {{ todayStr }}</span>
      </div>
      <div v-if="loading" class="text-center py-8 text-gray-400">加载中...</div>
      <div v-else class="grid grid-cols-1 gap-3">
        <div
          v-for="v in venues"
          :key="v.id"
          @click="selectVenue(v)"
          :class="['bg-white rounded-xl p-4 shadow-sm border-2 cursor-pointer transition',
                   selectedVenue?.id === v.id ? 'border-purple-600' : 'border-transparent']"
        >
          <div class="flex items-center justify-between mb-2">
            <h3 class="font-medium text-gray-800">{{ v.name }}</h3>
            <span class="text-xs bg-purple-100 text-purple-600 px-2 py-0.5 rounded">容纳 {{ v.capacity }} 人</span>
          </div>
          <p class="text-xs text-gray-500 mb-1">📍 {{ v.location }}</p>
          <p class="text-xs text-gray-500">🛠 {{ v.facilities }}</p>
          <p class="text-xs text-purple-600 mt-2">今日已有 {{ v.today_bookings }} 个预约</p>
        </div>
      </div>
    </div>

    <!-- 日期选择 -->
    <div v-if="selectedVenue" class="px-4">
      <h2 class="text-base font-bold text-gray-800 mb-3">选择日期</h2>
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <input type="date" v-model="selectedDate" :min="todayStr" class="w-full p-2 border rounded-lg" />
      </div>
    </div>

    <!-- 时段选择 -->
    <div v-if="selectedVenue && selectedDate" class="p-4">
      <h2 class="text-base font-bold text-gray-800 mb-3">选择时段</h2>
      <div v-if="loadingSlots" class="text-center py-4 text-gray-400">加载时段中...</div>
      <div v-else class="grid grid-cols-3 gap-2">
        <button
          v-for="slot in slots"
          :key="slot.time"
          @click="selectSlot(slot)"
          :disabled="!slot.available"
          :class="['p-3 rounded-lg text-sm font-medium transition',
                   !slot.available ? 'bg-gray-100 text-gray-400 cursor-not-allowed' :
                   selectedSlot?.time === slot.time ? 'bg-purple-600 text-white' :
                   'bg-white text-gray-700 border border-gray-200 hover:border-purple-400']"
        >
          <div>{{ slot.time }}</div>
          <div v-if="!slot.available" class="text-xs mt-1">已占</div>
        </button>
      </div>
    </div>

    <!-- 预约表单 -->
    <div v-if="selectedSlot" class="px-4">
      <h2 class="text-base font-bold text-gray-800 mb-3">填写预约信息</h2>
      <div class="bg-white rounded-xl p-4 shadow-sm space-y-3">
        <div>
          <label class="text-sm text-gray-600 block mb-1">会议主题</label>
          <input v-model="booking.title" placeholder="如：周会 / 评审 / 培训" class="w-full p-2 border rounded-lg" />
        </div>
        <div>
          <label class="text-sm text-gray-600 block mb-1">参与人数</label>
          <input v-model.number="booking.attendees" type="number" min="1" class="w-full p-2 border rounded-lg" />
        </div>
        <div>
          <label class="text-sm text-gray-600 block mb-1">备注</label>
          <textarea v-model="booking.remark" rows="2" class="w-full p-2 border rounded-lg"></textarea>
        </div>
        <div class="bg-purple-50 rounded-lg p-3 text-sm text-purple-700">
          📅 {{ selectedDate }} {{ selectedSlot.time }} - {{ nextSlot(selectedSlot.time) }} · {{ selectedVenue.name }}
        </div>
        <button
          @click="submitBooking"
          :disabled="submitting || !booking.title"
          class="w-full bg-gradient-to-r from-purple-600 to-purple-700 text-white py-3 rounded-xl font-medium shadow-md disabled:opacity-50"
        >{{ submitting ? '提交中...' : '确认预约' }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'

const router = useRouter()
const venues = ref([])
const slots = ref([])
const loading = ref(false)
const loadingSlots = ref(false)
const submitting = ref(false)
const selectedVenue = ref(null)
const selectedDate = ref('')
const selectedSlot = ref(null)
const booking = ref({ title: '', attendees: 1, remark: '' })

const todayStr = computed(() => new Date().toISOString().slice(0, 10))

const nextSlot = (time) => {
  const h = parseInt(time.split(':')[0]) + 1
  return `${String(h).padStart(2, '0')}:00`
}

const selectVenue = (v) => {
  selectedVenue.value = v
  selectedSlot.value = null
  loadSlots()
}

const selectSlot = (slot) => {
  if (!slot.available) return
  selectedSlot.value = slot
}

const loadVenues = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/hqh5/venues/list')
    venues.value = res.data?.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const loadSlots = async () => {
  if (!selectedVenue.value || !selectedDate.value) return
  loadingSlots.value = true
  try {
    const res = await axios.get(`/api/hqh5/venues/availability?venue_id=${selectedVenue.value.id}&date=${selectedDate.value}`)
    slots.value = res.data?.data?.slots || []
  } catch (e) {
    console.error(e)
  } finally {
    loadingSlots.value = false
  }
}

const submitBooking = async () => {
  submitting.value = true
  try {
    const res = await axios.post('/api/hqh5/venues/book', {
      venue_id: selectedVenue.value.id,
      user_id: 1,
      user_name: '江清波',
      title: booking.value.title,
      date: selectedDate.value,
      start_time: selectedSlot.value.time + ':00',
      end_time: nextSlot(selectedSlot.value.time) + ':00',
      attendees: booking.value.attendees,
      remark: booking.value.remark
    })
    if (res.data?.code === 0) {
      ElMessage.success('预约成功！')
      setTimeout(() => router.push('/hqh5/booking-success'), 500)
    } else if (res.data?.code === 409) {
      ElMessage.error(res.data.message || '时段冲突')
    } else {
      ElMessage.error(res.data?.message || '预约失败')
    }
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '网络错误')
  } finally {
    submitting.value = false
  }
}

watch(selectedDate, () => loadSlots())
onMounted(() => {
  loadVenues()
  selectedDate.value = todayStr.value
})
</script>