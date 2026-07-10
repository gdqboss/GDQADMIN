<template>
  <div class="min-h-screen bg-gradient-to-b from-green-50 to-white flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center">
      <!-- 成功图标 -->
      <div class="mx-auto w-24 h-24 rounded-full bg-green-100 flex items-center justify-center mb-6 animate-bounce">
        <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
        </svg>
      </div>

      <!-- 标题 -->
      <h1 class="text-2xl font-bold text-gray-800 mb-2">预约成功！</h1>
      <p class="text-sm text-gray-500 mb-8">{{ bookingTypeLabel }}已确认</p>

      <!-- 详情卡片 -->
      <div class="bg-white rounded-2xl shadow-lg p-6 text-left space-y-4">
        <div v-if="bookingDetails.venue">
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-sm text-gray-500">会议室</span>
            <span class="text-sm font-medium text-gray-800">{{ bookingDetails.venue }}</span>
          </div>
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-sm text-gray-500">日期</span>
            <span class="text-sm font-medium text-gray-800">{{ bookingDetails.date }}</span>
          </div>
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-sm text-gray-500">时间</span>
            <span class="text-sm font-medium text-gray-800">{{ bookingDetails.time }}</span>
          </div>
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-sm text-gray-500">主题</span>
            <span class="text-sm font-medium text-gray-800">{{ bookingDetails.title }}</span>
          </div>
          <div class="flex items-center justify-between py-2">
            <span class="text-sm text-gray-500">人数</span>
            <span class="text-sm font-medium text-gray-800">{{ bookingDetails.attendees }} 人</span>
          </div>
        </div>
        <div v-else-if="bookingDetails.type === 'butler'">
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-sm text-gray-500">工单类型</span>
            <span class="text-sm font-medium text-gray-800">{{ bookingDetails.serviceType }}</span>
          </div>
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-sm text-gray-500">主题</span>
            <span class="text-sm font-medium text-gray-800">{{ bookingDetails.title }}</span>
          </div>
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-sm text-gray-500">SLA 截止</span>
            <span class="text-sm font-medium text-red-600">{{ bookingDetails.sla }}</span>
          </div>
          <div class="flex items-center justify-between py-2">
            <span class="text-sm text-gray-500">提交时间</span>
            <span class="text-sm font-medium text-gray-800">{{ bookingDetails.time }}</span>
          </div>
        </div>
      </div>

      <!-- 提示 -->
      <div class="mt-6 bg-blue-50 rounded-xl p-4 text-sm text-blue-700">
        💡 提示：您可在「企业中心 → 我的预约/工单」查看详情
      </div>

      <!-- 操作按钮 -->
      <div class="mt-8 space-y-3">
        <button @click="goHome" class="w-full bg-gradient-to-r from-purple-600 to-purple-700 text-white py-3 rounded-xl font-medium shadow-md">
          返回首页
        </button>
        <button @click="viewDetail" class="w-full bg-white border border-gray-200 text-gray-700 py-3 rounded-xl font-medium">
          查看详情
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()

const bookingType = computed(() => route.query.type || 'venue')
const bookingDetails = computed(() => {
  if (bookingType.value === 'butler') {
    return {
      type: 'butler',
      serviceType: route.query.serviceType || 'IT 支持',
      title: route.query.title || '管家服务',
      sla: route.query.sla || '24 小时内',
      time: route.query.time || new Date().toLocaleString('zh-CN')
    }
  }
  return {
    type: 'venue',
    venue: route.query.venue || '一号会议室',
    date: route.query.date || new Date().toISOString().slice(0, 10),
    time: route.query.time || '10:00 - 11:00',
    title: route.query.title || '周会',
    attendees: parseInt(route.query.attendees) || 1
  }
})

const bookingTypeLabel = computed(() => bookingType.value === 'butler' ? '管家服务' : '会议室预约')

const goHome = () => router.push('/hqh5/guest-home')
const viewDetail = () => router.push(bookingType.value === 'butler' ? '/hqh5/butler-booking' : '/hqh5/venue-booking')

onMounted(() => {
  console.log('[booking-success] 加载参数:', route.query)
})
</script>