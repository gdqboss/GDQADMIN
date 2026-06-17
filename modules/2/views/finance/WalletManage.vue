<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('nav.walletManage') || '会员钱包管理' }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('wallet.subtitle') || '储值、提现、余额管理' }}</p>
      </div>
      <button @click="activeTab = 'config'" 
        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
        {{ $t('wallet.rechargeConfig') || '充值配置' }}
      </button>
    </div>

    <!-- 标签页 -->
    <div class="flex gap-4 mb-6 border-b border-gray-200">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium transition-colors', activeTab === tab.key ? 'text-primary border-b-2 border-primary' : 'text-gray-500 hover:text-gray-700']">
        {{ tab.label }}
      </button>
    </div>

    <!-- 钱包列表 -->
    <div v-if="activeTab === 'list'" class="space-y-4">
      <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex gap-4 mb-4">
          <input v-model="filters.keyword" :placeholder="$t('wallet.searchUser') || '搜索用户姓名/手机'"
            class="px-4 py-2 border rounded-lg w-64 focus:outline-none focus:ring-2 focus:ring-primary/20" />
          <select v-model="filters.status" class="px-4 py-2 border rounded-lg">
            <option value="">{{ $t('wallet.allStatus') || '全部状态' }}</option>
            <option value="normal">{{ $t('wallet.normal') || '正常' }}</option>
            <option value="frozen">{{ $t('wallet.frozen') || '已冻结' }}</option>
          </select>
          <button @click="loadWallets" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.search') || '搜索' }}</button>
        </div>

        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.user') || '用户' }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.balance') || '余额' }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.totalRecharge') || '累计充值' }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.totalWithdraw') || '累计提现' }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.status') || '状态' }}</th>
              <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">{{ $t('common.actions') || '操作' }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="w in list" :key="w.id" class="hover:bg-gray-50/50">
              <td class="px-4 py-3">
                <div class="font-medium">{{ w.name }}</div>
                <div class="text-xs text-gray-400">{{ w.phone }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="text-xl font-bold text-green-600">¥{{ w.balance }}</span>
              </td>
              <td class="px-4 py-3 text-gray-600">¥{{ w.total_recharge }}</td>
              <td class="px-4 py-3 text-gray-600">¥{{ w.total_withdraw }}</td>
              <td class="px-4 py-3">
                <span :class="w.status === 'frozen' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'" class="text-xs px-2 py-1 rounded-full">
                  {{ w.status === 'frozen' ? ($t('wallet.frozen') || '已冻结') : ($t('wallet.normal') || '正常') }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex gap-2 justify-center">
                  <button @click="openAdjust(w)" class="text-primary hover:text-primary/80 text-sm">{{ $t('wallet.adjust') || '调整' }}</button>
                  <button @click="openLogs(w)" class="text-blue-600 hover:text-blue-700 text-sm">{{ $t('wallet.logs') || '明细' }}</button>
                  <button v-if="w.status === 'normal'" @click="freezeWallet(w)" class="text-orange-600 hover:text-orange-700 text-sm">{{ $t('wallet.freeze') || '冻结' }}</button>
                  <button v-else @click="unfreezeWallet(w)" class="text-green-600 hover:text-green-700 text-sm">{{ $t('wallet.unfreeze') || '解冻' }}</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- 分页 -->
        <div class="flex justify-between items-center mt-4 pt-4 border-t">
          <div class="text-sm text-gray-500">{{ $t('common.total') || '共' }} {{ total }} {{ $t('common.records') || '条' }}</div>
          <div class="flex gap-2">
            <button @click="page--" :disabled="page <= 1" class="px-3 py-1 border rounded disabled:opacity-50">{{ $t('common.prev') || '上一页' }}</button>
            <span class="px-3 py-1">{{ page }} / {{ totalPages }}</span>
            <button @click="page++" :disabled="page >= totalPages" class="px-3 py-1 border rounded disabled:opacity-50">{{ $t('common.next') || '下一页' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 充值配置 -->
    <div v-if="activeTab === 'config'" class="space-y-4">
      <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold">{{ $t('wallet.rechargeConfig') || '充值配置' }}</h2>
          <button @click="openConfigDialog()" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.add') || '新增' }}</button>
        </div>

        <div class="grid grid-cols-4 gap-4">
          <div v-for="c in rechargeConfigs" :key="c.id" class="border rounded-xl p-4 hover:shadow-md transition-shadow">
            <div class="text-center">
              <div class="text-2xl font-bold text-gray-800">¥{{ c.amount }}</div>
              <div v-if="c.gift_amount > 0" class="text-sm text-green-600 mt-1">送 ¥{{ c.gift_amount }}</div>
              <div v-else-if="c.gift_ratio > 0" class="text-sm text-green-600 mt-1">送 {{ (c.gift_ratio * 100).toFixed(0) }}%</div>
              <div class="text-xs text-gray-400 mt-2">{{ c.name }}</div>
            </div>
            <div class="flex gap-2 mt-4">
              <button @click="openConfigDialog(c)" class="flex-1 px-3 py-1 text-sm border rounded hover:bg-gray-50">{{ $t('common.edit') || '编辑' }}</button>
              <button @click="deleteConfig(c)" class="flex-1 px-3 py-1 text-sm border rounded hover:bg-gray-50 text-red-600">{{ $t('common.delete') || '删除' }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 提现申请 -->
    <div v-if="activeTab === 'withdraw'" class="space-y-4">
      <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="mb-4">
          <select v-model="withdrawFilters.status" @change="loadWithdraws" class="px-4 py-2 border rounded-lg">
            <option value="">{{ $t('wallet.allStatus') || '全部状态' }}</option>
            <option value="pending">{{ $t('wallet.pending') || '待处理' }}</option>
            <option value="paid">{{ $t('wallet.paid') || '已打款' }}</option>
            <option value="rejected">{{ $t('wallet.rejected') || '已拒绝' }}</option>
          </select>
        </div>

        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.user') || '用户' }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.withdrawAmount') || '提现金额' }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.fee') || '手续费' }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.realAmount') || '实际到账' }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.bankInfo') || '银行信息' }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('wallet.status') || '状态' }}</th>
              <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">{{ $t('common.actions') || '操作' }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="w in withdrawList" :key="w.id" class="hover:bg-gray-50/50">
              <td class="px-4 py-3">
                <div class="font-medium">{{ w.name }}</div>
                <div class="text-xs text-gray-400">{{ w.phone }}</div>
              </td>
              <td class="px-4 py-3 text-orange-600 font-bold">¥{{ w.amount }}</td>
              <td class="px-4 py-3 text-gray-500">¥{{ w.fee }}</td>
              <td class="px-4 py-3 text-green-600 font-bold">¥{{ w.real_amount }}</td>
              <td class="px-4 py-3">
                <div class="text-sm">{{ w.bank_name }}</div>
                <div class="text-xs text-gray-400">{{ w.bank_account }}</div>
              </td>
              <td class="px-4 py-3">
                <span :class="{
                  'bg-yellow-100 text-yellow-700': w.status === 'pending',
                  'bg-green-100 text-green-700': w.status === 'paid',
                  'bg-red-100 text-red-700': w.status === 'rejected'
                }" class="text-xs px-2 py-1 rounded-full">
                  {{ { pending: $t('wallet.pending') || '待处理', paid: $t('wallet.paid') || '已打款', rejected: $t('wallet.rejected') || '已拒绝' }[w.status] }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div v-if="w.status === 'pending'" class="flex gap-2 justify-center">
                  <button @click="approveWithdraw(w)" class="text-sm text-green-600 hover:text-green-700">{{ $t('wallet.approve') || '通过' }}</button>
                  <button @click="rejectWithdraw(w)" class="text-sm text-red-600 hover:text-red-700">{{ $t('wallet.reject') || '拒绝' }}</button>
                </div>
                <span v-else class="text-sm text-gray-400">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 调整余额弹窗 -->
    <div v-if="adjustDialogVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">{{ $t('wallet.adjustBalance') || '调整余额' }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('wallet.user') || '用户' }}</label>
            <input :value="adjustForm.name + ' - ' + adjustForm.phone" disabled class="w-full px-3 py-2 bg-gray-100 border rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('wallet.currentBalance') || '当前余额' }}</label>
            <input :value="'¥' + adjustForm.balance" disabled class="w-full px-3 py-2 bg-gray-100 border rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('wallet.adjustAmount') || '调整金额' }}</label>
            <input v-model.number="adjustForm.amount" type="number" class="w-full px-3 py-2 border rounded-lg" placeholder="正数增加，负数减少" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('wallet.remark') || '备注' }}</label>
            <input v-model="adjustForm.remark" class="w-full px-3 py-2 border rounded-lg" />
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="adjustDialogVisible = false" class="px-4 py-2 border rounded-lg">{{ $t('common.cancel') || '取消' }}</button>
          <button @click="submitAdjust" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.save') || '保存' }}</button>
        </div>
      </div>
    </div>

    <!-- 充值配置弹窗 -->
    <div v-if="configDialogVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">{{ configForm.id ? ($t('common.edit') || '编辑') : ($t('common.add') || '新增') }}充值配置</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('wallet.configName') || '配置名称' }}</label>
            <input v-model="configForm.name" class="w-full px-3 py-2 border rounded-lg" placeholder="如: 充200送10" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('wallet.rechargeAmount') || '充值金额' }}</label>
            <input v-model.number="configForm.amount" type="number" class="w-full px-3 py-2 border rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('wallet.giftAmount') || '赠送金额' }}</label>
            <input v-model.number="configForm.gift_amount" type="number" class="w-full px-3 py-2 border rounded-lg" placeholder="固定赠送金额" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('wallet.giftRatio') || '赠送比例' }}</label>
            <input v-model.number="configForm.gift_ratio" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" placeholder="如: 0.1 = 10%" />
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="configDialogVisible = false" class="px-4 py-2 border rounded-lg">{{ $t('common.cancel') || '取消' }}</button>
          <button @click="submitConfig" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.save') || '保存' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { walletApi } from '@/api/wallet'

