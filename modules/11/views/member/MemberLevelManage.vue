<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('nav.memberLevelManage') || '会员等级管理' }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('level.subtitle') || '成长值、会员等级、权益体系' }}</p>
      </div>
    </div>

    <!-- 标签页 -->
    <div class="flex gap-4 mb-6 border-b border-gray-200">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium transition-colors', activeTab === tab.key ? 'text-primary border-b-2 border-primary' : 'text-gray-500 hover:text-gray-700']">
        {{ tab.label }}
      </button>
    </div>

    <!-- 等级列表 -->
    <div v-if="activeTab === 'levels'" class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">{{ $t('level.levelList') || '会员等级' }}</h2>
        <button @click="openLevelDialog()" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.add') || '新增等级' }}</button>
      </div>

      <div class="grid grid-cols-4 gap-4 mb-6">
        <div v-for="level in levels" :key="level.id" 
          class="border rounded-xl p-4 hover:shadow-md transition-shadow relative overflow-hidden"
          :class="level.is_default ? 'ring-2 ring-primary' : ''">
          <div v-if="level.is_default" class="absolute top-0 right-0 bg-primary text-white text-xs px-2 py-1 rounded-bl">默认</div>
          <div class="text-center">
            <div class="text-2xl mb-2">{{ level.icon || '👑' }}</div>
            <div class="font-bold text-lg">{{ level.name }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ level.min_points }} ~ {{ level.max_points || '∞' }}</div>
            <div class="mt-3 space-y-1">
              <div v-if="level.discount_rate > 0" class="text-sm text-green-600">购物{{ (level.discount_rate * 100).toFixed(0) }}折</div>
              <div v-if="level.points_ratio > 1" class="text-sm text-blue-600">{{ level.points_ratio }}倍积分</div>
              <div v-if="level.birthday_double" class="text-sm text-pink-600">生日双倍积分</div>
              <div v-if="level.free_shipping" class="text-sm text-orange-600">免运费</div>
            </div>
          </div>
          <div class="flex gap-2 mt-4">
            <button @click="openLevelDialog(level)" class="flex-1 px-3 py-1 text-sm border rounded hover:bg-gray-50">{{ $t('common.edit') || '编辑' }}</button>
            <button v-if="!level.is_default" @click="deleteLevel(level)" class="flex-1 px-3 py-1 text-sm border rounded hover:bg-gray-50 text-red-600">{{ $t('common.delete') || '删除' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 会员列表 -->
    <div v-if="activeTab === 'members'" class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
      <div class="flex gap-4 mb-4">
        <input v-model="filters.keyword" :placeholder="$t('level.searchUser') || '搜索用户'"
          class="px-4 py-2 border rounded-lg w-64" />
        <select v-model="filters.level_id" class="px-4 py-2 border rounded-lg">
          <option value="">{{ $t('level.allLevels') || '全部等级' }}</option>
          <option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
        <button @click="loadMembers" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.search') || '搜索' }}</button>
      </div>

      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('level.user') || '用户' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('level.level') || '等级' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('level.currentGrowth') || '当前成长值' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('level.totalGrowth') || '累计成长值' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('level.nextLevel') || '距下一级' }}</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">{{ $t('common.actions') || '操作' }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="m in members" :key="m.id" class="hover:bg-gray-50/50">
            <td class="px-4 py-3">
              <div class="font-medium">{{ m.name }}</div>
              <div class="text-xs text-gray-400">{{ m.phone }}</div>
            </td>
            <td class="px-4 py-3">
              <span class="px-3 py-1 rounded-full text-sm font-medium" :style="{ backgroundColor: levelColor(m.level_name) + '20', color: levelColor(m.level_name) }">
                {{ m.level_name }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="text-xl font-bold" :style="{ color: levelColor(m.level_name) }">{{ m.current_points }}</span>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ m.total_points }}</td>
            <td class="px-4 py-3 text-gray-500">
              <div v-if="m.next_level_name" class="text-sm">
                距 <span class="font-medium">{{ m.next_level_name }}</span> 还需 {{ m.next_level_min - m.current_points }}
              </div>
              <div v-else class="text-green-600 text-sm">已满级</div>
            </td>
            <td class="px-4 py-3">
              <button @click="openAdjust(m)" class="text-primary text-sm hover:underline">{{ $t('level.adjustGrowth') || '调整成长值' }}</button>
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

    <!-- 成长值规则 -->
    <div v-if="activeTab === 'rules'" class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
      <h2 class="text-lg font-bold mb-4">{{ $t('level.growthRules') || '成长值规则' }}</h2>
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('level.behavior') || '行为' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('level.growthValue') || '成长值' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('level.dailyLimit') || '每日上限' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('invite.status') || '状态' }}</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">{{ $t('common.actions') || '操作' }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="r in rules" :key="r.id">
            <td class="px-4 py-3 font-medium">{{ r.name }}</td>
            <td class="px-4 py-3 text-green-600 font-bold">+{{ r.points }}</td>
            <td class="px-4 py-3 text-gray-500">{{ r.daily_limit || '无限制' }}</td>
            <td class="px-4 py-3">
              <span :class="r.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="text-xs px-2 py-1 rounded-full">
                {{ r.status === 'active' ? '启用' : '禁用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="toggleRule(r)" class="text-sm hover:opacity-70" :class="r.status === 'active' ? 'text-orange-600' : 'text-green-600'">
                {{ r.status === 'active' ? '禁用' : '启用' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 等级编辑弹窗 -->
    <div v-if="levelDialogVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
        <h3 class="text-lg font-bold mb-4">{{ levelForm.id ? ($t('common.edit') || '编辑') : ($t('common.add') || '新增') }}会员等级</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('level.levelName') || '等级名称' }}</label>
            <input v-model="levelForm.name" class="w-full px-3 py-2 border rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('level.levelIcon') || '等级图标' }}</label>
            <input v-model="levelForm.icon" class="w-full px-3 py-2 border rounded-lg" placeholder="emoji或图标URL" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('level.minPoints') || '最低成长值' }}</label>
            <input v-model.number="levelForm.min_points" type="number" class="w-full px-3 py-2 border rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('level.maxPoints') || '最高成长值' }}</label>
            <input v-model.number="levelForm.max_points" type="number" class="w-full px-3 py-2 border rounded-lg" placeholder="留空表示无上限" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('level.discountRate') || '购物折扣' }} (%)</label>
            <input v-model.number="levelForm.discount_rate" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" placeholder="0.05=95折" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('level.pointsRatio') || '积分比例' }}</label>
            <input v-model.number="levelForm.points_ratio" type="number" step="0.1" class="w-full px-3 py-2 border rounded-lg" placeholder="1=1倍" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mt-4">
          <label class="flex items-center gap-2">
            <input type="checkbox" v-model="levelForm.birthday_double" class="w-4 h-4" />
            <span>{{ $t('level.birthdayDouble') || '生日双倍积分' }}</span>
          </label>
          <label class="flex items-center gap-2">
            <input type="checkbox" v-model="levelForm.free_shipping" class="w-4 h-4" />
            <span>{{ $t('level.freeShipping') || '免运费' }}</span>
          </label>
          <label class="flex items-center gap-2">
            <input type="checkbox" v-model="levelForm.exclusive_access" class="w-4 h-4" />
            <span>{{ $t('level.exclusiveAccess') || '专属活动参与权' }}</span>
          </label>
          <label class="flex items-center gap-2">
            <input type="checkbox" v-model="levelForm.priority_customer" class="w-4 h-4" />
            <span>{{ $t('level.priorityCustomer') || '优先客服' }}</span>
          </label>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="levelDialogVisible = false" class="px-4 py-2 border rounded-lg">{{ $t('common.cancel') || '取消' }}</button>
          <button @click="submitLevel" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.save') || '保存' }}</button>
        </div>
      </div>
    </div>

    <!-- 调整成长值弹窗 -->
    <div v-if="adjustDialogVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">{{ $t('level.adjustGrowth') || '调整成长值' }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('level.user') || '用户' }}</label>
            <input :value="adjustForm.name + ' - ' + adjustForm.phone" disabled class="w-full px-3 py-2 bg-gray-100 border rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('level.currentGrowth') || '当前成长值' }}</label>
            <input :value="adjustForm.current_points" disabled class="w-full px-3 py-2 bg-gray-100 border rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('level.adjustValue') || '调整值' }}</label>
            <input v-model.number="adjustForm.change_amount" type="number" class="w-full px-3 py-2 border rounded-lg" placeholder="正数增加，负数减少" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('level.remark') || '备注' }}</label>
            <input v-model="adjustForm.remark" class="w-full px-3 py-2 border rounded-lg" />
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="adjustDialogVisible = false" class="px-4 py-2 border rounded-lg">{{ $t('common.cancel') || '取消' }}</button>
          <button @click="submitAdjust" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('common.save') || '保存' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import request from '@/api/request'

