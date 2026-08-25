<script setup>
import { ref, onMounted } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const stats = ref({
  express_count: 0,
  template_count: 0,
  channel_count: 0,
})
const recentChannels = ref([])
const loading = ref(false)

async function fetchDashboard() {
  loading.value = true
  try {
    const [expressRes, templateRes, channelRes] = await Promise.all([
      api.get('/logistics/express-companies', { params: { size: 1 } }),
      api.get('/logistics/freight-templates', { params: { size: 1 } }),
      api.get('/logistics/channel-logistics', { params: { size: 5 } }),
    ])
    if (expressRes.code === 0) stats.value.express_count = expressRes.data.total ?? 0
    if (templateRes.code === 0) stats.value.template_count = templateRes.data.total ?? 0
    if (channelRes.code === 0) {
      stats.value.channel_count = channelRes.data.total ?? 0
      recentChannels.value = channelRes.data.list ?? []
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchDashboard)
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="物流管理" subtitle="快递公司 / 运费模板 / 渠道物流" />

    <!-- Stats cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm text-gray-500">快递公司</div>
            <div class="text-2xl font-bold mt-1">{{ stats.express_count }}</div>
          </div>
          <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-blue-500">local_shipping</span>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm text-gray-500">运费模板</div>
            <div class="text-2xl font-bold mt-1">{{ stats.template_count }}</div>
          </div>
          <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-green-500">receipt_long</span>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm text-gray-500">渠道物流</div>
            <div class="text-2xl font-bold mt-1">{{ stats.channel_count }}</div>
          </div>
          <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-purple-500">route</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick nav -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <router-link to="/logistics/express" class="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4 hover:shadow-md transition-shadow">
        <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-blue-500">local_shipping</span>
        </div>
        <div>
          <div class="font-medium text-gray-800">快递公司管理</div>
          <div class="text-sm text-gray-400 mt-0.5">管理快递公司信息</div>
        </div>
      </router-link>
      <router-link to="/logistics/templates" class="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4 hover:shadow-md transition-shadow">
        <div class="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-green-500">receipt_long</span>
        </div>
        <div>
          <div class="font-medium text-gray-800">运费模板管理</div>
          <div class="text-sm text-gray-400 mt-0.5">配置按重量/按件计费规则</div>
        </div>
      </router-link>
      <router-link to="/logistics/channels" class="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4 hover:shadow-md transition-shadow">
        <div class="w-12 h-12 rounded-lg bg-purple-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-purple-500">route</span>
        </div>
        <div>
          <div class="font-medium text-gray-800">渠道物流管理</div>
          <div class="text-sm text-gray-400 mt-0.5">管理物流发货渠道</div>
        </div>
      </router-link>
    </div>

    <!-- Recent channels -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <div class="text-sm font-medium text-gray-700">最近渠道物流</div>
      </div>
      <el-table v-loading="loading" :data="recentChannels" stripe empty-text="暂无数据">
        <el-table-column label="渠道名称" prop="name" min-width="140" />
        <el-table-column label="快递公司" prop="express_company_name" min-width="120">
          <template #default="{ row }">{{ row.express_company_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="row.status === 'enabled' ? 'success' : 'info'" size="small">
              {{ row.status === 'enabled' ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="参考时效" width="120">
          <template #default="{ row }">
            <span v-if="row.min_days || row.max_days">{{ row.min_days ||0 }}-{{ row.max_days || 0 }}天</span>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
       <el-table-column label="首重费用" width="100" align="right">
          <template #default="{ row }">
            <span class="text-blue-600">{{ row.first_weight_fee != null ? `S$ ${parseFloat(row.first_weight_fee).toFixed(2)}` : '-' }}</span>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>