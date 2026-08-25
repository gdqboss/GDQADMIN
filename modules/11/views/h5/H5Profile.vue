<template>
  <div class="min-h-screen bg-slate-50">
    <!-- 用户信息头部 — 渐变 + 毛玻璃效果 -->
    <div class="bg-gradient-to-br from-primary via-blue-500 to-indigo-600 px-5 pt-10 pb-20 text-white relative overflow-hidden">
      <!-- 装饰圆 -->
      <div class="absolute -top-16 -right-16 w-40 h-40 bg-white/10 rounded-full"></div>
      <div class="absolute bottom-4 -left-8 w-24 h-24 bg-white/5 rounded-full"></div>
      <div class="flex items-center gap-4 relative">
        <div class="w-16 h-16 rounded-full bg-white/25 backdrop-blur-sm flex items-center justify-center text-2xl font-bold shadow-lg ring-2 ring-white/30">
          {{ userInfo.name?.[0] || '?' }}
        </div>
        <div>
          <div class="font-bold text-xl">{{ userInfo.name || '未登录' }}</div>
          <div class="text-xs opacity-80 mt-1 flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">phone</span>
            {{ userInfo.phone || '' }}
          </div>
        </div>
        <button @click="$router.push('/h5/profile/edit')" class="ml-auto bg-white/20 backdrop-blur-sm rounded-full px-3 py-1.5 text-xs flex items-center gap-1 hover:bg-white/30 transition-all">
          <span class="material-symbols-outlined text-sm">edit</span>
          编辑
        </button>
      </div>
    </div>

    <!-- 会员卡 — 悬浮卡片 -->
    <div class="mx-4 -mt-12 bg-white rounded-2xl shadow-lg p-5 relative z-10 border border-slate-50">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-[11px] text-slate-400 flex items-center gap-1">
            <span class="material-symbols-outlined text-sm text-amber-400" style="font-variation-settings: 'FILL' 1">stars</span>
            会员等级
          </div>
          <div class="font-bold text-primary mt-0.5 flex items-center gap-1">
            {{ userInfo.level_name || '普通会员' }}
            <span class="material-symbols-outlined text-base text-amber-400">verified</span>
          </div>
        </div>
        <div class="text-right">
          <div class="text-[11px] text-slate-400">积分</div>
          <div class="font-bold text-xl text-slate-800 mt-0.5">{{ userInfo.score || 0 }}</div>
        </div>
        <button @click="$router.push('/h5/score-detail')"
          class="text-[11px] text-primary flex items-center gap-0.5 hover:underline">
          明细
          <span class="material-symbols-outlined text-sm">chevron_right</span>
        </button>
      </div>
    </div>

    <!-- 订单快捷入口 -->
    <div class="mx-4 mt-4 bg-white rounded-2xl p-4 shadow-sm">
      <div class="flex items-center justify-between mb-3">
        <span class="font-semibold text-sm text-slate-700 flex items-center gap-1.5">
          <span class="material-symbols-outlined text-lg text-primary">receipt_long</span>
          我的订单
        </span>
        <button @click="$router.push('/h5/orders')"
          class="text-[11px] text-slate-400 flex items-center gap-0.5 hover:text-primary transition-colors">
          全部订单
          <span class="material-symbols-outlined text-sm">chevron_right</span>
        </button>
      </div>
      <div class="grid grid-cols-4 gap-1">
        <button v-for="s in orderStatuses" :key="s.value"
          @click="$router.push('/h5/orders?status=' + s.value)"
          class="flex flex-col items-center gap-1 py-2 rounded-xl hover:bg-slate-50 transition-all active:scale-95">
          <div class="relative">
            <span class="material-symbols-outlined text-2xl text-slate-600" :style="s.iconFill ? 'font-variation-settings: \'FILL\' 1' : ''">{{ s.icon }}</span>
            <span v-if="s.count > 0"
              class="absolute -top-1 -right-1 min-w-[16px] h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-1 shadow-sm">
              {{ s.count > 99 ? '99+' : s.count }}
            </span>
          </div>
          <span class="text-[10px] text-slate-500">{{ s.label }}</span>
        </button>
      </div>
    </div>

    <!-- 功能菜单 -->
    <div class="mx-4 mt-4 bg-white rounded-2xl overflow-hidden shadow-sm divide-y divide-slate-50">
      <button v-for="m in menuItems" :key="m.path"
        @click="$router.push(m.path)"
        class="w-full flex items-center gap-3 px-4 py-3.5 text-sm hover:bg-slate-50 transition-all active:scale-[0.99]">
        <div class="w-8 h-8 rounded-xl flex items-center justify-center"
          :style="{ background: m.bgColor || '#f1f5f9' }">
          <span class="material-symbols-outlined text-lg" :style="{ color: m.iconColor || '#64748b' }">{{ m.icon }}</span>
        </div>
        <span class="flex-1 text-left text-slate-700 font-medium">{{ m.label }}</span>
        <span class="material-symbols-outlined text-sm text-slate-300">chevron_right</span>
      </button>
    </div>

    <!-- 退出登录 -->
    <div class="mx-4 mt-4 mb-6">
      <button @click="logout"
        class="w-full py-3 bg-white rounded-2xl text-sm text-red-400 font-medium shadow-sm hover:shadow-md transition-all active:scale-[0.98] border border-slate-100">
        退出登录
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const userInfo = ref({})

