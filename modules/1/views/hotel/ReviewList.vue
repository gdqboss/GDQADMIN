<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const { t } = useI18n()

const loading = ref(false)
const reviews = ref([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)

const filterKeyword = ref('')
const filterRating = ref('')
const filterDateRange = ref([])

const ratingOptions = [
  { label: '全部', value: '' },
  { label: '5星', value: 5 },
  { label: '4星', value: 4 },
  { label: '3星', value: 3 },
  { label: '2星', value: 2 },
  { label: '1星', value: 1 },
]

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}

async function fetchReviews() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      size: pageSize.value,
    }
    if (filterKeyword.value) params.keyword = filterKeyword.value
    if (filterRating.value) params.rating = filterRating.value
    if (filterDateRange.value?.length === 2) {
      params.start_date = filterDateRange.value[0]
      params.end_date = filterDateRange.value[1]
    }
    const res = await api.get('/hotel/reviews', { params })
    if (res.code === 0) {
      reviews.value = res.data.list || res.data
      total.value = res.data.total ?? reviews.value.length
    }
  } catch (e) {
    ElMessage.error(e.message || '获取评价列表失败')
  } finally {
    loading.value = false
  }
}

async function replyReview(row) {
  const reply = prompt('请输入回复内容：', row.reply || '')
  if (reply === null) return
  try {
    const res = await api.post(`/hotel/reviews/${row.id}/reply`, { reply })
    if (res.code === 0) {
      ElMessage.success('回复成功')
      fetchReviews()
    } else {
      ElMessage.error(res.message || '回复失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '回复失败')
  }
}

function renderStars(rating) {
  return '★'.repeat(rating) + '☆'.repeat(5 - rating)
}

watch([filterKeyword, filterRating, filterDateRange], () => {
  currentPage.value = 1
  fetchReviews()
})
watch(currentPage, fetchReviews)
onMounted(fetchReviews)
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="评价管理" subtitle="客人评价与回复" />

    <!-- Filter bar -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="flex flex-wrap gap-4 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">关键词搜索</label>
          <el-input
            v-model="filterKeyword"
            placeholder="订单号 / 客人姓名"
            clearable
            class="!w-64"
          />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">评分筛选</label>
          <el-select v-model="filterRating" class="!w-32">
            <el-option
              v-for="opt in ratingOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">日期范围</label>
          <el-date-picker
            v-model="filterDateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            class="!w-72"
          />
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-5 gap-4 mb-6">
      <div v-for="opt in ratingOptions.slice(1)" :key="opt.value" class="bg-white rounded-xl shadow-sm p-4 text-center">
        <div class="text-2xl font-bold text-yellow-500">{{ opt.value }}星</div>
        <div class="text-xs text-gray-400 mt-1">
          {{ reviews.filter(r => r.rating === opt.value).length }} 条
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table :data="reviews" stripe v-loading="loading" empty-text="暂无评价数据">
        <el-table-column label="订单号" prop="order_no" min-width="140" />
        <el-table-column label="客人" prop="guest_name" min-width="100" />
        <el-table-column label="房型" prop="room_type_name" min-width="100" />
        <el-table-column label="评分" width="100" align="center">
          <template #default="{ row }">
            <span class="text-yellow-500 text-lg">{{ renderStars(row.rating || 0) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="评价内容" min-width="200">
          <template #default="{ row }">
            <div class="text-sm text-gray-700">{{ row.content || '-' }}</div>
            <div v-if="row.reply" class="text-sm text-gray-400 mt-1 border-t pt-1">
             <span class="text-blue-500">回复：</span>{{ row.reply }}
            </div>
          </template>
        </el-table-column>
        <el-table-column label="评价时间" width="160">
          <template #default="{ row }">
            <span class="text-sm text-gray-500">{{ formatDate(row.created_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="replyReview(row)">
              {{ row.reply ? '修改回复' : '回复' }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- Pagination -->
      <div class="flex justify-end p-4 border-t">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next"
          background
        />
      </div>
    </div>
  </div>
</template>