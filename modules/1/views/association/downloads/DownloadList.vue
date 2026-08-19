<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">资料下载</h1>
        <p class="text-sm text-gray-500 mt-1">表格 / 模板 / 指南 / 规章 / 上传下载</p>
      </div>
      <button @click="openEdit()" class="px-4 py-2 bg-primary text-white rounded-lg">+ 上传资料</button>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3">
        <input v-model="filter.keyword" placeholder="搜索标题/文件名" class="px-3 py-2 border rounded-lg flex-1" @keyup.enter="search" />
        <select v-model="filter.status" class="px-3 py-2 border rounded-lg">
          <option value="">全部状态</option>
          <option value="published">已发布</option>
          <option value="draft">草稿</option>
          <option value="archived">已归档</option>
        </select>
        <select v-model="filter.category" class="px-3 py-2 border rounded-lg">
          <option value="">全部分类</option>
          <option value="general">综合</option>
          <option value="form">表格</option>
          <option value="template">模板</option>
          <option value="guide">指南</option>
          <option value="regulation">规章</option>
        </select>
        <button @click="search" class="px-4 py-2 bg-primary text-white rounded-lg">搜索</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">标题</th>
            <th class="px-4 py-3 text-left">文件名</th>
            <th class="px-4 py-3 text-left">类型</th>
            <th class="px-4 py-3 text-left">分类</th>
            <th class="px-4 py-3 text-left">状态</th>
            <th class="px-4 py-3 text-left">下载数</th>
            <th class="px-4 py-3 text-left">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in list" :key="d.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800">{{ d.title }}</div>
              <div v-if="d.description" class="text-xs text-gray-400 mt-1 line-clamp-1">{{ d.description }}</div>
            </td>
            <td class="px-4 py-3 text-sm">{{ d.file_name || '-' }}</td>
            <td class="px-4 py-3 text-sm uppercase">{{ d.file_type || '-' }}</td>
            <td class="px-4 py-3 text-sm">{{ categoryLabel(d.category) }}</td>
            <td class="px-4 py-3">
              <span :class="['px-2 py-0.5 rounded text-xs', statusClass(d.status)]">{{ statusLabel(d.status) }}</span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ d.download_count || 0 }}</td>
            <td class="px-4 py-3">
              <button @click="openEdit(d)" class="text-primary text-sm hover:underline mr-2">编辑</button>
              <button @click="del(d.id)" class="text-red-500 text-sm hover:underline">删除</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">暂无资料</div>

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

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑资料' : '上传资料'" width="700px" :close-on-click-modal="false">
      <el-form :model="form" label-width="100px">
        <el-form-item label="标题" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="分类">
          <el-select v-model="form.category" class="w-full">
            <el-option label="综合" value="general" />
            <el-option label="表格" value="form" />
            <el-option label="模板" value="template" />
            <el-option label="指南" value="guide" />
            <el-option label="规章" value="regulation" />
          </el-select>
        </el-form-item>
        <el-form-item label="文件 URL" required><el-input v-model="form.file_url" placeholder="/uploads/downloads/xxx.pdf" /></el-form-item>
        <el-form-item label="文件名"><el-input v-model="form.file_name" /></el-form-item>
        <el-form-item label="文件类型">
          <el-select v-model="form.file_type" class="w-full">
            <el-option label="PDF" value="pdf" /><el-option label="Word" value="doc" />
            <el-option label="Excel" value="xls" /><el-option label="Zip" value="zip" />
            <el-option label="图片" value="image" />
          </el-select>
        </el-form-item>
        <el-form-item label="文件大小(KB)"><el-input-number v-model="form.file_size" :min="0" /></el-form-item>
        <el-form-item label="封面 URL"><el-input v-model="form.cover_image" /></el-form-item>
        <el-form-item label="描述"><el-input v-model="form.description" type="textarea" :rows="2" /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="form.sort_order" :min="0" :max="999" /></el-form-item>
        <el-form-item label="状态">
          <el-select v-model="form.status" class="w-full">
            <el-option label="草稿" value="draft" />
            <el-option label="已发布" value="published" />
            <el-option label="已归档" value="archived" />
          </el-select>
        </el-form-item>
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
const form = ref({ title: '', description: '', file_url: '', file_name: '', file_size: 0, file_type: 'pdf', category: 'general', cover_image: '', status: 'draft', sort_order: 99 })

function categoryLabel(c) { return { general: '综合', form: '表格', template: '模板', guide: '指南', regulation: '规章' }[c] || c }
function statusClass(s) { return { published: 'bg-green-100 text-green-700', draft: 'bg-yellow-100 text-yellow-700', archived: 'bg-gray-100 text-gray-500' }[s] || '' }
function statusLabel(s) { return { published: '已发布', draft: '草稿', archived: '已归档' }[s] || s }

async function load() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value, ...filter.value }
    Object.keys(params).forEach(k => params[k] === '' && delete params[k])
    const res = await api.get('/association/downloads/admin', { params })
    if (res.code === 0) {
      list.value = res.data || []
      total.value = res.total || 0
    }
  } catch (e) { ElMessage.error(e.message) }
  finally { loading.value = false }
}

function search() { currentPage.value = 1; load() }
watch(filter, () => search(), { deep: true })

function openEdit(d) {
  form.value = d ? { ...d } : { title: '', description: '', file_url: '', file_name: '', file_size: 0, file_type: 'pdf', category: 'general', cover_image: '', status: 'draft', sort_order: 99 }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.title) return ElMessage.error('标题必填')
  if (!form.value.file_url) return ElMessage.error('文件 URL 必填')
  try {
    const payload = { ...form.value, server_profile_id: form.value.server_profile_id || 1 }
    delete payload.id; delete payload.created_at; delete payload.updated_at; delete payload.download_count
    const res = form.value.id
      ? await api.put(`/association/downloads/${form.value.id}`, payload)
      : await api.post('/association/downloads', payload)
    if (res.code === 0) { ElMessage.success('已保存'); dialogVisible.value = false; load() }
  } catch (e) { ElMessage.error(e.message) }
}

async function del(id) {
  try {
    await ElMessageBox.confirm('确认删除此资料?', '提示', { type: 'warning' })
    const res = await api.delete(`/association/downloads/${id}`)
    if (res.code === 0) { ElMessage.success('已删除'); load() }
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message) }
}

onMounted(load)
</script>