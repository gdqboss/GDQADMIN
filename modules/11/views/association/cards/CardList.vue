<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('association.cards.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('association.cards.subtitle') }}</p>
      </div>
      <button @click="openEdit()" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t("association.cards.add") }}</button>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3">
        <input v-model="filter.keyword" :placeholder="$t('association.cards.searchPlaceholder')" class="px-3 py-2 border rounded-lg flex-1" @keyup.enter="search" />
        <select v-model="filter.card_level" class="px-3 py-2 border rounded-lg">
          <option value="">{{ $t('association.members.allLevels') }}</option>
          <option value="member">{{ $t('association.members.ordinary') }}</option>
          <option value="senior">{{ $t("association.cards.seniorMember") }}</option>
          <option value="director">{{ $t('association.cards.director') }}</option>
        </select>
        <button @click="search" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('association.common.search') }}</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">{{ $t('association.org.name') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.cards.companyPosition') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.level') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.cards.contact') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.cards.isVisible') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.operations') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in list" :key="c.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <img v-if="c.avatar" :src="c.avatar" class="w-10 h-10 rounded-full object-cover" />
                <div v-else class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center text-lg">
                  {{ c.name.charAt(0) }}
                </div>
                <span class="font-medium">{{ c.name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-sm">
              <div>{{ c.company || '-' }}</div>
              <div class="text-xs text-gray-400">{{ c.title || '' }}</div>
            </td>
            <td class="px-4 py-3">
              <span :class="['px-2 py-0.5 rounded text-xs', levelClass(c.card_level)]">{{ levelLabel(c.card_level) }}</span>
            </td>
            <td class="px-4 py-3 text-sm">
              <div v-if="c.phone">📱 {{ c.phone }}</div>
              <div v-if="c.email">✉️ {{ c.email }}</div>
              <div v-if="c.wechat">💬 {{ c.wechat }}</div>
            </td>
            <td class="px-4 py-3">
              <span :class="c.is_visible ? 'text-green-600' : 'text-gray-400'">{{ c.is_visible ? $t('association.cards.isVisible') : $t('association.common.hidden') }}</span>
            </td>
            <td class="px-4 py-3">
              <button @click="openEdit(c)" class="text-primary text-sm hover:underline mr-2">{{ $t('association.common.edit') }}</button>
              <button @click="del(c.id)" class="text-red-500 text-sm hover:underline">{{ $t('association.common.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">{{ $t('association.cards.noData') }}</div>

      <div v-if="total > pageSize" class="p-4 flex justify-end">
        <el-pagination v-model:current-page="currentPage" v-model:page-size="pageSize" :total="total" layout="prev, pager, next" @current-change="load" />
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="form.id ? form.id ? $t('association.cards.dialogTitle') : $t('association.cards.add') : $t('association.cards.add')" width="600px" :close-on-click-modal="false">
      <el-form :model="form" label-width="100px">
        <el-form-item :label="$t('association.org.name')" required><el-input v-model="form.name" /></el-form-item>
        <el-form-item :label="$t('association.cards.avatarUrl')"><el-input v-model="form.avatar" /></el-form-item>
        <el-form-item :label="$t('association.cards.company')"><el-input v-model="form.company" /></el-form-item>
        <el-form-item :label="$t('association.cards.positionTitle')"><el-input v-model="form.title" /></el-form-item>
        <el-form-item :label="$t('association.cards.industry')"><el-input v-model="form.industry" /></el-form-item>
        <el-form-item :label="$t('association.common.level')">
          <el-select v-model="form.card_level" class="w-full">
            <el-option :label="$t('association.members.ordinary')" value="member" /><el-option :label="$t('association.cards.seniorMember')" value="senior" />
            <el-option :label="$t('association.cards.director')" value="director" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('association.cards.publicPhone')"><el-input v-model="form.phone" /></el-form-item>
        <el-form-item :label="$t('association.common.email')"><el-input v-model="form.email" /></el-form-item>
        <el-form-item :label="$t('association.cards.wechat')"><el-input v-model="form.wechat" /></el-form-item>
        <el-form-item :label="$t('association.cards.bio')"><el-input v-model="form.bio" type="textarea" :rows="3" /></el-form-item>
        <el-form-item :label="$t('association.cards.hobbies')"><el-input v-model="form.interests" :placeholder="$t('association.cards.commaSeparated')" /></el-form-item>
        <el-form-item :label="$t('association.common.sort')"><el-input-number v-model="form.sort_order" :min="0" /></el-form-item>
        <el-form-item :label="$t('association.cards.publicWall')"><el-switch v-model="form.is_visible" /></el-form-item>
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
const filter = ref({ keyword: '', card_level: '' })
const dialogVisible = ref(false)
const form = ref({})

function levelClass(l) { return { member: 'bg-gray-100 text-gray-700', senior: 'bg-blue-100 text-blue-700', director: 'bg-primary/10 text-primary' }[l] || '' }
function levelLabel(l) { return { member: $t('association.common.ordinary'), senior: $t('association.cards.seniorMember'), director: $t('association.cards.director') }[l] || l }

async function load() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value, ...filter.value }
    Object.keys(params).forEach(k => params[k] === '' && delete params[k])
    const res = await api.get('/association/cards/admin', { params })
    if (res.code === 0) {
      list.value = res.data || []
      total.value = res.total || 0
    }
  } catch (e) { ElMessage.error(e.message) }
  finally { loading.value = false }
}

function search() { currentPage.value = 1; load() }
watch(filter, () => search(), { deep: true })

function openEdit(c) {
  form.value = c ? { ...c, is_visible: !!c.is_visible } : { name: '', card_level: 'member', is_visible: true, sort_order: 99 }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.name) return ElMessage.error($t('association.common.nameRequired'))
  try {
    const payload = { ...form.value, is_visible: form.value.is_visible ? 1 : 0, server_profile_id: form.value.server_profile_id || 1 }
    delete payload.id; delete payload.created_at; delete payload.updated_at
    const res = form.value.id
      ? await api.put(`/association/cards/${form.value.id}`, payload)
      : await api.post('/association/cards', payload)
    if (res.code === 0) { ElMessage.success($t('association.common.saved')); dialogVisible.value = false; load() }
  } catch (e) { ElMessage.error(e.message) }
}

async function del(id) {
  try {
    await ElMessageBox.confirm($t('association.cards.confirmDelete'), $t('association.common.hint'), { type: 'warning' })
    const res = await api.delete(`/association/cards/${id}`)
    if (res.code === 0) { ElMessage.success($t('association.common.deleted')); load() }
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message) }
}

onMounted(load)
</script>