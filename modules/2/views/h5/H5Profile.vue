<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <!-- 用户信息头部 -->
    <div class="bg-gradient-to-r from-primary to-blue-500 px-4 pt-8 pb-16 text-white">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center text-xl font-bold">
          {{ userInfo.name?.[0] || '?' }}
        </div>
        <div>
          <div class="font-bold text-lg">{{ userInfo.name || '未登录' }}</div>
          <div class="text-xs opacity-80 mt-1">{{ userInfo.phone || '' }}</div>
        </div>
      </div>
    </div>

    <!-- 会员卡 -->
    <div class="mx-4 -mt-10 bg-white rounded-2xl shadow-lg p-4 relative z-10">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-xs text-slate-500">会员等级</div>
          <div class="font-bold text-primary mt-1">{{ userInfo.level_name || '普通会员' }}</div>
        </div>
        <div class="text-right">
          <div class="text-xs text-slate-500">积分</div>
          <div class="font-bold text-lg text-slate-800 mt-1">{{ userInfo.score || 0 }}</div>
        </div>
        <button @click="$router.push('/h5/score-detail')" class="text-xs text-primary">明细 →</button>
      </div>
    </div>

    <!-- 订单快捷入口 -->
    <div class="mx-4 mt-4 bg-white rounded-xl p-4">
      <div class="flex items-center justify-between mb-3">
        <span class="font-medium text-sm">我的订单</span>
        <button @click="$router.push('/h5/orders')" class="text-xs text-slate-400">全部订单 →</button>
      </div>
      <div class="grid grid-cols-4 gap-2">
        <button v-for="s in orderStatuses" :key="s.value"
          @click="$router.push('/h5/orders?status='+s.value)"
          class="flex flex-col items-center gap-1 py-2">
          <div class="relative">
            <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="s.icon"/>
            </svg>
            <span v-if="s.count" class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">{{ s.count }}</span>
          </div>
          <span class="text-xs text-slate-600">{{ s.label }}</span>
        </button>
      </div>
    </div>

    <!-- 功能菜单 -->
    <div class="mx-4 mt-4 bg-white rounded-xl divide-y">
      <button v-for="m in menuItems" :key="m.path"
        @click="$router.push(m.path)"
        class="w-full flex items-center gap-3 px-4 py-3.5 text-sm">
        <svg class="w-5 h-5" :class="m.color" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="m.icon"/>
        </svg>
        <span class="flex-1 text-left">{{ m.label }}</span>
        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>

    <!-- 底部导航 -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t flex z-30">
      <button v-for="tab in tabs" :key="tab.path" @click="$router.push(tab.path)"
        :class="['flex-1 py-2 flex flex-col items-center gap-0.5 text-xs',
          $route.path === tab.path ? 'text-primary' : 'text-slate-400']">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon"/>
        </svg>
        {{ tab.label }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const userInfo = ref({})
const orderStatuses = [
  { value: 'pending_pay', label: '待付款', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', count: 0 },
  { value: 'paid', label: '已付款', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', count: 0 },
  { value: 'shipped', label: '待收货', icon: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4', count: 0 },
  { value: 'completed', label: '已完成', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', count: 0 },
]
const menuItems = [
  { path: '/h5/address/list', label: '收货地址', icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z', color: 'text-blue-500' },
  { path: '/h5/coupons', label: '我的优惠券', icon: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z', color: 'text-orange-500' },
  { path: '/h5/score-history', label: '积分记录', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: 'text-yellow-500' },
  { path: '/h5/wallet', label: '我的钱包', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', color: 'text-green-500' },
  { path: '/h5/settings', label: '账户设置', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', color: 'text-slate-500' },
]

const tabs = [
  { path: '/h5/home', label: '首页', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { path: '/h5/categories', label: '分类', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
  { path: '/h5/cart', label: '购物车', icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' },
  { path: '/h5/profile', label: '我的', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
]

onMounted(() => {
  const token = localStorage.getItem('mall_token')
  if (token) {
    fetch('/api/h5/me', { headers: { Authorization: 'Bearer ' + token } })
      .then(r => r.json())
      .then(res => { if (res.code === 0) userInfo.value = res.data })
  }
  // 加载订单数量
  fetch('/api/mall/orders', { headers: { Authorization: 'Bearer ' + (localStorage.getItem('mall_token') || '') } })
    .then(r => r.json())
    .then(res => {
      if (res.code === 0) {
        const list = res.data?.list || []
        orderStatuses[0].count = list.filter(o => o.status === 'pending_pay').length
        orderStatuses[1].count = list.filter(o => o.status === 'paid').length
        orderStatuses[2].count = list.filter(o => o.status === 'shipped').length
      }
    })
})
</script>
