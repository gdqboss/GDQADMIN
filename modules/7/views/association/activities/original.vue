<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">活动报名</h1>
        <p class="text-sm text-gray-500 mt-1">活动创建 / 报名管理 / 状态流转</p>
      </div>
      <button @click="openEdit()" class="px-4 py-2 bg-primary text-white rounded-lg">+ 创建活动</button>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3">
        <input v-model="filter.keyword" placeholder="搜索标题/描述" class="px-3 py-2 border rounded-lg flex-1" @keyup.enter="search" />
        <select v-model="filter.status" class="px-3 py-2 border rounded-lg">
          <option value="">全部状态</option>
          <option value="draft">草稿</option>
          <option value="open">报名中</option>
          <option value="closed">已截止</option>
          <option value="finished">已结束</option>
          <option value="cancelled">已取消</option>
        </select>
        <button @click="search" class="px-4 py-2 bg-primary text-white rounded-lg">搜索</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">活动</th>
            <th class="px-4 py-3 text-left">时间</th>
            <th class="px-4 py-3 text-left">地点</th>
            <th class="px-4 py-3 text-left">报名情况</th>
            <th class="px-4 py-3 text-left">状态</th>
            <th class="px-4 py-3 text-left">操作</th>
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
              <button @click="viewRegs(a)" class="text-primary text-sm hover:underline mr-2">报名表</button>
              <button @click="openEdit(a)" class="text-primary text-sm hover:underline mr-2">编辑</button>
              <button @click="del(a.id)" class="text-red-500 text-sm hover:underline">删除</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">暂无活动</div>

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
    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑活动' : '创建活动'" width="750px" :close-on-click-modal="false">
      <el-form :model="form" label-width="100px">
        <el-form-item label="标题" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="副标题"><el-input v-model="form.subtitle" /></el-form-item>
        <el-form-item label="分类">
          <el-select v-model="form.category" class="w-full">
            <el-option label="沙龙" value="salon" /><el-option label="讲座" value="lecture" />
            <el-option label="考察" value="tour" /><el-option label="聚会" value="gathering" />
            <el-option label="培训" value="training" />
          </el-select>
        </el-form-item>
        <el-form-item label="封面"><el-input v-model="form.cover_image" /></el-form-item>
        <el-form-item label="描述"><el-input v-model="form.description" type="textarea" :rows="3" /></el-form-item>
        <el-form-item label="地点"><el-input v-model="form.location" /></el-form-item>
        <el-form-item label="开始时间" required>
          <el-date-picker v-model="form.start_time" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="w-full" />
        </el-form-item>
        <el-form-item label="结束时间">
          <el-date-picker v-model="form.end_time" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="w-full" />
        </el-form-item>
        <el-form-item label="报名截止">
          <el-date-picker v-model="form.registration_deadline" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="w-full" />
        </el-form-item>
        <el-form-item label="人数上限"><el-input-number v-model="form.max_participants" :min="0" /></el-form-item>
        <el-form-item label="费用"><el-input-number v-model="form.fee" :min="0" :precision="2" /></el-form-item>
        <el-form-item label="状态">
          <el-select v-model="form.status" class="w-full">
            <el-option label="草稿" value="draft" /><el-option label="报名中" value="open" />
            <el-option label="已截止" value="closed" /><el-option label="已结束" value="finished" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-form-item>
        <el-form-item label="主办方"><el-input v-model="form.organizer" /></el-form-item>
        <el-form-item label="联系人"><el-input v-model="form.contact_person" /></el-form-item>
        <el-form-item label="联系电话"><el-input v-model="form.contact_phone" /></el-form-item>
      </el-form>
      <template #footer>
        <button @click="dialogVisible = false" class="px-4 py-2 border rounded-lg">取消</button>
        <button @click="save" class="px-4 py-2 bg-primary text-white rounded-lg ml-2">保存</button>
      </template>
    </el-dialog>

    <!-- 报名列表 -->
    <el-dialog v-model="regsDialogVisible" :title="`报名名单 - ${currentActivity?.title || ''}`" width="900px">
      <div class="flex gap-3 mb-4">
        <select v-model="regFilter.status" class="px-3 py-2 border rounded-lg" @change="loadRegs">
          <option value="">全部</option>
          <option value="pending">待审核</option>
          <option value="confirmed">已确认</option>
          <option value="rejected">已拒绝</option>
          <option value="cancelled">已取消</option>
          <option value="attended">已出席</option>
        </select>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">姓名</th>
            <th class="px-3 py-2 text-left">电话</th>
            <th class="px-3 py-2 text-left">公司</th>
            <th class="px-3 py-2 text-left">职位</th>
            <th class="px-3 py-2 text-left">状态</th>
            <th class="px-3 py-2 text-left">报名时间</th>
            <th class="px-3 py-2 text-left">操作</th>
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
                <el-option label="待审核" value="pending" /><el-option label="已确认" value="confirmed" />
                <el-option label="已拒绝" value="rejected" /><el-option label="已出席" value="attended" />
              </el-select>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="regs.length === 0" class="p-8 text-center text-gray-400">暂无报名</div>
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
function statusLabel(s) { return { open: '报名中', draft: '草稿', closed: '已截止', finished: '已结束', cancelled: '已取消' }[s] || s }
function regStatusClass(s) { return { pending: 'bg-yellow-100 text-yellow-700', confirmed: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700', cancelled: 'bg-gray-100 text-gray-500', attended: 'bg-blue-100 text-blue-700' }[s] || '' }
function regStatusLabel(s) { return { pending: '待审核', confirmed: '已确认', rejected: '已拒绝', cancelled: '已取消', attended: '已出席' }[s] || s }

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
  if (!form.value.title) return ElMessage.error('标题必填')
  if (!form.value.start_time) return ElMessage.error('开始时间必填')
  try {
    const payload = { ...form.value, server_profile_id: form.value.server_profile_id || 1 }
    delete payload.id; delete payload.created_at; delete payload.updated_at; delete payload.current_participants
    const res = form.value.id
      ? await api.put(`/association/activities/${form.value.id}`, payload)
      : await api.post('/association/activities', payload)
    if (res.code === 0) { ElMessage.success('已保存'); dialogVisible.value = false; load() }
  } catch (e) { ElMessage.error(e.message) }
}

async function del(id) {
  try {
    await ElMessageBox.confirm('确认删除此活动? 若存在有效报名则无法删除', '提示', { type: 'warning' })
    const res = await api.delete(`/association/activities/${id}`)
    if (res.code === 0) { ElMessage.success('已删除'); load() }
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
    if (res.code === 0) { ElMessage.success('已更新'); loadRegs() }
  } catch (e) { ElMessage.error(e.message) }
}

onMounted(load)
</script>