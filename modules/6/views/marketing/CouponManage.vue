<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <!-- 页面标题 -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('nav.couponManage') || '优惠券管理' }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('coupon.subtitle') || '创建和管理满减/折扣优惠券' }}</p>
      </div>
      <button @click="openCreateDialog" 
        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
        + {{ $t('coupon.create') || '创建优惠券' }}
      </button>
    </div>

    <!-- 统计看板 -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <div class="text-3xl font-bold text-primary">{{ stats.total }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $t('coupon.totalCoupons') || '优惠券总数' }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <div class="text-3xl font-bold text-green-600">{{ stats.active }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $t('coupon.activeCoupons') || '活动中' }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <div class="text-3xl font-bold text-blue-600">{{ stats.totalClaimed }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $t('coupon.totalClaimed') || '已领取' }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <div class="text-3xl font-bold text-orange-600">{{ stats.totalDiscount }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $t('coupon.totalDiscount') || '优惠总额' }}</div>
      </div>
    </div>

    <!-- 筛选栏 -->
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-6">
      <div class="flex gap-4 items-center">
        <input v-model="filters.keyword" :placeholder="$t('coupon.searchPlaceholder') || '搜索优惠券名称/代码'"
          class="px-4 py-2 border border-gray-200 rounded-lg w-64 focus:outline-none focus:ring-2 focus:ring-primary/20" />
        <select v-model="filters.status" class="px-4 py-2 border border-gray-200 rounded-lg focus:outline-none">
          <option value="">{{ $t('coupon.allStatus') || '全部状态' }}</option>
          <option value="active">{{ $t('coupon.active') || '活动中' }}</option>
          <option value="paused">{{ $t('coupon.paused') || '已暂停' }}</option>
          <option value="expired">{{ $t('coupon.expired') || '已过期' }}</option>
        </select>
        <select v-model="filters.type" class="px-4 py-2 border border-gray-200 rounded-lg focus:outline-none">
          <option value="">{{ $t('coupon.allType') || '全部类型' }}</option>
          <option value="fixed">{{ $t('coupon.fixed') || '立减' }}</option>
          <option value="percentage">{{ $t('coupon.percentage') || '折扣' }}</option>
          <option value="manjian">{{ $t('coupon.manjian') || '满减' }}</option>
        </select>
        <button @click="loadCoupons" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
          {{ $t('common.search') || '搜索' }}
        </button>
      </div>
    </div>

    <!-- 优惠券列表 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('coupon.code') || '优惠券码' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('coupon.name') || '名称' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('coupon.type') || '类型' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('coupon.value') || '面值' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('coupon.condition') || '使用条件' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('coupon.status') || '状态' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('coupon.period') || '有效期' }}</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">{{ $t('coupon.stats') || '领取/使用' }}</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">{{ $t('common.actions') || '操作' }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="c in list" :key="c.id" class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-3">
              <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded">{{ c.code }}</span>
            </td>
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800">{{ c.name }}</div>
              <div class="text-xs text-gray-400">{{ c.description }}</div>
            </td>
            <td class="px-4 py-3">
              <span :class="typeClass(c.type)" class="text-xs px-2 py-1 rounded-full">
                {{ typeLabel(c.type) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="text-orange-600 font-bold">
                {{ c.type === 'percentage' ? c.value + '%' : '¥' + c.value }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              <div v-if="c.min_order_amount > 0">满¥{{ c.min_order_amount }}</div>
              <div v-else class="text-gray-400">无门槛</div>
            </td>
            <td class="px-4 py-3">
              <span :class="statusClass(c.status)" class="text-xs px-2 py-1 rounded-full">
                {{ statusLabel(c.status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500">
              <div>{{ formatDate(c.start_time) }}</div>
              <div>~ {{ formatDate(c.end_time) }}</div>
            </td>
            <td class="px-4 py-3 text-center">
              <div class="text-sm">
                <span class="text-blue-600">{{ c.total_received || 0 }}</span>
                <span class="text-gray-400"> / </span>
                <span class="text-green-600">{{ c.total_used || 0 }}</span>
              </div>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2 justify-center">
                <button @click="openEditDialog(c)" class="text-primary hover:text-primary/80 text-sm">
                  {{ $t('common.edit') || '编辑' }}
                </button>
                <button @click="toggleStatus(c)" class="text-sm hover:opacity-70" :class="c.status === 'active' ? 'text-orange-600' : 'text-green-600'">
                  {{ c.status === 'active' ? ($t('coupon.pause') || '暂停') : ($t('coupon.enable') || '启用') }}
                </button>
                <button @click="deleteCoupon(c)" class="text-red-600 hover:text-red-700 text-sm">
                  {{ $t('common.delete') || '删除' }}
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="list.length === 0">
            <td colspan="9" class="px-4 py-8 text-center text-gray-400">
              {{ $t('common.noData') || '暂无数据' }}
            </td>
          </tr>
        </tbody>
      </table>
      
      <!-- 分页 -->
      <div class="px-4 py-3 border-t border-gray-100 flex justify-between items-center">
        <div class="text-sm text-gray-500">
          {{ $t('common.total') || '共' }} {{ total }} {{ $t('common.records') || '条' }}
        </div>
        <div class="flex gap-2">
          <button @click="page--" :disabled="page <= 1" 
            class="px-3 py-1 border rounded hover:bg-gray-50 disabled:opacity-50">
            {{ $t('common.prev') || '上一页' }}
          </button>
          <span class="px-3 py-1">{{ page }} / {{ totalPages }}</span>
          <button @click="page++" :disabled="page >= totalPages"
            class="px-3 py-1 border rounded hover:bg-gray-50 disabled:opacity-50">
            {{ $t('common.next') || '下一页' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 创建/编辑弹窗 -->
    <div v-if="dialogVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="dialogVisible = false">
      <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-lg font-bold">{{ isEdit ? ($t('coupon.editCoupon') || '编辑优惠券') : ($t('coupon.createCoupon') || '创建优惠券') }}</h2>
          <button @click="dialogVisible = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
          <div class="space-y-4">
            <!-- 基本信息 -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.code') || '优惠券码' }}</label>
                <input v-model="form.code" type="text" :disabled="isEdit"
                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="如: SUMMER2025" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.name') || '名称' }}</label>
                <input v-model="form.name" type="text"
                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="如: 夏季狂欢券" />
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.description') || '描述' }}</label>
              <input v-model="form.description" type="text"
                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.type') || '类型' }}</label>
                <select v-model="form.type" class="w-full px-3 py-2 border rounded-lg focus:outline-none">
                  <option value="fixed">{{ $t('coupon.fixed') || '立减' }}</option>
                  <option value="percentage">{{ $t('coupon.percentage') || '折扣' }}</option>
                  <option value="manjian">{{ $t('coupon.manjian') || '满减' }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.value') || '面值' }}</label>
                <input v-model.number="form.value" type="number"
                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" 
                  :placeholder="form.type === 'percentage' ? '如: 20(表示20%)' : '如: 50'" />
              </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.minOrder') || '最低订单金额' }}</label>
                <input v-model.number="form.min_order_amount" type="number"
                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="0表示无门槛" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.maxDiscount') || '最高优惠' }}</label>
                <input v-model.number="form.max_discount_amount" type="number"
                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="空表示不限" />
              </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.startTime') || '开始时间' }}</label>
                <input v-model="form.start_time" type="datetime-local"
                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.endTime') || '结束时间' }}</label>
                <input v-model="form.end_time" type="datetime-local"
                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" />
              </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.totalQty') || '发放总量' }}</label>
                <input v-model.number="form.total_quantity" type="number"
                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="空表示不限量" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.perUserLimit') || '每人限领' }}</label>
                <input v-model.number="form.per_user_limit" type="number"
                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="默认1" />
              </div>
            </div>
            
            <!-- 促销规则 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.rules') || '促销规则' }}</label>
              <div class="border rounded-lg p-4 space-y-3">
                <div class="flex items-center gap-4">
                  <select v-model="form.rule_type" class="px-3 py-2 border rounded-lg">
                    <option value="min_amount">{{ $t('coupon.minAmount') || '满额' }}</option>
                    <option value="min_quantity">{{ $t('coupon.minQty') || '满件' }}</option>
                    <option value="first_order">{{ $t('coupon.firstOrder') || '首单' }}</option>
                  </select>
                  <input v-model.number="form.rule_value" type="number" :placeholder="$t('coupon.threshold') || '阈值'"
                    class="flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" />
                  <span class="text-gray-500">{{ $t('coupon.reward') || '奖励' }}:</span>
                  <select v-model="form.reward_type" class="px-3 py-2 border rounded-lg">
                    <option value="discount">{{ $t('coupon.discount') || '优惠' }}</option>
                    <option value="gift">{{ $t('coupon.gift') || '赠品' }}</option>
                    <option value="free_shipping">{{ $t('coupon.freeShip') || '包邮' }}</option>
                  </select>
                </div>
              </div>
            </div>
            
            <!-- 适用范围 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('coupon.applicableScope') || '适用范围' }}</label>
              <select v-model="form.applicable_type" class="w-full px-3 py-2 border rounded-lg focus:outline-none">
                <option value="all">{{ $t('coupon.allProducts') || '全部商品' }}</option>
                <option value="categories">{{ $t('coupon.selectedCategories') || '指定分类' }}</option>
                <option value="products">{{ $t('coupon.selectedProducts') || '指定商品' }}</option>
                <option value="stores">{{ $t('coupon.selectedStores') || '指定门店' }}</option>
              </select>
            </div>
          </div>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
          <button @click="dialogVisible = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
            {{ $t('common.cancel') || '取消' }}
          </button>
          <button @click="submitForm" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
            {{ $t('common.save') || '保存' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { couponApi } from '@/api/coupon'

const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const dialogVisible = ref(false)
const isEdit = ref(false)

const filters = ref({
  keyword: '',
  status: '',
  type: ''
})

const form = ref({
  code: '',
  name: '',
  description: '',
  type: 'fixed',
  value: 0,
  min_order_amount: 0,
  max_discount_amount: null,
  start_time: '',
  end_time: '',
  total_quantity: null,
  per_user_limit: 1,
  applicable_type: 'all',
  rule_type: 'min_amount',
  rule_value: 0,
  reward_type: 'discount'
})

const stats = ref({ total: 0, active: 0, totalClaimed: 0, totalDiscount: 0 })

const totalPages = computed(() => Math.ceil(total.value / pageSize.value))

const typeLabel = (type) => {
  const map = { fixed: '立减', percentage: '折扣', manjian: '满减', buy_x_get_y: '买赠' }
  return map[type] || type
}

const typeClass = (type) => {
  const map = { fixed: 'bg-orange-100 text-orange-700', percentage: 'bg-blue-100 text-blue-700', manjian: 'bg-green-100 text-green-700' }
  return map[type] || 'bg-gray-100 text-gray-700'
}

const statusLabel = (status) => {
  const map = { active: '活动中', paused: '已暂停', expired: '已过期' }
  return map[status] || status
}

const statusClass = (status) => {
  const map = { active: 'bg-green-100 text-green-700', paused: 'bg-orange-100 text-orange-700', expired: 'bg-gray-100 text-gray-500' }
  return map[status] || 'bg-gray-100 text-gray-700'
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('zh-CN', { month: 'short', day: 'numeric' })
}

const loadCoupons = async () => {
  try {
    const res = await couponApi.list({ page: page.value, pageSize: pageSize.value, ...filters.value })
    if (res.code === 0) {
      list.value = res.data.list
      total.value = res.data.total
    }
  } catch (err) {
    console.error('Load coupons error:', err)
  }
}

const loadStats = async () => {
  try {
    const res = await couponApi.list({ pageSize: 1000 })
    if (res.code === 0) {
      const all = res.data.list
      stats.value = {
        total: all.length,
        active: all.filter(c => c.status === 'active').length,
        totalClaimed: all.reduce((sum, c) => sum + (c.total_received || 0), 0),
        totalDiscount: all.reduce((sum, c) => sum + (c.total_discount || 0), 0).toFixed(2)
      }
    }
  } catch (err) {
    console.error('Load stats error:', err)
  }
}

const openCreateDialog = () => {
  isEdit.value = false
  form.value = {
    code: '',
    name: '',
    description: '',
    type: 'fixed',
    value: 0,
    min_order_amount: 0,
    max_discount_amount: null,
    start_time: '',
    end_time: '',
    total_quantity: null,
    per_user_limit: 1,
    applicable_type: 'all',
    rule_type: 'min_amount',
    rule_value: 0,
    reward_type: 'discount'
  }
  dialogVisible.value = true
}

const openEditDialog = async (c) => {
  isEdit.value = true
  try {
    const res = await couponApi.detail(c.id)
    if (res.code === 0) {
      const data = res.data
      form.value = {
        ...data,
        rule_type: data.rules?.[0]?.rule_type || 'min_amount',
        rule_value: data.rules?.[0]?.rule_value || 0,
        reward_type: data.rules?.[0]?.reward_type || 'discount'
      }
      dialogVisible.value = true
    }
  } catch (err) {
    console.error('Load coupon detail error:', err)
  }
}

const submitForm = async () => {
  try {
    const rules = [{
      rule_type: form.value.rule_type,
      rule_value: form.value.rule_value,
      reward_type: form.value.reward_type,
      reward_value: form.value.value,
      sort_order: 1
    }]
    
    const data = {
      code: form.value.code,
      name: form.value.name,
      description: form.value.description,
      type: form.value.type,
      value: form.value.value,
      min_order_amount: form.value.min_order_amount,
      max_discount_amount: form.value.max_discount_amount,
      start_time: form.value.start_time,
      end_time: form.value.end_time,
      total_quantity: form.value.total_quantity,
      per_user_limit: form.value.per_user_limit,
      applicable_type: form.value.applicable_type,
      rules,
      applicable_items: []
    }
    
    const res = isEdit.value
      ? await couponApi.update(form.value.id, data)
      : await couponApi.create(data)
    
    if (res.code === 0) {
      dialogVisible.value = false
      loadCoupons()
      loadStats()
    }
  } catch (err) {
    console.error('Submit form error:', err)
  }
}

const toggleStatus = async (c) => {
  try {
    await couponApi.toggle(c.id)
    loadCoupons()
    loadStats()
  } catch (err) {
    console.error('Toggle status error:', err)
  }
}

const deleteCoupon = async (c) => {
  if (!confirm(`确定删除优惠券「${c.name}」吗？`)) return
  try {
    await couponApi.delete(c.id)
    loadCoupons()
    loadStats()
  } catch (err) {
    console.error('Delete coupon error:', err)
  }
}

onMounted(() => {
  loadCoupons()
  loadStats()
})
</script>