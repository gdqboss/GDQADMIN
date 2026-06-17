<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('nav.inviteManage') || '邀请返现管理' }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('invite.subtitle') || '邀请奖励、红包裂变' }}</p>
      </div>
    </div>

    <!-- 标签页 -->
    <div class="flex gap-4 mb-6 border-b border-gray-200">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium transition-colors', activeTab === tab.key ? 'text-primary border-b-2 border-primary' : 'text-gray-500 hover:text-gray-700']">
        {{ tab.label }}
      </button>
    </div>

    <!-- 邀请配置 -->
    <div v-if="activeTab === 'config'" class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">{{ $t('invite.inviteConfig') || '邀请奖励配置' }}</h2>
        <button @click="openConfigDialog()" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.add') || '新增' }}</button>
      </div>

      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.name') || '活动名称' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.rewardType') || '奖励类型' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.rewardAmount') || '奖励金额' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.minRecharge') || '最低门槛' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.status') || '状态' }}</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">{{ $t('common.actions') || '操作' }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="c in configs" :key="c.id">
            <td class="px-4 py-3 font-medium">{{ c.name }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 text-xs rounded-full" :class="typeClass(c.reward_type)">{{ typeLabel(c.reward_type) }}</span>
            </td>
            <td class="px-4 py-3 text-green-600 font-bold">¥{{ c.reward_amount }}</td>
            <td class="px-4 py-3 text-gray-500">满¥{{ c.min_recharge }}</td>
            <td class="px-4 py-3">
              <span :class="c.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="text-xs px-2 py-1 rounded-full">
                {{ c.status === 'active' ? '启用' : '禁用' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2 justify-center">
                <button @click="openConfigDialog(c)" class="text-primary text-sm">{{ $t('common.edit') || '编辑' }}</button>
                <button @click="toggleConfig(c)" class="text-sm hover:opacity-70" :class="c.status === 'active' ? 'text-orange-600' : 'text-green-600'">
                  {{ c.status === 'active' ? '禁用' : '启用' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 邀请关系列表 -->
    <div v-if="activeTab === 'relations'" class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
      <div class="flex gap-4 mb-4">
        <input v-model="filters.keyword" :placeholder="$t('invite.searchPlaceholder') || '搜索用户'"
          class="px-4 py-2 border rounded-lg w-64" />
        <button @click="loadRelations" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.search') || '搜索' }}</button>
      </div>

      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.inviter') || '邀请人' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.invitee') || '被邀请人' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.inviteCode') || '邀请码' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.rewardStatus') || '奖励状态' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.createTime') || '注册时间' }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="r in relations" :key="r.id">
            <td class="px-4 py-3">
              <div class="font-medium">{{ r.inviter_name }}</div>
              <div class="text-xs text-gray-400">{{ r.inviter_phone }}</div>
            </td>
            <td class="px-4 py-3">
              <div class="font-medium">{{ r.invitee_name }}</div>
              <div class="text-xs text-gray-400">{{ r.invitee_phone }}</div>
            </td>
            <td class="px-4 py-3 font-mono text-sm bg-gray-100 px-2 py-1 rounded">{{ r.invite_code }}</td>
            <td class="px-4 py-3">
              <span :class="{
                'bg-yellow-100 text-yellow-700': r.reward_status === 'pending',
                'bg-green-100 text-green-700': r.reward_status === 'rewarded',
                'bg-gray-100 text-gray-500': r.reward_status === 'expired'
              }" class="text-xs px-2 py-1 rounded-full">
                {{ { pending: '待奖励', rewarded: '已奖励', expired: '已失效' }[r.reward_status] }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(r.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 红包配置 -->
    <div v-if="activeTab === 'redpacket'" class="space-y-4">
      <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold">{{ $t('invite.redpacketConfig') || '红包配置' }}</h2>
          <button @click="openRedpacketDialog()" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.add') || '新增红包' }}</button>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div v-for="r in redpackets" :key="r.id" class="border rounded-xl p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="font-bold">{{ r.name }}</span>
              <span :class="r.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="text-xs px-2 py-1 rounded-full">
                {{ r.status === 'active' ? '进行中' : '已结束' }}
              </span>
            </div>
            <div class="text-2xl font-bold text-red-600 mb-2">¥{{ r.total_amount }}</div>
            <div class="text-sm text-gray-500">
              <div>{{ $t('invite.totalCount') || '总个数' }}: {{ r.total_count }}</div>
              <div>{{ $t('invite.remain') || '剩余' }}: ¥{{ r.total_remain }} / {{ r.count_remain }}个</div>
            </div>
            <div class="flex gap-2 mt-4">
              <button @click="openRedpacketDialog(r)" class="flex-1 px-3 py-1 text-sm border rounded hover:bg-gray-50">{{ $t('common.edit') || '编辑' }}</button>
              <button @click="sendRedpacket(r)" class="flex-1 px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700">{{ $t('invite.send') || '发放' }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 配置弹窗 -->
    <div v-if="configDialogVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">{{ $t('invite.inviteConfig') || '邀请奖励配置' }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('invite.name') || '活动名称' }}</label>
            <input v-model="configForm.name" class="w-full px-3 py-2 border rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('invite.rewardType') || '奖励类型' }}</label>
            <select v-model="configForm.reward_type" class="w-full px-3 py-2 border rounded-lg">
              <option value="balance">{{ $t('invite.balance') || '余额' }}</option>
              <option value="cash">{{ $t('invite.cash') || '现金' }}</option>
              <option value="coupon">{{ $t('invite.coupon') || '优惠券' }}</option>
              <option value="points">{{ $t('invite.points') || '积分' }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('invite.rewardAmount') || '奖励金额' }}</label>
            <input v-model.number="configForm.reward_amount" type="number" class="w-full px-3 py-2 border rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('invite.minRecharge') || '最低门槛' }}</label>
            <input v-model.number="configForm.min_recharge" type="number" class="w-full px-3 py-2 border rounded-lg" />
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
import { ref, onMounted } from 'vue'
import request from '@/api/request'

const activeTab = ref('config')
const tabs = [
  { key: 'config', label: '邀请奖励' },
  { key: 'relations', label: '邀请关系' },
  { key: 'redpacket', label: '红包管理' }
]

const configs = ref([])
const relations = ref([])
const redpackets = ref([])
const filters = ref({ keyword: '' })
const configDialogVisible = ref(false)
const configForm = ref({ id: null, name: '', reward_type: 'balance', reward_amount: 0, min_recharge: 0 })

const typeLabel = (t) => ({ balance: '余额', cash: '现金', coupon: '优惠券', points: '积分' }[t] || t)
const typeClass = (t) => ({ balance: 'bg-blue-100 text-blue-700', cash: 'bg-green-100 text-green-700', coupon: 'bg-orange-100 text-orange-700', points: 'bg-purple-100 text-purple-700' }[t] || 'bg-gray-100 text-gray-700')
const formatDate = (d) => d ? new Date(d).toLocaleDateString('zh-CN') : ''

const loadConfigs = async () => {
  try {
    const res = await request.get('/api/invite/admin/config')
    if (res.code === 0) configs.value = res.data
  } catch (err) { console.error(err) }
}

const loadRelations = async () => {
  try {
    const res = await request.get('/api/invite/admin/list', { params: filters.value })
    if (res.code === 0) relations.value = res.data.list
  } catch (err) { console.error(err) }
}

const loadRedpackets = async () => {
  try {
    const res = await request.get('/api/invite/admin/redpacket/configs')
    if (res.code === 0) redpackets.value = res.data
  } catch (err) { console.error(err) }
}

const openConfigDialog = (c = {}) => {
  configForm.value = c.id ? { ...c } : { id: null, name: '', reward_type: 'balance', reward_amount: 0, min_recharge: 0 }
  configDialogVisible.value = true
}

const submitConfig = async () => {
  try {
    if (configForm.value.id) {
      await request.put(`/api/invite/admin/config/${configForm.value.id}`, configForm.value)
    } else {
      await request.post('/api/invite/admin/config', configForm.value)
    }
    configDialogVisible.value = false
    loadConfigs()
  } catch (err) { console.error(err) }
}

const toggleConfig = async (c) => {
  try {
    await request.put(`/api/invite/admin/config/${c.id}`, { ...c, status: c.status === 'active' ? 'inactive' : 'active' })
    loadConfigs()
  } catch (err) { console.error(err) }
}

const openRedpacketDialog = (r = {}) => {
  alert('请在红包配置页面编辑')
}

const sendRedpacket = (r) => {
  const userIds = prompt('请输入用户ID，多个用逗号分隔')
  if (!userIds) return
  request.post('/api/invite/admin/redpacket/send', {
    config_id: r.id,
    user_ids: userIds.split(',').map(id => parseInt(id.trim()))
  }).then(res => {
    if (res.code === 0) alert('发放成功')
  })
}

onMounted(() => {
  loadConfigs()
  loadRelations()
  loadRedpackets()
})
</script>