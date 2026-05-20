<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import i18n from '../i18n/index.js'
import api from '../services/api.js'

const emit = defineEmits(['toggle-sidebar'])
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const unreadCount = ref(0)
const giftApprovalCount = ref(0)
const taskUnreadCount = ref(0)
const recentReminders = ref([])
const showReminderDropdown = ref(false)

const totalBadge = computed(() => unreadCount.value + giftApprovalCount.value + taskUnreadCount.value)

// Map Chinese meta titles/parents to i18n keys for breadcrumb translation
const metaI18nMap = {
  '工作台': 'nav.dashboard',
  '商品管理': 'nav.products',
  '出入库管理': 'nav.inout',
  '仓库列表': 'nav.warehouseList',
  '仓库详情': 'nav.warehouseDetail',
  '库存预警': 'nav.alerts',
  '审批列表': 'nav.approvalList',
  '审批详情': 'nav.approvalDetail',
  '零售记录': 'nav.retail',
  '赠送审批': 'nav.giftApprovals',
  '售后管理': 'nav.aftersale',
  '调货管理': 'nav.transfer',
  '创建调货单': 'nav.transferCreate',
  '调货详情': 'nav.transferDetail',
  '退货记录': 'nav.returnRecords',
  '我的团队': 'nav.myTeam',
  'AI 自动化': 'nav.aiAutomation',
  '企业微信': 'nav.wecom',
  'OA 办公': 'nav.oa',
  '考勤管理': 'nav.attendance',
  '工作日志': 'nav.workLogs',
  '我的权责': 'nav.myResponsibility',
  '审批管理': 'nav.approvalManage',
  '发起审批': 'nav.createApproval',
  '通讯录': 'nav.directory',
  '班次管理': 'nav.shifts',
  '排班日历': 'nav.schedule',
  '考勤统计': 'nav.attendanceSummary',
  '请假管理': 'nav.leave',
  '工作流管理': 'nav.workflow',
  '任务管理': 'nav.tasks',
  '任务统计': 'nav.taskStats',
  '拜访日志': 'nav.visitLogs',
  '分享日志': 'nav.shareLogs',
  '投诉建议': 'nav.feedback',
  '一物一码': 'nav.qrcode',
  '报表中心': 'nav.reports',
  '个人信息': 'nav.profile',
  '系统设置': 'nav.settings',
  '用户管理': 'nav.userManagement',
  'H5用户管理': 'nav.h5Users',
  '职位权责管理': 'nav.jobResponsibilities',
  '权责管理': 'nav.responsibilityManage',
  '财务总览': 'nav.financeOverview',
  '采购成本': 'nav.purchaseCosts',
  '销售收入': 'nav.salesRevenues',
  '费用支出': 'nav.expenses',
  '应付款管理': 'nav.accountsPayable',
  '应收款管理': 'nav.accountsReceivable',
  '利润分析': 'nav.profitAnalysis',
  '资金账户': 'nav.fundAccounts',
  '资金流水': 'nav.cashFlow',
  '供货商对账': 'nav.supplierStatement',
  '客户对账': 'nav.customerStatement',
  '发票管理': 'nav.invoices',
  '发票统计': 'nav.invoiceStatistics',
  '财务提醒': 'nav.financeReminders',
  '提醒设置': 'nav.reminderSettings',
  '审批设置': 'nav.approvalSettings',
  '供货商管理': 'nav.supplierManage',
  '经销商管理': 'nav.dealerManage',
  '门店管理': 'nav.storeManage',
  '库存管理': 'nav.inventory',
  '仓库管理': 'nav.warehouses',
  '审批中心': 'nav.approvals',
  '日志系统': 'nav.logs',
  '财务管理': 'nav.finance',
  '合作伙伴': 'nav.partners',
  '消息': 'nav.messages',
  'OpenClaw': 'nav.openClaw',
}

function translateMeta(text) {
  if (!text) return ''
  const key = metaI18nMap[text]
  return key ? t(key) : text
}

const breadcrumbs = computed(() => {
  const items = [{ label: t('nav.dashboard'), to: '/' }]
  if (route.meta.parent) {
    items.push({ label: translateMeta(route.meta.parent) })
  }
  if (route.meta.title && route.meta.title !== '工作台') {
    items.push({ label: translateMeta(route.meta.title) })
  }
  return items
})

async function toggleLocale() {
  const newLocale = i18n.global.locale.value === 'zh' ? 'en' : 'zh'
  if (i18n.setLocaleMessage) {
    await i18n.setLocaleMessage(newLocale)
  } else {
    i18n.global.locale.value = newLocale
  }
  localStorage.setItem('caimeite_locale', newLocale)
}

const fetchUnreadCount = async () => {
  try {
    const data = await api.get('/finance-simple/reminders/unread-count')
    if (data.code === 0) {
      unreadCount.value = data.data.count
    }
  } catch (error) {
    console.error('获取未读提醒数量失败:', error)
  }
}

const fetchGiftApprovalCount = async () => {
  try {
    const data = await api.get('/gift-approvals/pending-count')
    if (data.code === 0) {
      giftApprovalCount.value = data.data.count
    }
  } catch (error) {
    // ignore
  }
}

const fetchTaskUnreadCount = async () => {
  try {
    const data = await api.get('/tasks/unread-count')
    if (data.code === 0) {
      taskUnreadCount.value = data.data.count
    }
  } catch (error) {
    // ignore
  }
}

const fetchRecentReminders = async () => {
  try {
    const data = await api.get('/finance-simple/reminders/recent')
    if (data.code === 0) {
      recentReminders.value = data.data
    }
  } catch (error) {
    console.error('获取最近提醒失败:', error)
  }
}

