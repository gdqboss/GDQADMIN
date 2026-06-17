<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const { t } = useI18n()
const router = useRouter()

// ─── Tabs ────────────────────────────────────────────────────────────────────────
const tabs = [
  { key: 'all', label: '全部' },
  { key: 'published', label: '已发布' },
  { key: 'draft', label: '草稿' },
  { key: 'archived', label: '已归档' },
]
const activeTab = ref('all')

// ─── Search ─────────────────────────────────────────────────────────────────────
const searchKeyword = ref('')
const dateRange = ref([])

// ─── Pagination ────────────────────────────────────────────────────────────────
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)

// ─── Table data ────────────────────────────────────────────────────────────────
const articles = ref([])
const loading = ref(false)

const statusTypeMap = {
  published: 'success',
  draft: 'warning',
  archived: 'info',
}

const statusLabelMap = {
  published: '已发布',
  draft: '草稿',
  archived: '已归档',
}

function getStatusType(status) {
  return statusTypeMap[status] || 'info'
}

function getStatusLabel(status) {
  return statusLabelMap[status] || status
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}

// ─── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchArticles() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      size: pageSize.value,
    }
    if (activeTab.value !== 'all') params.status = activeTab.value
    if (searchKeyword.value) params.keyword = searchKeyword.value
    if (dateRange.value?.length === 2) {
      params.date_start = dateRange.value[0]
      params.date_end = dateRange.value[1]
    }
    const res = await api.get('/article', { params })
    if (res.code === 0) {
      articles.value = res.data.list || res.data
      total.value = res.data.total ?? articles.value.length
    }
  } catch (e) {
    ElMessage.error(e.message || '获取文章列表失败')
  } finally {
    loading.value = false
  }
}

watch([activeTab, searchKeyword, dateRange], () => {
  currentPage.value = 1
  fetchArticles()
}, { deep: true })
watch(currentPage, fetchArticles)

onMounted(() => {
  fetchArticles()
})

// ─── Actions ───────────────────────────────────────────────────────────────────
function goDetail(id) {
  router.push(`/articles/${id}`)
}

function goNewArticle() {
  router.push('/articles/new')
}

async function publishArticle(row) {
  if (!confirm(`确定发布文章「${row.title}」？`)) return
  try {
    const res = await api.put(`/article/${row.id}/status`, { status: 'published' })
    if (res.code === 0) {
      ElMessage.success('文章已发布')
      fetchArticles()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function archiveArticle(row) {
  if (!confirm(`确定归档文章「${row.title}」？`)) return
  try {
    const res = await api.put(`/article/${row.id}/status`, { status: 'archived' })
    if (res.code === 0) {
      ElMessage.success('文章已归档')
      fetchArticles()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function deleteArticle(row) {
  if (!confirm(`确定删除文章「${row.title}」？此操作不可恢复！`)) return
  try {
    const res = await api.delete(`/article/${row.id}`)
    if (res.code === 0) {
      ElMessage.success('文章已删除')
      fetchArticles()
    } else {
      ElMessage.error(res.message || '删除失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '删除失败')
  }
}

function canPublish(status) { return status === 'draft' }
function canArchive(status) { return status === 'published' }
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="文章管理" subtitle="内容发布与编辑" />

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
      <div class="flex overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="px-6 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
          :class="activeTab === tab.key
            ? 'border-blue-600 text-blue-600 bg-blue-50'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Search bar -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="flex flex-wrap gap-4 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">关键词搜索</label>
          <el-input
            v-model="searchKeyword"
            placeholder="标题 / 摘要 / 作者"
            clearable
            class="!w-64"
          />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">日期范围</label>
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            class="!w-72"
          />
        </div>
        <div class="ml-auto">
          <el-button type="primary" @click="goNewArticle">新增文章</el-button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table
        v-loading="loading"
        :data="articles"
        stripe
        class="w-full"
        empty-text="暂无文章数据"
      >
        <el-table-column label="标题" prop="title" min-width="200" />
        <el-table-column label="封面图" width="100">
          <template #default="{ row }">
            <img
              v-if="row.cover_image"
              :src="row.cover_image"
              class="w-16 h-16 object-cover rounded"
            />
            <span v-else class="text-gray-300 text-xs">无</span>
          </template>
        </el-table-column>
        <el-table-column label="作者" prop="author" width="120" />
        <el-table-column label="分类" prop="category" width="120" />
        <el-table-column label="标签" min-width="120">
          <template #default="{ row }">
            <span class="text-sm text-gray-500">{{ row.tags || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="浏览/点赞" width="110">
          <template #default="{ row }">
            <div class="text-sm text-center">
              <div>{{ row.view_count || 0 }} / {{ row.like_count || 0 }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">
            <span class="text-sm text-gray-500">{{ formatDate(row.created_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <el-button size="small" link type="primary" @click="goDetail(row.id)">查看详情</el-button>
              <el-button v-if="canPublish(row.status)" size="small" link type="success" @click="publishArticle(row)">发布</el-button>
              <el-button v-if="canArchive(row.status)" size="small" link type="info" @click="archiveArticle(row)">归档</el-button>
              <el-button size="small" link type="danger" @click="deleteArticle(row)">删除</el-button>
            </div>
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