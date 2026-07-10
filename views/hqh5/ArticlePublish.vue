<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部 -->
    <div class="bg-white p-4 sticky top-0 z-10 shadow-sm flex items-center justify-between">
      <button @click="router.back()" class="text-gray-600">‹ 返回</button>
      <h1 class="text-lg font-bold text-gray-800">文章发布</h1>
      <button @click="showCreate = true" class="text-purple-600 text-sm font-medium">+ 新建</button>
    </div>

    <!-- Tab -->
    <div class="bg-white px-4 pb-3 flex gap-2 sticky top-16 z-10 border-b">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        @click="activeTab = tab.value; loadList()"
        :class="['px-3 py-1.5 rounded-full text-sm transition',
                 activeTab === tab.value ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600']"
      >{{ tab.label }} ({{ counts[tab.value] || 0 }})</button>
    </div>

    <!-- 列表 -->
    <div class="p-4 space-y-3">
      <div v-if="loading" class="text-center py-8 text-gray-400">加载中...</div>
      <div v-else-if="list.length === 0" class="text-center py-12 text-gray-400 bg-white rounded-xl">
        暂无{{ tabs.find(t => t.value === activeTab)?.label }}文章
      </div>
      <div
        v-for="art in list"
        :key="art.id"
        class="bg-white rounded-xl shadow-sm overflow-hidden"
      >
        <div class="flex gap-3 p-3">
          <img v-if="art.cover_image" :src="art.cover_image" class="w-20 h-20 rounded-lg object-cover flex-shrink-0" />
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs px-2 py-0.5 rounded" :class="art.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                {{ art.status === 'published' ? '已发布' : '草稿' }}
              </span>
              <span class="text-xs text-purple-600">{{ art.category }}</span>
            </div>
            <h3 class="font-medium text-gray-800 line-clamp-1">{{ art.title }}</h3>
            <p class="text-xs text-gray-500 line-clamp-2 mt-1">{{ art.summary }}</p>
            <div class="flex items-center justify-between text-xs text-gray-400 mt-2">
              <span>{{ art.author }} · {{ formatTime(art.published_at || art.created_at) }}</span>
              <span>👁 {{ art.view_count || 0 }}</span>
            </div>
          </div>
        </div>
        <div v-if="art.status === 'draft'" class="bg-gray-50 px-3 py-2 flex justify-end gap-2">
          <button @click="publishArticle(art)" class="text-xs bg-purple-600 text-white px-3 py-1 rounded">发布</button>
        </div>
      </div>
    </div>

    <!-- 新建文章 -->
    <el-dialog v-model="showCreate" title="新建文章" width="95%" top="5vh">
      <el-form :model="newArt" label-width="80px">
        <el-form-item label="标题">
          <el-input v-model="newArt.title" placeholder="文章标题" />
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="newArt.category" placeholder="选择分类">
            <el-option v-for="c in categories" :key="c" :label="c" :value="c" />
          </el-select>
        </el-form-item>
        <el-form-item label="封面图">
          <el-input v-model="newArt.cover_image" placeholder="封面图片 URL" />
        </el-form-item>
        <el-form-item label="摘要">
          <el-input v-model="newArt.summary" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="正文">
          <el-input v-model="newArt.content" type="textarea" :rows="6" placeholder="支持 HTML" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">取消</el-button>
        <el-button @click="submitArticle('draft')">保存草稿</el-button>
        <el-button type="primary" @click="submitArticle('published')">发布</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'

const router = useRouter()
const tabs = [
  { label: '全部', value: 'all' },
  { label: '已发布', value: 'published' },
  { label: '草稿', value: 'draft' }
]
const categories = ['政策', '活动', '服务', '沙龙', '推荐', '报告', '通知', '生活']

const activeTab = ref('all')
const list = ref([])
const counts = ref({ all: 0, published: 0, draft: 0 })
const loading = ref(false)
const showCreate = ref(false)
const newArt = ref({ title: '', category: '', cover_image: '', summary: '', content: '', status: 'draft' })

const formatTime = (d) => d ? new Date(d).toLocaleString('zh-CN', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''

const loadList = async () => {
  loading.value = true
  try {
    // 拉全部已发布
    const res1 = await axios.get('/api/hqh5/articles?page=1&pageSize=50')
    const allPublished = res1.data?.data?.list || []
    counts.value.all = allPublished.length
    counts.value.published = allPublished.length
    list.value = activeTab.value === 'draft' ? [] : allPublished
    // 这里简化为：草稿空（生产可加草稿表）
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const submitArticle = async (status) => {
  if (!newArt.value.title || !newArt.value.content) {
    ElMessage.warning('标题和内容必填')
    return
  }
  try {
    const res = await axios.post('/api/hqh5/articles/create', {
      ...newArt.value,
      author: 'admin',
      status
    })
    if (res.data?.code === 0) {
      ElMessage.success(status === 'published' ? '发布成功' : '已存草稿')
      showCreate.value = false
      newArt.value = { title: '', category: '', cover_image: '', summary: '', content: '', status: 'draft' }
      loadList()
    }
  } catch (e) {
    ElMessage.error('提交失败')
  }
}

const publishArticle = async (art) => {
  try {
    // 简化处理：再次创建为 published
    const res = await axios.post('/api/hqh5/articles/create', {
      ...art,
      id: undefined,
      status: 'published'
    })
    if (res.data?.code === 0) {
      ElMessage.success('已发布')
      loadList()
    }
  } catch (e) {
    ElMessage.error('发布失败')
  }
}

onMounted(loadList)
</script>