<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('association.activities.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('association.activities.subtitle') }}</p>
      </div>
      <button @click="openEdit()" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t("association.activities.create") }}</button>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3">
        <input v-model="filter.keyword" :placeholder="$t('association.activities.searchPlaceholder')" class="px-3 py-2 border rounded-lg flex-1" @keyup.enter="search" />
        <select v-model="filter.status" class="px-3 py-2 border rounded-lg">
          <option value="">{{ $t('association.activities.allStatus') }}</option>
          <option value="draft">{{ $t('association.common.draft') }}</option>
          <option value="open">{{ $t('association.activities.enrolling') }}</option>
          <option value="closed">{{ $t('association.activities.closed') }}</option>
          <option value="finished">{{ $t('association.activities.ended') }}</option>
          <option value="cancelled">{{ $t('association.activities.cancelled') }}</option>
        </select>
        <button @click="search" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t('association.common.search') }}</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">{{ $t('association.announcements.activity') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.activities.time') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.activities.location') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.activities.enrollment') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.status') }}</th>
            <th class="px-4 py-3 text-left">{{ $t('association.common.operations') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in list" :key="a.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800">{{ a.title }}</div>
              <div v-if="a.subtitle" class="text-xs text-gray-400 mt-1">{{ a.subtitle }}</div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ formatTime(a.start_time) }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ a.location || '-' }}</td>
            <td class="px-4 py-3 text-sm">
              {{ a.current_participants }} / {{ a.max_participants || '∞' }}
            </td>
            <td class="px-4 py-3">
              <span :class="['px-2 py-0.5 rounded text-xs', statusClass(a.status)]">{{ statusLabel(a.status) }}</span>
            </td>
            <td class="px-4 py-3">
              <button @click="viewRegs(a)" class="text-primary text-sm hover:underline mr-2">{{ $t("association.activities.registerList") }}</button>
              <button @click="openEdit(a)" class="text-primary text-sm hover:underline mr-2">{{ $t('association.common.edit') }}</button>
              <button @click="del(a.id)" class="text-red-500 text-sm hover:underline">{{ $t('association.common.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">{{ $t('association.activities.noData') }}</div>

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

    <!-- 创建/编辑 -->
    <el-dialog v-model="dialogVisible" :title="form.id ? form.id ? $t('association.activities.dialogTitle') : $t('association.activities.create') : $t('association.activities.create')" width="750px" :close-on-click-modal="false">
      <el-form :model="form" label-width="100px">
        <el-form-item :label="$t('association.common.title')" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item :label="$t('association.activities.subtitle')"><el-input v-model="form.subtitle" /></el-form-item>
        <el-form-item :label="$t('association.common.category')">
          <el-select v-model="form.category" class="w-full">
            <el-option :label="$t('association.activities.salon')" value="salon" /><el-option :label="$t('association.activities.lecture')" value="lecture" />
            <el-option :label="$t('association.activities.study')" value="tour" /><el-option :label="$t('association.activities.gathering')" value="gathering" />
            <el-option :label="$t('association.activities.training')" value="training" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('association.activities.cover')"><el-input v-model="form.cover_image" /></el-form-item>
        <el-form-item :label="$t('association.activities.description')"><el-input v-model="form.description" type="textarea" :rows="3" /></el-form-item>
        <el-form-item :label="$t('association.activities.location')"><el-input v-model="form.location" /></el-form-item>
        <el-form-item :label="$t('association.activities.startTime')" required>
          <el-date-picker v-model="form.start_time" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="w-full" />
        </el-form-item>
        <el-form-item :label="$t('association.activities.endTime')">
          <el-date-picker v-model="form.end_time" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="w-full" />
        </el-form-item>
        <el-form-item :label="$t('association.activities.deadline')">
          <el-date-picker v-model="form.registration_deadline" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="w-full" />
        </el-form-item>
        <el-form-item :label="$t('association.activities.maxPeople')"><el-input-number v-model="form.max_participants" :min="0" /></el-form-item>
        <el-form-item :label="$t('association.activities.fee')"><el-input-number v-model="form.fee" :min="0" :precision="2" /></el-form-item>
        <el-form-item :label="$t('association.common.status')">
          <el-select v-model="form.status" class="w-full">
            <el-option :label="$t('association.common.draft')" value="draft" /><el-option :label="$t('association.activities.enrolling')" value="open" />
            <el-option :label="$t('association.activities.closed')" value="closed" /><el-option :label="$t('association.activities.ended')" value="finished" />
            <el-option :label="$t('association.activities.cancelled')" value="cancelled" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('association.activities.organizer')"><el-input v-model="form.organizer" /></el-form-item>
        <el-form-item :label="$t('association.activities.contact')"><el-input v-model="form.contact_person" /></el-form-item>
        <el-form-item :label="$t('association.activities.contactPhone')"><el-input v-model="form.contact_phone" /></el-form-item>
      </el-form>
      <template #footer>
        <button @click="dialogVisible = false" class="px-4 py-2 border rounded-lg">{{ $t('association.common.cancel') }}</button>
        <button @click="save" class="px-4 py-2 bg-primary text-white rounded-lg ml-2">{{ $t('association.common.save') }}</button>
      </template>
    </el-dialog>

    <!-- 报名列表 -->
    <el-dialog v-model="regsDialogVisible" :title="`${$t('association.activities.registerList')} - ${currentActivity?.title || ''}`" width="900px">
      <div class="flex gap-3 mb-4">
        <select v-model="regFilter.status" class="px-3 py-2 border rounded-lg" @change="loadRegs">
          <option value="">{{ $t('association.common.all') }}</option>
          <option value="pending">{{ $t("association.activities.pending") }}</option>
          <option value="confirmed">{{ $t("association.activities.confirmed") }}</option>
          <option value="rejected">{{ $t("association.activities.rejected") }}</option>
          <option value="cancelled">{{ $t('association.activities.cancelled') }}</option>
          <option value="attended">{{ $t("association.activities.attended") }}</option>
        </select>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">{{ $t('association.org.name') }}</th>
            <th class="px-3 py-2 text-left">{{ $t('association.common.phone') }}</th>
            <th class="px-3 py-2 text-left">{{ $t('association.cards.company') }}</th>
            <th class="px-3 py-2 text-left">{{ $t('association.org.position') }}</th>
            <th class="px-3 py-2 text-left">{{ $t('association.common.status') }}</th>
            <th class="px-3 py-2 text-left">{{ $t("association.activities.regTime") }}</th>
            <th class="px-3 py-2 text-left">{{ $t('association.common.operations') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in regs" :key="r.id" class="border-t">
            <td class="px-3 py-2">{{ r.member_name }}</td>
            <td class="px-3 py-2">{{ r.member_phone }}</td>
            <td class="px-3 py-2">{{ r.member_company || '-' }}</td>
            <td class="px-3 py-2">{{ r.member_title || '-' }}</td>
            <td class="px-3 py-2">
              <span :class="['px-2 py-0.5 rounded text-xs', regStatusClass(r.status)]">{{ regStatusLabel(r.status) }}</span>
            </td>
            <td class="px-3 py-2 text-xs text-gray-500">{{ formatTime(r.created_at) }}</td>
            <td class="px-3 py-2">
              <el-select v-model="r.status" @change="updateRegStatus(r)" size="small" class="!w-28">
                <el-option :label="$t('association.activities.pending')" value="pending" /><el-option :label="$t('association.activities.confirmed')" value="confirmed" />
                <el-option :label="$t('association.activities.rejected')" value="rejected" /><el-option :label="$t('association.activities.attended')" value="attended" />
              </el-select>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="regs.length === 0" class="p-8 text-center text-gray-400">{{ $t("association.activities.noRegData") }}</div>
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
const regsDialogVisible = ref(false)
const form = ref({})
const regs = ref([])
const currentActivity = ref(null)
const regFilter = ref({ status: '' })

function formatTime(t) { return t ? new Date(t).toLocaleString('zh-CN', { hour12: false }) : '-' }
function statusClass(s) {
  return { open: 'bg-green-100 text-green-700', draft: 'bg-gray-100 text-gray-500', closed: 'bg-yellow-100 text-yellow-700', finished: 'bg-blue-100 text-blue-700', cancelled: 'bg-red-100 text-red-700' }[s] || ''
}
function statusLabel(s) { return { open: $t('association.activities.enrolling'), draft: $t('association.common.draft'), closed: $t('association.activities.closed'), finished: $t('association.activities.ended'), cancelled: $t('association.activities.cancelled') }[s] || s }
function regStatusClass(s) { return { pending: 'bg-yellow-100 text-yellow-700', confirmed: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700', cancelled: 'bg-gray-100 text-gray-500', attended: 'bg-blue-100 text-blue-700' }[s] || '' }
function regStatusLabel(s) { return { pending: $t('association.activities.pending'), confirmed: $t('association.activities.confirmed'), rejected: $t('association.activities.rejected'), cancelled: $t('association.activities.cancelled'), attended: $t('association.activities.attended') }[s] || s }

async function load() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value, ...filter.value }
    Object.keys(params).forEach(k => params[k] === '' && delete params[k])
    const res = await api.get('/association/activities/admin', { params })
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
  form.value = a ? { ...a } : { title: '', subtitle: '', description: '', location: '', cover_image: '', start_time: '', end_time: '', registration_deadline: '', max_participants: 0, fee: 0, status: 'draft', category: 'salon', organizer: '', contact_person: '', contact_phone: '' }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.title) return ElMessage.error($t('association.common.titleRequired'))
  if (!form.value.start_time) return ElMessage.error($t('association.activities.startTimeRequired'))
  try {
    const payload = { ...form.value, server_profile_id: form.value.server_profile_id || 1 }
    delete payload.id; delete payload.created_at; delete payload.updated_at; delete payload.current_participants
    const res = form.value.id
      ? await api.put(`/association/activities/${form.value.id}`, payload)
      : await api.post('/association/activities', payload)
    if (res.code === 0) { ElMessage.success($t('association.common.saved')); dialogVisible.value = false; load() }
  } catch (e) { ElMessage.error(e.message) }
}

async function del(id) {
  try {
    await ElMessageBox.confirm($t('association.activities.confirmDelete'), $t('association.common.hint'), { type: 'warning' })
    const res = await api.delete(`/association/activities/${id}`)
    if (res.code === 0) { ElMessage.success($t('association.common.deleted')); load() }
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message) }
}

async function viewRegs(a) {
  currentActivity.value = a
  regsDialogVisible.value = true
  await loadRegs()
}

async function loadRegs() {
  if (!currentActivity.value) return
  try {
    const params = { ...regFilter.value }
    Object.keys(params).forEach(k => params[k] === '' && delete params[k])
    const res = await api.get(`/association/activities/${currentActivity.value.id}/registrations`, { params })
    if (res.code === 0) regs.value = res.data || []
  } catch (e) { ElMessage.error(e.message) }
}

async function updateRegStatus(r) {
  try {
    const res = await api.put(`/association/activities/${currentActivity.value.id}/registrations/${r.id}`, { status: r.status })
    if (res.code === 0) { ElMessage.success($t('association.common.updated')); loadRegs() }
  } catch (e) { ElMessage.error(e.message) }
}

onMounted(load)
</script>