const activeTab = ref('levels')
const tabs = [
  { key: 'levels', label: '会员等级' },
  { key: 'members', label: '会员列表' },
  { key: 'rules', label: '成长值规则' }
]

const levels = ref([])
const members = ref([])
const rules = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const filters = ref({ keyword: '', level_id: '' })

const levelDialogVisible = ref(false)
const adjustDialogVisible = ref(false)
const levelForm = ref({})
const adjustForm = ref({})

const totalPages = computed(() => Math.ceil(total.value / pageSize.value))

const levelColor = (name) => {
  const map = { '普通会员': '#999', '银卡会员': '#C0C0C0', '金卡会员': '#FFD700', '黑金会员': '#1a1a1a' }
  return map[name] || '#666'
}

const loadLevels = async () => {
  try {
    const res = await request.get('/api/member-level/admin/levels')
    if (res.code === 0) levels.value = res.data
  } catch (err) { console.error(err) }
}

const loadMembers = async () => {
  try {
    const res = await request.get('/api/member-level/admin/members', { params: { page: page.value, pageSize: pageSize.value, ...filters.value } })
    if (res.code === 0) {
      members.value = res.data.list
      total.value = res.data.total
    }
  } catch (err) { console.error(err) }
}

const loadRules = async () => {
  try {
    const res = await request.get('/api/member-level/admin/rules')
    if (res.code === 0) rules.value = res.data
  } catch (err) { console.error(err) }
}

