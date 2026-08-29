<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('association.org.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('association.org.subtitle') }}</p>
      </div>
      <button @click="openEdit(null, 0)" class="px-4 py-2 bg-primary text-white rounded-lg">{{ $t("association.org.addRoot") }}</button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
      <div v-if="loading" class="p-8 text-center text-gray-400">{{ $t('association.common.loading') }}</div>
      <div v-else-if="flat.length === 0" class="p-8 text-center text-gray-400">{{ $t("association.org.noData") }}</div>
      <div v-else>
        <table class="w-full">
          <thead class="bg-gray-50 text-sm text-gray-600">
            <tr>
              <th class="px-4 py-3 text-left">{{ $t('association.org.namePosition') }}</th>
              <th class="px-4 py-3 text-left">{{ $t('association.org.depth') }}</th>
              <th class="px-4 py-3 text-left">{{ $t('association.org.visible') }}</th>
              <th class="px-4 py-3 text-left">{{ $t('association.common.sort') }}</th>
              <th class="px-4 py-3 text-left">{{ $t('association.common.operations') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="n in flat" :key="n.id" class="border-t hover:bg-gray-50">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800" :style="{ paddingLeft: (n.depth * 24) + 'px' }">
                  <span v-if="n.depth > 0" class="text-gray-400 mr-2">└─</span>
                  {{ n.name }}
                </div>
                <div v-if="n.title" class="text-xs text-gray-500 mt-1" :style="{ paddingLeft: (n.depth * 24 + 16) + 'px' }">
                  {{ n.title }}
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-500">第 {{ n.depth + 1 }} 层</td>
              <td class="px-4 py-3">
                <span :class="['px-2 py-0.5 rounded text-xs', n.is_visible ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                  {{ n.is_visible ? $t('association.common.visible') : $t('association.common.hidden') }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-500">{{ n.sort_order || 99 }}</td>
              <td class="px-4 py-3">
                <button @click="openEdit(null, n.id)" class="text-primary text-sm hover:underline mr-2">{{ $t("association.org.addChild") }}</button>
                <button @click="openEdit(n)" class="text-primary text-sm hover:underline mr-2">{{ $t('association.common.edit') }}</button>
                <button @click="del(n.id, n.name)" class="text-red-500 text-sm hover:underline">{{ $t('association.common.delete') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="form.id ? form.id ? $t('association.org.dialogTitle') : $t('association.org.addChild') : $t('association.org.addChild')" width="600px" :close-on-click-modal="false">
      <el-form :model="form" label-width="100px">
        <el-form-item :label="$t('association.common.name')" required><el-input v-model="form.name" :placeholder="$t('association.org.namePlaceholder')" /></el-form-item>
        <el-form-item :label="$t('association.org.position')"><el-input v-model="form.title" :placeholder="$t('association.org.positionPlaceholder')" /></el-form-item>
        <el-form-item :label="$t('association.org.parentNode')">
          <el-select v-model="form.parent_id" class="w-full" :disabled="!!form.id">
            <el-option :label="$t('association.org.rootNode')" :value="0" />
            <el-option v-for="n in flat" :key="n.id" :label="'└─ '.repeat(n.depth) + n.name" :value="n.id" :disabled="form.id === n.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('association.cards.avatarUrl')"><el-input v-model="form.avatar" /></el-form-item>
        <el-form-item :label="$t('association.common.description')"><el-input v-model="form.bio" type="textarea" :rows="3" /></el-form-item>
        <el-form-item :label="$t('association.common.sort')"><el-input-number v-model="form.sort_order" :min="0" :max="999" /></el-form-item>
        <el-form-item :label="$t('association.common.visible')"><el-switch v-model="form.is_visible" /></el-form-item>
      </el-form>
      <template #footer>
        <button @click="dialogVisible = false" class="px-4 py-2 border rounded-lg">{{ $t('association.common.cancel') }}</button>
        <button @click="save" class="px-4 py-2 bg-primary text-white rounded-lg ml-2">{{ $t('association.common.save') }}</button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '@/services/api.js'

const flat = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const form = ref({ id: null, name: '', title: '', avatar: '', bio: '', parent_id: 0, sort_order: 99, is_visible: true })

// 把树拍平成数组（深度优先），同时记录每个节点的 depth
function flattenTree(tree, depth = 0, result = []) {
  for (const node of tree) {
    result.push({ ...node, depth })
    if (node.children && node.children.length > 0) {
      flattenTree(node.children, depth + 1, result)
    }
  }
  return result
}

const flatList = computed(() => flattenTree([]))

async function load() {
  loading.value = true
  try {
    const r = await api.get('/association/org/admin')
    if (r.code === 0) {
      const rows = r.data || []
      const map = new Map()
      const roots = []
      rows.forEach(r => map.set(r.id, { ...r, children: [] }))
      rows.forEach(r => {
        const node = map.get(r.id)
        if (r.parent_id && map.has(r.parent_id)) {
          map.get(r.parent_id).children.push(node)
        } else {
          roots.push(node)
        }
      })
      flat.value = flattenTree(roots)
    }
  } catch (e) { ElMessage.error(e.message) }
  finally { loading.value = false }
}

function openEdit(node, parentId = null) {
  if (node) {
    form.value = { id: node.id, name: node.name, title: node.title || '', avatar: node.avatar || '', bio: node.bio || '', parent_id: node.parent_id, sort_order: node.sort_order || 99, is_visible: !!node.is_visible }
  } else {
    form.value = { id: null, name: '', title: '', avatar: '', bio: '', parent_id: parentId || 0, sort_order: 99, is_visible: true }
  }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.name) return ElMessage.error($t('association.common.nameRequired'))
  try {
    const payload = { ...form.value, server_profile_id: form.value.server_profile_id || 1, is_visible: form.value.is_visible ? 1 : 0 }
    delete payload.id
    const res = form.value.id
      ? await api.put(`/association/org/${form.value.id}`, payload)
      : await api.post('/association/org', payload)
    if (res.code === 0) { ElMessage.success($t('association.common.saved')); dialogVisible.value = false; load() }
  } catch (e) { ElMessage.error(e.message) }
}

async function del(id, name) {
  try {
    await ElMessageBox.confirm(`确认删除「${name}」及其所有子节点?\n(软删,is_visible=0,可恢复)`, $t('association.common.hint'), { type: 'warning' })
    const res = await api.delete(`/association/org/${id}`)
    if (res.code === 0) { ElMessage.success(`已软删 ${res.data.affected || 1} 个节点`); load() }
  } catch (e) { if (e !== 'cancel') ElMessage.error(e.message) }
}

onMounted(load)
</script>