const orderStatuses = [
  { value: 'pending_pay', label: '待付款', icon: 'payments', iconFill: false, count: 0 },
  { value: 'paid', label: '已付款', icon: 'check_circle', iconFill: true, count: 0 },
  { value: 'shipped', label: '待收货', icon: 'local_shipping', iconFill: false, count: 0 },
  { value: 'completed', label: '已完成', icon: 'task_alt', iconFill: true, count: 0 },
]

const menuItems = [
  { path: '/h5/address/list', label: '收货地址', icon: 'location_on', bgColor: '#eff6ff', iconColor: '#3b82f6' },
  { path: '/h5/coupons', label: '我的优惠券', icon: 'confirmation_number', bgColor: '#fff7ed', iconColor: '#f97316' },
  { path: '/h5/score-history', label: '积分记录', icon: 'monetization_on', bgColor: '#fefce8', iconColor: '#eab308' },
  { path: '/h5/wallet', label: '我的钱包', icon: 'account_balance_wallet', bgColor: '#f0fdf4', iconColor: '#22c55e' },
  { path: '/h5/settings', label: '账户设置', icon: 'settings', bgColor: '#f8fafc', iconColor: '#64748b' },
]

onMounted(() => {
  const token = localStorage.getItem('mall_token')
  if (token) {
    fetch('/api/h5/me', { headers: { Authorization: 'Bearer ' + token } })
      .then(r => r.json())
      .then(res => { if (res.code === 0) userInfo.value = res.data })
      .catch(() => {})
  }
  // 加载订单数量
  fetch('/api/mall/orders', {
    headers: { Authorization: 'Bearer ' + (localStorage.getItem('mall_token') || '') }
  })
    .then(r => r.json())
    .then(res => {
      if (res.code === 0) {
        const list = res.data?.list || []
        orderStatuses[0].count = list.filter(o => o.status === 'pending_pay').length
        orderStatuses[1].count = list.filter(o => o.status === 'paid').length
        orderStatuses[2].count = list.filter(o => o.status === 'shipped').length
        orderStatuses[3].count = list.filter(o => o.status === 'completed').length
      }
    })
    .catch(() => {})
})

function logout() {
  localStorage.removeItem('mall_token')
  localStorage.removeItem('mall_user_id')
  localStorage.removeItem('mall_user_name')
  router.push('/h5')
}
</script>