const activeTab = ref('list')
const tabs = [
  { key: 'list', label: '钱包列表' },
  { key: 'config', label: '充值配置' },
  { key: 'withdraw', label: '提现申请' }
]

const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const filters = ref({ keyword: '', status: '' })

const rechargeConfigs = ref([])
const withdrawList = ref([])
const withdrawFilters = ref({ status: '' })

const adjustDialogVisible = ref(false)
const configDialogVisible = ref(false)

const adjustForm = ref({ user_id: null, name: '', phone: '', balance: 0, amount: 0, remark: '' })
const configForm = ref({ id: null, name: '', amount: 0, gift_amount: 0, gift_ratio: 0 })

const totalPages = computed(() => Math.ceil(total.value / pageSize.value))

const loadWallets = async () => {
  try {
    const res = await walletApi.list({ page: page.value, pageSize: pageSize.value, ...filters.value })
    if (res.code === 0) {
      list.value = res.data.list
      total.value = res.data.total
    }
  } catch (err) { console.error(err) }
}

const loadConfigs = async () => {
  try {
    const res = await walletApi.getRechargeConfigs()
    if (res.code === 0) rechargeConfigs.value = res.data
  } catch (err) { console.error(err) }
}

const loadWithdraws = async () => {
  try {
    const res = await walletApi.getWithdrawList({ ...withdrawFilters.value })
    if (res.code === 0) withdrawList.value = res.data.list
  } catch (err) { console.error(err) }
}

