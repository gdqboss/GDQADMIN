<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('association.members.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('association.members.subtitle') }}</p>
      </div>
      <button @click="openEdit()" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t("association.members.add") }}</button>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3">
        <input v-model="filter.keyword" :placeholder="$t('association.members.searchPlaceholder')" class="px-3 py-2 border rounded-lg flex-1" @keyup.enter="search" />
        <select v-model="filter.member_level" class="px-3 py-2 border rounded-lg">
          <option value="">{{ $t('association.members.allLevels') }}</option>
          <option v-for="lv in levels" :key="lv.id" :value="lv.id">{{ lv.name }}</option>
        </select>
        <select v-model="filter.customer_type" class="px-3 py-2 border rounded-lg">
          <option value="">{{ $t('association.members.allTypes') }}</option>
          <option value="gov">{{ $t('association.members.gov') }}</option>
          <option value="biz">{{ $t('association.members.enterprise') }}</option>
          <option value="peer">{{ $t('association.members.peer') }}</option>
          <option value="normal">{{ $t('association.common.ordinary') }}</option>
        </select>
        <button @click="search" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('association.common.search') }}</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">ID</th>
            <th class="px-4 py-3 text-left">{{ $t('association.org.name') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.phone') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.type') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.level') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.points') }}</th>
            <th class="px-4 py-3 text-left">{{ $t("association.members.totalSpend") }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.operations') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in list" :key="m.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3 text-sm text-gray-500">#{{ m.id }}</td>
            <td class="px-4 py-3 font-medium">{{ m.name }}</td>
            <td class="px-4 py-3 text-sm">{{ m.phone || '-' }}</td>
            <td class="px-4 py-3 text-sm">{{ customerTypeLabel(m.customer_type) }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 bg-primary/10 text-primary rounded text-xs">{{ m.level_name || $t('association.common.ordinary') }}</span>
            </td>
            <td class="px-4 py-3 text-sm">{{ m.points || 0 }}</td>
            <td class="px-4 py-3 text-sm">¥{{ m.total_spent || 0 }}</td>
            <td class="px-4 py-3">
              <button @click="openEdit(m)" class="text-primary text-sm hover:underline mr-2">{{ $t('association.common.edit') }}</button>
              <button @click="del(m.id)" class="text-red-500 text-sm hover:underline">{{ $t('association.common.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">{{ $t("association.members.noData") }}</div>

      <div v-if="total > pageSize" class="p-4 flex justify-end">
        <el-pagination v-model:current-page="currentPage" v-model:page-size="pageSize" :total="total" layout="prev, pager, next" @current-change="load" />
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="form.id ? form.id ? $t('association.members.dialogTitle') : $t('association.members.add') : $t('association.members.add')" width="500px">
      <el-form :model="form" label-width="100px">
        <el-form-item :label="$t('association.org.name')" required><el-input v-model="form.name" /></el-form-item>
        <el-form-item :label="$t('association.common.phone')"><el-input v-model="form.phone" /></el-form-item>
        <el-form-item :label="$t('association.common.type')">
          <el-select v-model="form.customer_type" class="w-full">
            <el-option :label="$t('association.common.ordinary')" value="normal" /><el-option :label="$t('association.members.gov')" value="gov" />
            <el-option :label="$t('association.members.enterprise')" value="biz" /><el-option :label="$t('association.members.peer')" value="peer" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('association.common.level')">
          <el-select v-model="form.member_level" class="w-full">
            <el-option v-for="lv in levels" :key="lv.id" :label="lv.name" :value="lv.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('association.common.points')"><el-input-number v-model="form.points" :min="0" /></el-form-item>
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
const filter = ref({ keyword: '', member_level: '', customer_type: '' })
const dialogVisible = ref(false)
const form = ref({})
const levels = ref([])

function customerTypeLabel(t) { return { normal: $t('association.common.ordinary'), gov: $t('association.members.gov'), biz: $t('association.members.enterprise'), peer: $t('association.members.peer') }[t] || t }

async function load() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value, ...filter.value }
    Object.keys(params).forEach(k => params[k] === '' && delete params[k])
    const res = await api.get('/association/members/admin', { params })
    if (res.code === 0) {
      list.value = res.data || []
      total.value = res.total || 0
    }
  } catch (e) { ElMessage.error(e.message) }
  finally { loading.value = false }
}

async function loadLevels() {
  try {
    const res = await api.get('/association/members/levels/all')
    if (res.code === 0) levels.value = res.data || []
  } catch (e) {}
}

function search() { currentPage.value = 1; load() }
watch(filter, () => search(), { deep: true })

function openEdit(m) {
  form.value = m ? { ...m } : { name: '', customer_type: 'normal', member_level: 1, points: 0 }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.name) return ElMessage.error($t('association.common.nameRequired'))
  try {
    const payload = { ...form.value }
    delete payload.id; delete payload.created_at; delete payload.level_name; delete payload.level_icon; delete payload.discount_rate; delete payload.total_spent
    const res = form.value.id
      ? await api.put(`/association/members/${form.value.id}`, payload)
      : await api.post('/association/members', payload)
    if (res.code === 0) { ElMessage.success($t('association.common.saved')); dialogVisible.value = false; load() }
  } catch (e) { ElMessage.error(e.message) }
}

async function del(id) {
  try {
    await ElMessageBox.confirm($t('association.members.confirmDelete'), $t('association.common.hint'), { type: 'warning' })
    const res = await api.delete(`/association/members/${id}`)
    if (res.code === 0) { ElMessage.success($t('association.common.deleted')); load() }
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message) }
}

onMounted(() => { loadLevels(); load() })
</script>