const openLevelDialog = (l = {}) => {
  levelForm.value = l.id ? { ...l, discount_rate: l.discount_rate * 100 || 0 } : {
    name: '', icon: '', min_points: 0, max_points: null, discount_rate: 0, points_ratio: 1,
    birthday_double: false, free_shipping: false, exclusive_access: false, priority_customer: false, status: 'active', sort_order: 0
  }
  levelDialogVisible.value = true
}

const submitLevel = async () => {
  try {
    const data = { ...levelForm.value, discount_rate: (levelForm.value.discount_rate || 0) / 100 }
    if (data.id) {
      await request.put(`/api/member-level/admin/level/${data.id}`, data)
    } else {
      await request.post('/api/member-level/admin/level', data)
    }
    levelDialogVisible.value = false
    loadLevels()
  } catch (err) { console.error(err) }
}

const deleteLevel = async (l) => {
  if (!confirm(`确定删除等级「${l.name}」吗？`)) return
  try {
    await request.delete(`/api/member-level/admin/level/${l.id}`)
    loadLevels()
  } catch (err) { console.error(err) }
}

const openAdjust = (m) => {
  adjustForm.value = { user_id: m.user_id, name: m.name, phone: m.phone, current_points: m.current_points, change_amount: 0, remark: '' }
  adjustDialogVisible.value = true
}

const submitAdjust = async () => {
  try {
    await request.post('/api/member-level/admin/adjust', {
      user_id: adjustForm.value.user_id,
      change_amount: adjustForm.value.change_amount,
      remark: adjustForm.value.remark
    })
    adjustDialogVisible.value = false
    loadMembers()
  } catch (err) { console.error(err) }
}

const toggleRule = async (r) => {
  try {
    await request.put(`/api/member-level/admin/rules/${r.id}`, { ...r, status: r.status === 'active' ? 'inactive' : 'active' })
    loadRules()
  } catch (err) { console.error(err) }
}

onMounted(() => {
  loadLevels()
  loadMembers()
  loadRules()
})
</script>