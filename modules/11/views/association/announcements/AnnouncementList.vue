<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('association.announcements.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('association.announcements.subtitle') }}</p>
      </div>
      <button @click="openEdit()" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t("association.announcements.publish") }}</button>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3">
        <input v-model="filter.keyword" :placeholder="$t('association.announcements.searchPlaceholder')" class="px-3 py-2 border rounded-lg flex-1" @keyup.enter="search" />
        <select v-model="filter.status" class="px-3 py-2 border rounded-lg">
          <option value="">{{ $t('association.activities.allStatus') }}</option>
          <option value="published">{{ $t('association.common.published') }}</option>
          <option value="draft">{{ $t('association.common.draft') }}</option>
          <option value="archived">{{ $t('association.common.archived') }}</option>
        </select>
        <select v-model="filter.category" class="px-3 py-2 border rounded-lg">
          <option value="">{{ $t('association.announcements.allCategories') }}</option>
          <option value="general">{{ $t('association.announcements.general') }}</option>
          <option value="notice">{{ $t('association.announcements.notice') }}</option>
          <option value="event">{{ $t('association.announcements.activity') }}</option>
          <option value="recruit">{{ $t('association.announcements.recruitment') }}</option>
        </select>
        <button @click="search" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('association.common.search') }}</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">{{ $t('association.common.title') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.category') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.status') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.top') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.academic.browse') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.publishTime') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.operations') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in list" :key="a.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800">{{ a.title }}</div>
              <div v-if="a.summary" class="text-xs text-gray-400 mt-1 line-clamp-1">{{ a.summary }}</div>
            </td>
            <td class="px-4 py-3 text-sm">{{ categoryLabel(a.category) }}</td>
            <td class="px-4 py-3">
              <span :class="['px-2 py-0.5 rounded text-xs', statusClass(a.status)]">{{ statusLabel(a.status) }}</span>
            </td>
            <td class="px-4 py-3">{{ a.priority ? $t('association.announcements.top') : '-' }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ a.view_count || 0 }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ formatTime(a.published_at || a.created_at) }}</td>
            <td class="px-4 py-3">
              <button @click="openEdit(a)" class="text-primary text-sm hover:underline mr-2">{{ $t('association.common.edit') }}</button>
              <button @click="del(a.id)" class="text-red-500 text-sm hover:underline">{{ $t('association.common.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">{{ $t('association.announcements.noData') }}</div>

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

    <el-dialog v-model="dialogVisible" :title="form.id ? form.id ? $t('association.announcements.dialogTitle') : $t('association.announcements.publish') : $t('association.announcements.publish')" width="700px" :close-on-click-modal="false">
      <el-form :model="form" label-width="100px">
        <el-form-item :label="$t('association.common.title')" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item :label="$t('association.common.category')">
          <el-select v-model="form.category" class="w-full">
            <el-option :label="$t('association.announcements.general')" value="general" /><el-option :label="$t('association.announcements.notice')" value="notice" />
            <el-option :label="$t('association.announcements.activity')" value="event" /><el-option :label="$t('association.announcements.recruitment')" value="recruit" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('association.common.summary')"><el-input v-model="form.summary" type="textarea" :rows="2" /></el-form-item>
        <el-form-item :label="$t('association.common.coverUrl')"><el-input v-model="form.cover_image" /></el-form-item>
        <el-form-item :label="$t('association.common.status')">
          <el-select v-model="form.status" class="w-full">
            <el-option :label="$t('association.common.draft')" value="draft" /><el-option :label="$t('association.common.published')" value="published" /><el-option :label="$t('association.common.archived')" value="archived" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('association.common.top')"><el-switch v-model="form.priority" /></el-form-item>
        <el-form-item :label="$t('association.announcements.content')"><el-input v-model="form.content" type="textarea" :rows="8" /></el-form-item>
      </el-form>
      <template #footer>
        <button @click="dialogVisible = false" class="px-4 py-2 border rounded-lg">{{ $t('association.common.cancel') }}</button>
        <button @click="save" class="px-4 py-2 bg-primary text-white rounded-lg ml-2">{{ $t('association.common.save') }}</button>
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
const form = ref({ title: '', content: '', summary: '', cover_image: '', category: 'general', priority: false, status: 'draft' })

function categoryLabel(c) {
  return { general: $t('association.announcements.general'), notice: $t('association.announcements.notice'), event: $t('association.announcements.activity'), recruit: $t('association.announcements.recruitment') }[c] || c
}
function statusClass(s) { return { published: 'bg-green-100 text-green-700', draft: 'bg-yellow-100 text-yellow-700', archived: 'bg-gray-100 text-gray-500' }[s] || '' }
function statusLabel(s) { return { published: $t('association.common.published'), draft: $t('association.common.draft'), archived: $t('association.common.archived') }[s] || s }
function formatTime(t) { return t ? new Date(t).toLocaleString('zh-CN', { hour12: false }) : '-' }

async function load() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value, ...filter.value }
    Object.keys(params).forEach(k => params[k] === '' && delete params[k])
    const res = await api.get('/association/announcements/admin', { params })
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
  form.value = a ? { ...a, priority: !!a.priority } : { title: '', content: '', summary: '', cover_image: '', category: 'general', priority: false, status: 'draft' }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.title) return ElMessage.error($t('association.common.titleRequired'))
  try {
    const payload = { ...form.value, priority: form.value.priority ? 1 : 0, server_profile_id: form.value.server_profile_id || 1 }
    delete payload.id; delete payload.created_at; delete payload.updated_at; delete payload.view_count
    const res = form.value.id
      ? await api.put(`/association/announcements/${form.value.id}`, payload)
      : await api.post('/association/announcements', payload)
    if (res.code === 0) { ElMessage.success($t('association.common.saved')); dialogVisible.value = false; load() }
  } catch (e) { ElMessage.error(e.message) }
}

async function del(id) {
  try {
    await ElMessageBox.confirm($t('association.announcements.confirmDelete'), $t('association.common.hint'), { type: 'warning' })
    const res = await api.delete(`/association/announcements/${id}`)
    if (res.code === 0) { ElMessage.success($t('association.common.deleted')); load() }
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message) }
}

onMounted(load)
</script>