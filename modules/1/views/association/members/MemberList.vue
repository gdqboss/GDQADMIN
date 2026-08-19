<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">会员管理</h1>
        <p class="text-sm text-gray-500 mt-1">会员资料 / 等级 / 积分</p>
      </div>
      <button @click="openEdit()" class="px-4 py-2 bg-primary text-white rounded-lg">+ 新增会员</button>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
      <div class="flex gap-3">
        <input v-model="filter.keyword" placeholder="搜索姓名/电话" class="px-3 py-2 border rounded-lg flex-1" @keyup.enter="search" />
        <select v-model="filter.member_level" class="px-3 py-2 border rounded-lg">
          <option value="">全部等级</option>
          <option v-for="lv in levels" :key="lv.id" :value="lv.id">{{ lv.name }}</option>
        </select>
        <select v-model="filter.customer_type" class="px-3 py-2 border rounded-lg">
          <option value="">全部类型</option>
          <option value="gov">政府</option>
          <option value="biz">企业</option>
          <option value="peer">同行</option>
          <option value="normal">普通</option>
        </select>
        <button @click="search" class="px-4 py-2 bg-primary text-white rounded-lg">搜索</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <table class="w-full">
        <thead class="bg-gray-50 text-sm text-gray-600">
          <tr>
            <th class="px-4 py-3 text-left">ID</th>
            <th class="px-4 py-3 text-left">姓名</th>
            <th class="px-4 py-3 text-left">电话</th>
            <th class="px-4 py-3 text-left">类型</th>
            <th class="px-4 py-3 text-left">等级</th>
            <th class="px-4 py-3 text-left">积分</th>
            <th class="px-4 py-3 text-left">累计消费</th>
            <th class="px-4 py-3 text-left">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in list" :key="m.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3 text-sm text-gray-500">#{{ m.id }}</td>
            <td class="px-4 py-3 font-medium">{{ m.name }}</td>
            <td class="px-4 py-3 text-sm">{{ m.phone || '-' }}</td>
            <td class="px-4 py-3 text-sm">{{ customerTypeLabel(m.customer_type) }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 bg-primary/10 text-primary rounded text-xs">{{ m.level_name || '普通' }}</span>
            </td>
            <td class="px-4 py-3 text-sm">{{ m.points || 0 }}</td>
            <td class="px-4 py-3 text-sm">¥{{ m.total_spent || 0 }}</td>
            <td class="px-4 py-3">
              <button @click="openEdit(m)" class="text-primary text-sm hover:underline mr-2">编辑</button>
              <button @click="del(m.id)" class="text-red-500 text-sm hover:underline">删除</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!loading && list.length === 0" class="p-8 text-center text-gray-400">暂无会员</div>

      <div v-if="total > pageSize" class="p-4 flex justify-end">
        <el-pagination v-model:current-page="currentPage" v-model:page-size="pageSize" :total="total" layout="prev, pager, next" @current-change="load" />
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑会员' : '新增会员'" width="500px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="姓名" required><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="电话"><el-input v-model="form.phone" /></el-form-item>
        <el-form-item label="类型">
          <el-select v-model="form.customer_type" class="w-full">
            <el-option label="普通" value="normal" /><el-option label="政府" value="gov" />
            <el-option label="企业" value="biz" /><el-option label="同行" value="peer" />
          </el-select>
        </el-form-item>
        <el-form-item label="等级">
          <el-select v-model="form.member_level" class="w-full">
            <el-option v-for="lv in levels" :key="lv.id" :label="lv.name" :value="lv.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="积分"><el-input-number v-model="form.points" :min="0" /></el-form-item>
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
const filter = ref({ keyword: '', member_level: '', customer_type: '' })
const dialogVisible = ref(false)
const form = ref({})
const levels = ref([])

function customerTypeLabel(t) { return { normal: '普通', gov: '政府', biz: '企业', peer: '同行' }[t] || t }

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
  if (!form.value.name) return ElMessage.error('姓名必填')
  try {
    const payload = { ...form.value }
    delete payload.id; delete payload.created_at; delete payload.level_name; delete payload.level_icon; delete payload.discount_rate; delete payload.total_spent
    const res = form.value.id
      ? await api.put(`/association/members/${form.value.id}`, payload)
      : await api.post('/association/members', payload)
    if (res.code === 0) { ElMessage.success('已保存'); dialogVisible.value = false; load() }
  } catch (e) { ElMessage.error(e.message) }
}

async function del(id) {
  try {
    await ElMessageBox.confirm('确认删除此会员?', '提示', { type: 'warning' })
    const res = await api.delete(`/association/members/${id}`)
    if (res.code === 0) { ElMessage.success('已删除'); load() }
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message) }
}

onMounted(() => { loadLevels(); load() })
</script>