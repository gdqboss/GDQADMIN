<template>
  <div class="p-6">
    <PageHeader title="优惠券管理" subtitle="管理员优惠券发放与管理" />

    <!-- 操作栏 -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap gap-4 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">关键词</label>
        <el-input v-model="keyword" placeholder="优惠券名称" clearable class="!w-48" @clear="resetAndFetch" @keyup.enter="resetAndFetch" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">状态</label>
        <el-select v-model="filterStatus" placeholder="全部" clearable class="!w-32" @change="resetAndFetch">
          <el-option label="全部" value="" />
          <el-option label="有效" value="active" />
          <el-option label="无效" value="inactive" />
          <el-option label="已过期" value="expired" />
        </el-select>
      </div>
      <button @click="resetAndFetch" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">搜索</button>
      <button @click="showCreateDialog = true" class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm hover:bg-amber-600 ml-auto">
        + 新建优惠券
      </button>
    </div>

    <!-- 表格 -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table :data="coupons" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="优惠券信息" min-width="200">
          <template #default="{ row }">
            <p class="text-sm font-medium text-gray-800">{{ row.name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">
              <span v-if="row.type === 'cash'" class="text-amber-600">满减券 ¥{{ row.money }}</span>
              <span v-else-if="row.type === 'discount'" class="text-amber-600">{{ (row.discount_rate * 10).toFixed(1) }}折</span>
              <span v-else class="text-amber-600">免运费</span>
              <span v-if="row.min_amount > 0" class="ml-1">（满¥{{ row.min_amount }}）</span>
            </p>
          </template>
        </el-table-column>
        <el-table-column label="发放/剩余" width="110">
          <template #default="{ row }">
            <span class="text-sm">{{ row.remain_count }} / {{ row.total_count }}</span>
          </template>
        </el-table-column>
        <el-table-column label="限领" width="70">
          <template #default="{ row }">
            <span class="text-sm text-gray-600">{{ row.per_limit }}张/人</span>
          </template>
        </el-table-column>
        <el-table-column label="有效期" width="170">
          <template #default="{ row }">
            <span class="text-xs text-gray-500">
              {{ row.start_time ? formatDate(row.start_time) : '领取生效' }}
              ~ {{ row.end_time ? formatDate(row.end_time) : '永久' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <button @click="openReceiveDialog(row)" class="text-blue-600 text-sm hover:underline mr-3">发放</button>
            <button @click="editCoupon(row)" class="text-blue-600 text-sm hover:underline mr-3">编辑</button>
            <button @click="deleteCoupon(row)" class="text-red-500 text-sm hover:underline">删除</button>
          </template>
        </el-table-column>
      </el-table>

      <div class="p-4 flex justify-end">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="fetchCoupons"
        />
      </div>
    </div>

    <!-- 新建/编辑弹窗 -->
    <el-dialog v-model="showCreateDialog" :title="editingId ? '编辑优惠券' : '新建优惠券'" width="650px" :close-on-click-modal="false">
      <el-form :model="form" label-width="100px" class="grid grid-cols-2 gap-x-4">
        <el-form-item label="名称" required class="col-span-2">
          <el-input v-model="form.name" placeholder="如：新人满减券" />
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="form.type" class="!w-full">
            <el-option label="现金券" value="cash" />
            <el-option label="折扣券" value="discount" />
            <el-option label="运费券" value="shipping" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="form.status" class="!w-full">
            <el-option label="有效" value="active" />
            <el-option label="无效" value="inactive" />
          </el-select>
        </el-form-item>
        <el-form-item label="优惠金额" v-if="form.type === 'cash'" required>
          <el-input-number v-model="form.money" :min="0.01" :precision="2" />
        </el-form-item>
        <el-form-item label="折扣率" v-if="form.type === 'discount'" required>
          <el-input-number v-model="form.discount_rate" :min="0.01" :max="0.99" :precision="2" placeholder="如 0.90 表示9折" />
        </el-form-item>
        <el-form-item label="满减门槛" v-if="form.type === 'cash'">
          <el-input-number v-model="form.min_amount" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="发放数量" required>
          <el-input-number v-model="form.total_count" :min="1" />
        </el-form-item>
        <el-form-item label="每人限领">
          <el-input-number v-model="form.per_limit" :min="1" />
        </el-form-item>
        <el-form-item label="有效开始">
          <el-date-picker v-model="form.start_time" type="datetime" placeholder="留空表示立即生效" value-format="YYYY-MM-DD HH:mm:ss" class="!w-full" />
        </el-form-item>
        <el-form-item label="有效截止">
          <el-date-picker v-model="form.end_time" type="datetime" placeholder="留空表示永久有效" value-format="YYYY-MM-DD HH:mm:ss" class="!w-full" />
        </el-form-item>
        <el-form-item label="领取后有效天数">
          <el-input-number v-model="form.valid_days" :min="0" placeholder="0表示不限制" />
        </el-form-item>
        <el-form-item label="适用全部商品">
          <el-switch v-model="form.apply_all" :active-value="true" :inactive-value="false" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="submitForm" :loading="submitting">{{ editingId ? '保存' : '创建' }}</el-button>
      </template>
    </el-dialog>

    <!-- 发放优惠券弹窗 -->
    <el-dialog v-model="showReceiveDialog" title="发放优惠券" width="500px">
      <p class="text-sm text-gray-600 mb-3">优惠券：<span class="font-bold">{{ selectedCoupon?.name }}</span>（剩余 {{ selectedCoupon?.remain_count }} 张）</p>
      <el-form-item label="用户ID">
        <el-input v-model="receiveUserIds" type="textarea" :rows="4" placeholder="填写用户ID，多个用换行分隔，如&#10;1&#10;2&#10;3" />
      </el-form-item>
      <p class="text-xs text-gray-400">将会给每个用户发放一张该优惠券，并从剩余数量中扣除</p>
      <template #footer>
        <el-button @click="showReceiveDialog = false">取消</el-button>
        <el-button type="primary" @click="submitReward" :loading="rewarding">确认发放</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage, ElMessageBox } from 'element-plus'

const coupons = ref([])
const loading = ref(false)
const showCreateDialog = ref(false)
const showReceiveDialog = ref(false) // boolean for dialog visibility
const selectedCoupon = ref(null)    // coupon object for the function
const receiveUserIds = ref('')
const submitting = ref(false)
const rewarding = ref(false)
const editingId = ref(null)
const keyword = ref('')
const filterStatus = ref('')
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)

const defaultForm = {
  name: '', type: 'cash', money: 10, min_amount: 0, discount_rate: 0.9,
  total_count: 100, per_limit: 1, start_time: null, end_time: null,
  valid_days: 30, apply_all: true, status: 'active'
}
const form = ref({ ...defaultForm })

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false }).slice(0, 16)
}
function statusTagType(s) {
  return { active: 'success', inactive: 'info', expired: 'warning' }[s] || 'info'
}
function statusLabel(s) {
  return { active: '有效', inactive: '无效', expired: '已过期' }[s] || s
}

