<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">学术动态</h1>
        <p class="text-sm text-gray-500 mt-1">研究进展 / 学术报告 / 期刊文章</p>
      </div>
      <button @click="openEdit()" class="px-4 py-2 bg-primary text-white rounded-lg">+ 发布动态</button>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3">
        <input v-model="filter.keyword" placeholder="搜索标题/作者/期刊" class="px-3 py-2 border rounded-lg flex-1" @keyup.enter="search" />
        <select v-model="filter.status" class="px-3 py-2 border rounded-lg">
          <option value="">全部状态</option>
          <option value="published">已发布</option>
          <option value="draft">草稿</option>
          <option value="archived">已归档</option>
        </select>
        <select v-model="filter.category" class="px-3 py-2 border rounded-lg">
          <option value="">全部分类</option>
          <option value="general">综合</option>
          <option value="research">研究</option>
          <option value="conference">会议</option>
          <option value="award">获奖</option>
        </select>
        <button @click="search" class="px-4 py-2 bg-primary text-white rounded-lg">搜索</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">标题</th>
            <th class="px-4 py-3 text-left">作者</th>
            <th class="px-4 py-3 text-left">期刊/会议</th>
            <th class="px-4 py-3 text-left">DOI</th>
            <th class="px-4 py-3 text-left">状态</th>
            <th class="px-4 py-3 text-left">浏览</th>
            <th class="px-4 py-3 text-left">发布时间</th>
            <th class="px-4 py-3 text-left">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in list" :key="a.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800">{{ a.title }}</div>
              <div v-if="a.summary" class="text-xs text-gray-400 mt-1 line-clamp-1">{{ a.summary }}</div>
            </td>
            <td class="px-4 py-3 text-sm">{{ a.author_name || '-' }}</td>
            <td class="px-4 py-3 text-sm">{{ a.journal_name || '-' }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ a.doi || '-' }}</td>
            <td class="px-4 py-3">
              <span :class="['px-2 py-0.5 rounded text-xs', statusClass(a.status)]">{{ statusLabel(a.status) }}</span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ a.view_count || 0 }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ formatTime(a.published_at || a.created_at) }}</td>
            <td class="px-4 py-3">
              <button @click="openEdit(a)" class="text-primary text-sm hover:underline mr-2">编辑</button>
              <button @click="del(a.id)" class="text-red-500 text-sm hover:underline">删除</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">暂无学术动态</div>

      <div v-if="total > pageSize" class="p-4 flex justify-end">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          layout="prev, pager, next"
          @current-change="load"
        />
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑学术动态' : '发布学术动态'" width="800px" :close-on-click-modal="false">
      <el-form :model="form" label-width="100px">
        <el-form-item label="标题" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="分类">
          <el-select v-model="form.category" class="w-full">
            <el-option label="综合" value="general" />
            <el-option label="研究" value="research" />
            <el-option label="会议" value="conference" />
            <el-option label="获奖" value="award" />
          </el-select>
        </el-form-item>
        <el-form-item label="作者"><el-input v-model="form.author_name" placeholder="第一作者 / 通讯作者" /></el-form-item>
        <el-form-item label="期刊/会议"><el-input v-model="form.journal_name" /></el-form-item>
        <el-form-item label="DOI"><el-input v-model="form.doi" placeholder="10.xxxx/xxx" /></el-form-item>
        <el-form-item label="PDF URL"><el-input v-model="form.pdf_url" /></el-form-item>
        <el-form-item label="摘要"><el-input v-model="form.summary" type="textarea" :rows="2" /></el-form-item>
        <el-form-item label="封面 URL"><el-input v-model="form.cover_image" /></el-form-item>
        <el-form-item label="状态">
          <el-select v-model="form.status" class="w-full">
            <el-option label="草稿" value="draft" />
            <el-option label="已发布" value="published" />
            <el-option label="已归档" value="archived" />
          </el-select>
        </el-form-item>
        <el-form-item label="置顶"><el-switch v-model="form.priority" /></el-form-item>
        <el-form-item label="正文"><el-input v-model="form.content" type="textarea" :rows="8" /></el-form-item>
      </el-form>
      <template #footer>
        <button @click="dialogVisible = false" class="px-4 py-2 border rounded-lg">取消</button>
        <button @click="save" class="px-4 py-2 bg-primary text-white rounded-lg ml-2">保存</button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '@/services/api.js'

const list = ref([])
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const filter = ref({ keyword: '', status: '', category: '' })
const dialogVisible = ref(false)
const form = ref({ title: '', content: '', summary: '', cover_image: '', category: 'general', priority: false, status: 'draft', author_name: '', journal_name: '', doi: '', pdf_url: '' })

function statusClass(s) { return { published: 'bg-green-100 text-green-700', draft: 'bg-yellow-100 text-yellow-700', archived: 'bg-gray-100 text-gray-500' }[s] || '' }
function statusLabel(s) { return { published: '已发布', draft: '草稿', archived: '已归档' }[s] || s }
function formatTime(t) { return t ? new Date(t).toLocaleString('zh-CN', { hour12: false }) : '-' }

async function load() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value, ...filter.value }
    Object.keys(params).forEach(k => params[k] === '' && delete params[k])
    const res = await api.get('/association/academic/admin', { params })
    if (res.code === 0) {
      list.value = res.data || []
      total.value = res.total || 0
    }
  } catch (e) { ElMessage.error(e.message) }
  finally { loading.value = false }
}

function search() { currentPage.value = 1; load() }
watch(filter, () => search(), { deep: true })

function openEdit(a) {
  form.value = a ? { ...a, priority: !!a.priority } : { title: '', content: '', summary: '', cover_image: '', category: 'general', priority: false, status: 'draft', author_name: '', journal_name: '', doi: '', pdf_url: '' }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.title) return ElMessage.error('标题必填')
  try {
    const payload = { ...form.value, priority: form.value.priority ? 1 : 0, server_profile_id: form.value.server_profile_id || 1 }
    delete payload.id; delete payload.created_at; delete payload.updated_at; delete payload.view_count
    const res = form.value.id
      ? await api.put(`/association/academic/${form.value.id}`, payload)
      : await api.post('/association/academic', payload)
    if (res.code === 0) { ElMessage.success('已保存'); dialogVisible.value = false; load() }
  } catch (e) { ElMessage.error(e.message) }
}

async function del(id) {
  try {
    await ElMessageBox.confirm('确认删除此学术动态?', '提示', { type: 'warning' })
    const res = await api.delete(`/association/academic/${id}`)
    if (res.code === 0) { ElMessage.success('已删除'); load() }
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message) }
}

onMounted(load)
</script>