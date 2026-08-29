<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

const isEdit = ref(false)
const articleId = ref(null)

const form = ref({
  title: '',
  content: '',
  summary: '',
  cover_image: '',
  author: '',
  category: '',
  tags: '',
  status: 'draft',
})
const loading = ref(false)
const saving = ref(false)

onMounted(() => {
  if (route.params.id && route.params.id !== 'new') {
    articleId.value = route.params.id
    isEdit.value = true
    fetchArticle()
  }
})

async function fetchArticle() {
  loading.value = true
  try {
    const res = await api.get(`/article/${articleId.value}`)
    if (res.code === 0) {
      form.value = {
        title: res.data.title || '',
        content: res.data.content || '',
        summary: res.data.summary || '',
        cover_image: res.data.cover_image || '',
        author: res.data.author || '',
        category: res.data.category || '',
        tags: res.data.tags || '',
        status: res.data.status || 'draft',
      }
    }
  } catch (e) {
    ElMessage.error('获取文章详情失败')
    router.back()
  } finally {
    loading.value = false
  }
}

async function saveArticle(isPublish = false) {
  if (!form.value.title) {
    ElMessage.warning('标题必填')
    return
  }
  saving.value = true
  try {
    const payload = { ...form.value }
    if (isPublish) payload.status = 'published'

    let res
    if (isEdit.value) {
      res = await api.put(`/article/${articleId.value}`, payload)
    } else {
      res = await api.post('/article', payload)
    }
    if (res.code === 0) {
      ElMessage.success(isEdit.value ? '保存成功' : '创建成功')
      router.push('/articles')
    } else {
      ElMessage.error(res.message || '保存失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    saving.value = false
  }
}

async function publishArticle() {
  if (!form.value.title) {
    ElMessage.warning('标题必填')
    return
  }
  saving.value = true
  try {
    const payload = { ...form.value, status: 'published' }
    let res
    if (isEdit.value) {
      res = await api.put(`/article/${articleId.value}`, payload)
    } else {
      res = await api.post('/article', payload)
    }
    if (res.code === 0) {
      ElMessage.success('发布成功')
      router.push('/articles')
    } else {
      ElMessage.error(res.message || '发布失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '发布失败')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader :title="isEdit ? '编辑文章' : '新增文章'" :subtitle="isEdit ? `ID: ${articleId}` : '创建新文章'" />

    <div class="bg-white rounded-xl shadow-sm p-6">
      <div v-loading="loading" class="max-w-3xl mx-auto">
        <!-- Title -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">标题 <span class="text-red-500">*</span></label>
          <el-input v-model="form.title" placeholder="请输入文章标题" maxlength="255" show-word-limit />
        </div>

        <!-- Summary -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">摘要</label>
          <el-input v-model="form.summary" type="textarea" placeholder="请输入文章摘要" :rows="2" maxlength="500" show-word-limit />
        </div>

        <!-- Cover Image -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">封面图URL</label>
          <el-input v-model="form.cover_image" placeholder="请输入封面图URL" />
          <div v-if="form.cover_image" class="mt-2">
            <img :src="form.cover_image" class="w-32 h-32 object-cover rounded border" />
          </div>
        </div>

        <!-- Author + Category -->
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">作者</label>
            <el-input v-model="form.author" placeholder="作者名称" maxlength="100" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">分类</label>
            <el-input v-model="form.category" placeholder="文章分类" maxlength="100" />
          </div>
        </div>

        <!-- Tags -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">标签</label>
          <el-input v-model="form.tags" placeholder="多个标签用逗号分隔" maxlength="500" />
        </div>

        <!-- Content -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">内容</label>
          <el-input
            v-model="form.content"
            type="textarea"
            placeholder="请输入文章内容"
            :rows="12"
          />
        </div>

        <!-- Status -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">状态</label>
          <el-select v-model="form.status" class="!w-40">
            <el-option label="草稿" value="draft" />
            <el-option label="已发布" value="published" />
            <el-option label="已归档" value="archived" />
          </el-select>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 justify-end border-t pt-4">
          <el-button @click="router.back()">取消</el-button>
          <el-button :loading="saving" @click="saveArticle(false)">保存草稿</el-button>
          <el-button type="success" :loading="saving" @click="publishArticle">保存并发布</el-button>
        </div>
      </div>
    </div>
  </div>
</template>