async function fetchCoupons() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value }
    if (filterStatus.value) params.status = filterStatus.value
    if (keyword.value) params.keyword = keyword.value
    const res = await api.get('/coupon/admin', { params })
    if (res.code === 0) {
      coupons.value = res.data.list || []
      total.value = res.data.total
    }
  } finally { loading.value = false }
}

function editCoupon(row) {
  editingId.value = row.id
  form.value = {
    name: row.name, type: row.type, money: row.money, min_amount: row.min_amount,
    discount_rate: row.discount_rate, total_count: row.total_count, per_limit: row.per_limit,
    start_time: row.start_time, end_time: row.end_time, valid_days: row.valid_days,
    apply_all: !!row.apply_all, status: row.status
  }
  showCreateDialog.value = true
}

function resetForm() {
  editingId.value = null
  form.value = { ...defaultForm }
}

async function submitForm() {
  if (!form.value.name) return ElMessage.warning('名称必填')
  if (!form.value.total_count) return ElMessage.warning('发放数量必填')
  submitting.value = true
  try {
    const payload = { ...form.value }
    let res
    if (editingId.value) {
      res = await api.put(`/coupon/admin/${editingId.value}`, payload)
    } else {
      res = await api.post('/coupon/admin', payload)
    }
    if (res.code === 0) {
      ElMessage.success(editingId.value ? '更新成功' : '创建成功')
      showCreateDialog.value = false
      resetForm()
      fetchCoupons()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  } finally { submitting.value = false }
}

function openReceiveDialog(coupon) {
  selectedCoupon.value = coupon
  receiveUserIds.value = ''
  showReceiveDialog.value = true
}

async function submitReward() {
  if (!receiveUserIds.value.trim()) return ElMessage.warning('请填写用户ID')
  const ids = receiveUserIds.value.split(/\n/).map(s => parseInt(s.trim())).filter(n => !isNaN(n))
  if (!ids.length) return ElMessage.warning('未识别到有效用户ID')
  rewarding.value = true
  try {
    const res = await api.post(`/coupon/admin/${selectedCoupon.value.id}/reward`, { user_ids: ids })
    if (res.code === 0) {
      ElMessage.success(res.message || '发放成功')
      showReceiveDialog.value = false
      fetchCoupons()
    } else {
      ElMessage.error(res.message || '发放失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '发放失败')
  } finally { rewarding.value = false }
}

async function deleteCoupon(row) {
  try {
    await ElMessageBox.confirm(`确定删除优惠券「${row.name}」？`, '确认删除')
    const res = await api.delete(`/coupon/admin/${row.id}`)
    if (res.code === 0) {
      ElMessage.success(res.message || '删除成功')
      fetchCoupons()
    }
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '删除失败')
  }
}

function resetAndFetch() {
  currentPage.value = 1
  fetchCoupons()
}

onMounted(() => {
  fetchCoupons()
})
</script>