const openAdjust = (w) => {
  adjustForm.value = { user_id: w.user_id, name: w.name, phone: w.phone, balance: w.balance, amount: 0, remark: '' }
  adjustDialogVisible.value = true
}

const submitAdjust = async () => {
  try {
    const res = await walletApi.adjust({ user_id: adjustForm.value.user_id, amount: adjustForm.value.amount, remark: adjustForm.value.remark })
    if (res.code === 0) {
      adjustDialogVisible.value = false
      loadWallets()
    }
  } catch (err) { console.error(err) }
}

const freezeWallet = async (w) => {
  try {
    await walletApi.freeze(w.user_id)
    loadWallets()
  } catch (err) { console.error(err) }
}

const unfreezeWallet = async (w) => {
  try {
    await walletApi.unfreeze(w.user_id)
    loadWallets()
  } catch (err) { console.error(err) }
}

const openLogs = (w) => {
  window.open(`/wallet-logs?user_id=${w.user_id}`, '_blank')
}

const openConfigDialog = (c = {}) => {
  configForm.value = c.id ? { ...c } : { id: null, name: '', amount: 0, gift_amount: 0, gift_ratio: 0 }
  configDialogVisible.value = true
}

const submitConfig = async () => {
  try {
    if (configForm.value.id) {
      await walletApi.updateRechargeConfig(configForm.value.id, configForm.value)
    } else {
      await walletApi.createRechargeConfig(configForm.value)
    }
    configDialogVisible.value = false
    loadConfigs()
  } catch (err) { console.error(err) }
}

const deleteConfig = async (c) => {
  if (!confirm('确定删除？')) return
  try {
    await walletApi.deleteRechargeConfig(c.id)
    loadConfigs()
  } catch (err) { console.error(err) }
}

const approveWithdraw = async (w) => {
  try {
    await walletApi.approveWithdraw(w.id, {})
    loadWithdraws()
  } catch (err) { console.error(err) }
}

const rejectWithdraw = async (w) => {
  const reason = prompt('请输入拒绝原因')
  if (!reason) return
  try {
    await walletApi.rejectWithdraw(w.id, { reason })
    loadWithdraws()
  } catch (err) { console.error(err) }
}

onMounted(() => {
  loadWallets()
  loadConfigs()
  loadWithdraws()
})
</script>