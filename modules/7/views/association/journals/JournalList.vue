<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">期刊管理</h1>
        <p class="text-sm text-gray-500 mt-1">每期发布 / 卷号期号 / PDF 上传 / 下载统计</p>
      </div>
      <button @click="openEdit()" class="px-4 py-2 bg-primary text-white rounded-lg">+ 新增期刊</button>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3">
        <input v-model="filter.keyword" placeholder="搜索标题/卷号/期号" class="px-3 py-2 border rounded-lg flex-1" @keyup.enter="search" />
        <select v-model="filter.status" class="px-3 py-2 border rounded-lg">
          <option value="">全部状态</option>
          <option value="published">已发布</option>
          <option value="draft">草稿</option>
          <option value="archived">已归档</option>
        </select>
        <button @click="search" class="px-4 py-2 bg-primary text-white rounded-lg">搜索</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">标题</th>
            <th class="px-4 py-3 text-left">卷/期</th>
            <th class="px-4 py-3 text-left">发布日期</th>
            <th class="px-4 py-3 text-left">状态</th>
            <th class="px-4 py-3 text-left">下载数</th>
            <th class="px-4 py-3 text-left">排序</th>
            <th class="px-4 py-3 text-left">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="j in list" :key="j.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800">{{ j.title }}</div>
              <div v-if="j.description" class="text-xs text-gray-400 mt-1 line-clamp-1">{{ j.description }}</div>
            </td>
            <td class="px-4 py-3 text-sm">{{ j.volume || '-' }} / {{ j.issue || '-' }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ j.publish_date || '-' }}</td>
            <td class="px-4 py-3">
              <span :class="['px-2 py-0.5 rounded text-xs', statusClass(j.status)]">{{ statusLabel(j.status) }}</span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ j.download_count || 0 }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ j.sort_order || 99 }}</td>
            <td class="px-4 py-3">
              <button @click="openEdit(j)" class="text-primary text-sm hover:underline mr-2">编辑</button>
              <button @click="del(j.id)" class="text-red-500 text-sm hover:underline">删除</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">暂无期刊</div>

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

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑期刊' : '新增期刊'" width="700px" :close-on-click-modal="false">
      <el-form :model="form" label-width="100px">
        <el-form-item label="标题" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="卷号"><el-input v-model="form.volume" placeholder="Vol. 12" /></el-form-item>
        <el-form-item label="期号"><el-input v-model="form.issue" placeholder="No. 3" /></el-form-item>
        <el-form-item label="发布日期"><el-input v-model="form.publish_date" placeholder="2026-08-01" /></el-form-item>
        <el-form-item label="分类">
          <el-select v-model="form.category" class="w-full">
            <el-option label="综合" value="general" />
            <el-option label="专刊" value="special" />
            <el-option label="特辑" value="issue" />
          </el-select>
        </el-form-item>
        <el-form-item label="封面 URL"><el-input v-model="form.cover_image" /></el-form-item>
        <el-form-item label="PDF URL"><el-input v-model="form.pdf_url" placeholder="/uploads/journals/xxx.pdf" /></el-form-item>
        <el-form-item label="描述"><el-input v-model="form.description" type="textarea" :rows="3" /></el-form-item>
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
const filter = ref({ keyword: '', status: '' })
const dialogVisible = ref(false)
const form = ref({ title: '', volume: '', issue: '', cover_image: '', description: '', pdf_url: '', publish_date: '', category: 'general', status: 'draft', sort_order: 99 })

function statusClass(s) { return { published: 'bg-green-100 text-green-700', draft: 'bg-yellow-100 text-yellow-700', archived: 'bg-gray-100 text-gray-500' }[s] || '' }
function statusLabel(s) { return { published: '已发布', draft: '草稿', archived: '已归档' }[s] || s }

async function load() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value, ...filter.value }
    Object.keys(params).forEach(k => params[k] === '' && delete params[k])
    const res = await api.get('/association/journals/admin', { params })
    if (res.code === 0) {
      list.value = res.data || []
      total.value = res.total || 0
    }
  } catch (e) { ElMessage.error(e.message) }
  finally { loading.value = false }
}

function search() { currentPage.value = 1; load() }
watch(filter, () => search(), { deep: true })

function openEdit(j) {
  form.value = j ? { ...j } : { title: '', volume: '', issue: '', cover_image: '', description: '', pdf_url: '', publish_date: '', category: 'general', status: 'draft', sort_order: 99 }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.title) return ElMessage.error('标题必填')
  try {
    const payload = { ...form.value, server_profile_id: form.value.server_profile_id || 1 }
    delete payload.id; delete payload.created_at; delete payload.updated_at; delete payload.view_count; delete payload.download_count
    const res = form.value.id
      ? await api.put(`/association/journals/${form.value.id}`, payload)
      : await api.post('/association/journals', payload)
    if (res.code === 0) { ElMessage.success('已保存'); dialogVisible.value = false; load() }
  } catch (e) { ElMessage.error(e.message) }
}

async function del(id) {
  try {
    await ElMessageBox.confirm('确认删除此期刊?', '提示', { type: 'warning' })
    const res = await api.delete(`/association/journals/${id}`)
    if (res.code === 0) { ElMessage.success('已删除'); load() }
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message) }
}

onMounted(load)
</script>