const toggleReminderDropdown = async () => {
  showReminderDropdown.value = !showReminderDropdown.value
  if (showReminderDropdown.value) {
    await fetchRecentReminders()
  }
}

const viewAllReminders = () => {
  showReminderDropdown.value = false
  router.push('/finance/reminders')
}

const formatDate = (dateStr) => {
  const date = new Date(dateStr)
  const now = new Date()
  const diff = now - date
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)

  if (minutes < 60) return i18n.global.locale.value === 'zh' ? `${minutes}分钟前` : `${minutes}m ago`
  if (hours < 24) return i18n.global.locale.value === 'zh' ? `${hours}小时前` : `${hours}h ago`
  return i18n.global.locale.value === 'zh' ? `${days}天前` : `${days}d ago`
}

onMounted(() => {
  fetchUnreadCount()
  fetchGiftApprovalCount()
  fetchTaskUnreadCount()
  // 每分钟刷新一次
  setInterval(fetchUnreadCount, 60000)
  setInterval(fetchGiftApprovalCount, 60000)
  setInterval(fetchTaskUnreadCount, 60000)
  // 监听任务已读事件（TaskManage进入"我的任务"Tab时触发）
  window.addEventListener('tasks-read', fetchTaskUnreadCount)
})
</script>

<template>
  <header class="h-14 md:h-16 bg-white border-b border-border flex items-center justify-between px-3 md:px-6 shadow-card z-10 shrink-0">
    <div class="flex items-center gap-3">
      <!-- Hamburger (mobile only) -->
      <button @click="$emit('toggle-sidebar')" class="lg:hidden p-2 text-text-secondary hover:bg-gray-100 rounded-lg transition-colors">
        <span class="material-symbols-outlined text-[22px]">menu</span>
      </button>
      <!-- Breadcrumb (desktop) -->
      <nav class="hidden md:flex text-sm text-text-regular items-center">
        <template v-for="(crumb, i) in breadcrumbs" :key="i">
          <router-link v-if="crumb.to" :to="crumb.to" class="hover:text-primary cursor-pointer">{{ crumb.label }}</router-link>
          <span v-else :class="i === breadcrumbs.length - 1 ? 'font-medium text-text-primary' : ''">{{ crumb.label }}</span>
          <span v-if="i < breadcrumbs.length - 1" class="mx-2 text-gray-400">/</span>
        </template>
      </nav>
      <!-- Page title on mobile -->
      <span class="md:hidden font-medium text-text-primary text-sm">{{ translateMeta(route.meta.title) || $t('system.name') }}</span>
    </div>
    <div class="flex items-center gap-2 md:gap-4">
      <!-- Search (desktop) -->
      <div class="hidden md:flex items-center bg-gray-50 rounded-full px-3 py-1.5 border border-transparent focus-within:border-primary focus-within:bg-white transition-all w-64">
        <span class="material-symbols-outlined text-text-secondary text-[20px]">search</span>
        <input type="text" :placeholder="$t('common.search') + '...'" class="bg-transparent border-none focus:ring-0 focus:outline-none text-sm w-full text-text-primary placeholder-text-secondary ml-2" />
      </div>
      <!-- Language toggle -->
      <button @click="toggleLocale" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-medium text-text-primary hover:bg-gray-50 transition-colors">
        <span class="material-symbols-outlined text-[16px]">language</span>
        {{ i18n.global.locale.value === 'zh' ? 'EN' : '中' }}
      </button>
      <!-- Notifications / Reminders -->
      <div class="relative">
        <button
          @click="toggleReminderDropdown"
          class="relative p-2 text-text-regular hover:bg-gray-100 rounded-full transition-colors"
        >
          <span class="material-symbols-outlined text-[22px]">notifications</span>
          <span
            v-if="totalBadge > 0"
            class="absolute top-1 right-1 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1"
          >
            {{ totalBadge > 99 ? '99+' : totalBadge }}
          </span>
        </button>

        <!-- Dropdown -->
        <div
          v-if="showReminderDropdown"
          class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border z-50"
          @click.stop
        >
          <div class="p-3 border-b flex items-center justify-between">
            <h3 class="font-semibold">{{ $t('finance.reminders.title') }}</h3>
            <span class="text-sm text-gray-500">{{ unreadCount }} {{ $t('finance.reminders.unread') }}</span>
          </div>

          <div class="max-h-96 overflow-y-auto">
            <div v-if="recentReminders.length === 0" class="p-6 text-center text-gray-500">
              {{ $t('finance.reminders.noUnread') }}
            </div>
            <div
              v-for="reminder in recentReminders"
              :key="reminder.id"
              class="p-3 border-b hover:bg-gray-50 cursor-pointer"
              @click="viewAllReminders"
            >
              <div class="flex items-start gap-2">
                <span
                  class="material-symbols-outlined text-[20px] mt-0.5"
                  :class="{
                    'text-red-500': reminder.priority === 'high',
                    'text-yellow-500': reminder.priority === 'medium',
                    'text-blue-500': reminder.priority === 'low'
                  }"
                >
                  {{ reminder.reminder_type === 'monthly_report' ? 'description' : 'warning' }}
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ reminder.title }}</p>
                  <p class="text-xs text-gray-500 mt-1">{{ formatDate(reminder.created_at) }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="p-3 border-t">
            <button
              @click="viewAllReminders"
              class="w-full text-center text-sm text-blue-600 hover:text-blue-700 font-medium"
            >
              {{ $t('finance.reminders.viewAll') }}
            </button>
          </div>
        </div>
      </div>
      <!-- Help (desktop) -->
      <button class="hidden md:flex p-2 text-text-regular hover:bg-gray-100 rounded-full transition-colors">
        <span class="material-symbols-outlined text-[22px]">help</span>
      </button>
    </div>
  </header>
</template>
