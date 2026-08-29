<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage, ElMessageBox } from 'element-plus'

const { t } = useI18n()

// ─── 桌码管理 ─────────────────────────────────────────────────────────────────
const tables = ref([])
const tableLoading = ref(false)

async function fetchTables() {
  tableLoading.value = true
  try {
    const { data } = await api.get('/referral/tables')
    tables.value = data
  } catch (e) {
    ElMessage.error('加载桌码失败')
  } finally {
    tableLoading.value = false
  }
}

async function addTable() {
  const { value } = await ElMessageBox.prompt('请输入桌号（如 A01）', '新增桌码', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
  })
  if (!value) return
  try {
    await api.post('/referral/tables', { table_no: value })
    ElMessage.success('添加成功')
    fetchTables()
  } catch (e) {
    ElMessage.error('添加失败')
  }
}

async function delTable(row) {
  await ElMessageBox.confirm(`确定删除桌码 ${row.table_no}？`, '删除确认')
  await api.delete(`/referral/tables/${row.id}`)
  ElMessage.success('删除成功')
  fetchTables()
}

// QR码下载（使用后端生成的二维码图片）
function downloadQR(token) {
  const qrUrl = `/api/referral/qr/${token}`
  const win = window.open(qrUrl, '_blank')
  if (!win) ElMessage.warning('请允许弹出窗口')
}

// ─── 奖励规则 ─────────────────────────────────────────────────────────────────
const rewards = ref([])
const rewardLoading = ref(false)

async function fetchRewards() {
  rewardLoading.value = true
  try {
    const { data } = await api.get('/referral/rewards')
    rewards.value = data
  } catch (e) {
    ElMessage.error('加载规则失败')
  } finally {
    rewardLoading.value = false
  }
}

async function addReward() {
  const { value } = await ElMessageBox.prompt(
    '请输入所需推荐人数（如 3）\n奖励将自动发放优惠券',
    '新增奖励规则',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      inputType: 'number',
    }
  )
  if (!value) return
  try {
    await api.post('/referral/rewards', { required_heads: parseInt(value) })
    ElMessage.success('添加成功')
    fetchRewards()
  } catch (e) {
    ElMessage.error('添加失败')
  }
}

async function delReward(row) {
  await ElMessageBox.confirm('确定删除该规则？', '删除确认')
  await api.delete(`/referral/rewards/${row.id}`)
  ElMessage.success('删除成功')
  fetchRewards()
}

// ─── 消费券 ───────────────────────────────────────────────────────────────────
const coupons = ref([])
const couponLoading = ref(false)

async function fetchCoupons() {
  couponLoading.value = true
  try {
    const { data } = await api.get('/referral/coupons')
    coupons.value = data
  } catch (e) {
    ElMessage.error('加载消费券失败')
  } finally {
    couponLoading.value = false
  }
}

async function addCoupon() {
  const { value: name } = await ElMessageBox.prompt('券名称（如 满100减20）', '新增消费券', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
  })
  if (!name) return

  const { value: money } = await ElMessageBox.prompt('满减金额（如 20）', '面额', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    inputType: 'number',
  })

  try {
    await api.post('/referral/coupons', {
      name,
      type: 'cash',
      money: parseFloat(money),
      min_price: 0,
    })
    ElMessage.success('添加成功')
    fetchCoupons()
  } catch (e) {
    ElMessage.error('添加失败')
  }
}

async function delCoupon(row) {
  await ElMessageBox.confirm('确定删除该消费券？', '删除确认')
  await api.delete(`/referral/coupons/${row.id}`)
  ElMessage.success('删除成功')
  fetchCoupons()
}

function couponTypeLabel(type) {
  return { cash: '满减券', discount: '折扣券', product: '实物券' }[type] || type
}

onMounted(() => {
  fetchTables()
  fetchRewards()
  fetchCoupons()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="推荐裂变管理" />

    <!-- 桌码管理 -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">桌码管理</h2>
        <button
          @click="addTable"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700"
        >
          + 新增桌码
        </button>
      </div>

      <el-table :data="tables" v-loading="tableLoading" stripe>
        <el-table-column prop="table_no" label="桌号" width="120" />
        <el-table-column prop="table_name" label="桌名" />
        <el-table-column label="二维码" width="100" align="center">
          <template #default="{ row }">
            <img
              :src="`/api/referral/qr/${row.qr_token}`"
              :alt="row.table_no"
              class="w-12 h-12 object-contain cursor-pointer hover:opacity-80"
              @click="downloadQR(row.qr_token)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="qr_token" label="Token" min-width="200" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <span
              class="px-2 py-1 text-xs rounded-full"
              :class="{
                'bg-green-100 text-green-700': row.status === 'active',
                'bg-gray-100 text-gray-500': row.status === 'inactive',
                'bg-orange-100 text-orange-700': row.status === 'occupied',
              }"
            >
              {{ row.status === 'active' ? '可用' : row.status === 'inactive' ? '停用' : '占用' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <button
              @click="downloadQR(row.qr_token)"
              class="text-blue-600 text-sm hover:underline mr-3"
            >
              下载码
            </button>
            <button
              @click="delTable(row)"
              class="text-red-600 text-sm hover:underline"
            >
              删除
            </button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 奖励规则 -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">奖励规则</h2>
        <button
          @click="addReward"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700"
        >
          + 新增规则
        </button>
      </div>

      <el-table :data="rewards" v-loading="rewardLoading" stripe>
        <el-table-column prop="product_name" label="关联商品" width="180">
          <template #default="{ row }">
            {{ row.product_name || '通用规则' }}
          </template>
        </el-table-column>
        <el-table-column prop="required_heads" label="所需人数" width="120" />
        <el-table-column prop="reward_type" label="奖励类型" width="120">
          <template #default="{ row }">
            {{ { coupon: '消费券', score: '积分', cash: '现金' }[row.reward_type] || row.reward_type }}
          </template>
        </el-table-column>
        <el-table-column prop="reward_desc" label="奖励说明" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <span
              class="px-2 py-1 text-xs rounded-full"
              :class="row.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
            >
              {{ row.status === 'active' ? '启用' : '停用' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <button @click="delReward(row)" class="text-red-600 text-sm hover:underline">
              删除
            </button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 消费券 -->
    <div class="bg-white rounded-xl shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">消费券</h2>
        <button
          @click="addCoupon"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700"
        >
          + 新增消费券
        </button>
      </div>

      <el-table :data="coupons" v-loading="couponLoading" stripe>
        <el-table-column prop="name" label="券名称" width="160" />
        <el-table-column prop="type" label="类型" width="100">
          <template #default="{ row }">
            {{ couponTypeLabel(row.type) }}
          </template>
        </el-table-column>
        <el-table-column prop="money" label="面额" width="100">
          <template #default="{ row }">
            {{ row.money ? `¥${row.money}` : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="min_price" label="门槛" width="100">
          <template #default="{ row }">
            {{ row.min_price > 0 ? `满¥${row.min_price}` : '无门槛' }}
          </template>
        </el-table-column>
        <el-table-column prop="stock" label="库存" width="100">
          <template #default="{ row }">
            {{ row.stock < 0 ? '不限' : row.stock }}
          </template>
        </el-table-column>
        <el-table-column prop="valid_days" label="有效期" width="100">
          <template #default="{ row }">
            {{ row.valid_days }}天
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <span
              class="px-2 py-1 text-xs rounded-full"
              :class="row.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
            >
              {{ row.status === 'active' ? '启用' : '停用' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <button @click="delCoupon(row)" class="text-red-600 text-sm hover:underline">
              删除
            </button>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>