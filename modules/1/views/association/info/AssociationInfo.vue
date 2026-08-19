<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">协会介绍</h1>
        <p class="text-sm text-gray-500 mt-1">协会基本信息 / 简介 / 联系方式</p>
      </div>
      <button @click="openEdit()" class="px-4 py-2 bg-primary text-white rounded-lg">
        {{ form.id ? '编辑信息' : '完善信息' }}
      </button>
    </div>

    <div v-if="!form.id && !loading" class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 text-center text-gray-500">
      尚未配置协会介绍,点击右上角"完善信息"开始填写
    </div>

    <div v-else class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
      <div class="flex items-start gap-6">
        <img v-if="form.logo" :src="form.logo" class="w-24 h-24 rounded-xl object-cover border" />
        <div class="flex-1">
          <h2 class="text-2xl font-bold text-gray-800">{{ form.name_zh || '未命名协会' }}</h2>
          <p v-if="form.name_zh_tw" class="text-sm text-gray-400 mt-1">{{ form.name_zh_tw }}</p>
          <p v-if="form.name_en" class="text-sm text-gray-400 mt-1">{{ form.name_en }}</p>          <p v-if="form.slogan" class="text-base text-primary mt-3 font-medium">{{ form.slogan }}</p>
          <p v-if="form.slogan_zh_tw" class="text-xs text-primary/70 mt-1">繁: {{ form.slogan_zh_tw }}</p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-6 mt-6 pt-6 border-t">
        <div v-if="form.founded_year"><label class="text-sm text-gray-500">成立年份</label><div class="mt-1">{{ form.founded_year }}</div></div>
        <div v-if="form.member_count > 0"><label class="text-sm text-gray-500">会员数</label><div class="mt-1">{{ form.member_count }}</div></div>
        <div v-if="form.phone"><label class="text-sm text-gray-500">联系电话</label><div class="mt-1">{{ form.phone }}</div></div>
        <div v-if="form.email"><label class="text-sm text-gray-500">邮箱</label><div class="mt-1">{{ form.email }}</div></div>
        <div v-if="form.website" class="col-span-2"><label class="text-sm text-gray-500">官网</label><div class="mt-1"><a :href="form.website" target="_blank" class="text-primary">{{ form.website }}</a></div></div>
        <div v-if="form.address" class="col-span-2"><label class="text-sm text-gray-500">地址</label><div class="mt-1">{{ form.address }}</div></div>
      </div>

      <div v-if="form.intro" class="mt-6 pt-6 border-t">
        <label class="text-sm text-gray-500">协会简介</label>
        <div class="mt-2 text-gray-700 whitespace-pre-wrap">{{ form.intro }}</div>
      </div>
      <div v-if="form.history" class="mt-4">
        <label class="text-sm text-gray-500">发展历程</label>
        <div class="mt-2 text-gray-700 whitespace-pre-wrap">{{ form.history }}</div>
      </div>
      <div v-if="form.vision" class="mt-4">
        <label class="text-sm text-gray-500">愿景使命</label>
        <div class="mt-2 text-gray-700 whitespace-pre-wrap">{{ form.vision }}</div>
      </div>
    </div>

    <!-- 编辑对话框 -->
    <el-dialog v-model="dialogVisible" title="编辑协会介绍" width="700px" :close-on-click-modal="false">
      <el-form :model="form" label-width="100px">
        <el-form-item label="中文名 (简体)"><el-input v-model="form.name_zh" /></el-form-item>
        <el-form-item label="中文名 (繁体)"><el-input v-model="form.name_zh_tw" placeholder="例: 新加坡彩美特協會" /></el-form-item>
        <el-form-item label="英文名"><el-input v-model="form.name_en" /></el-form-item>
        <el-form-item label="标语 (简体)"><el-input v-model="form.slogan" /></el-form-item>
        <el-form-item label="标语 (繁体)"><el-input v-model="form.slogan_zh_tw" /></el-form-item>
        <el-form-item label="标语 (英文)"><el-input v-model="form.slogan_en" /></el-form-item>
        <el-form-item label="Logo URL"><el-input v-model="form.logo" /></el-form-item>
        <el-form-item label="成立年份"><el-input-number v-model="form.founded_year" :min="1900" :max="2100" /></el-form-item>
        <el-form-item label="联系电话"><el-input v-model="form.phone" /></el-form-item>
        <el-form-item label="邮箱"><el-input v-model="form.email" /></el-form-item>
        <el-form-item label="官网"><el-input v-model="form.website" /></el-form-item>
        <el-form-item label="地址"><el-input v-model="form.address" type="textarea" :rows="2" /></el-form-item>
        <el-form-item label="协会简介"><el-input v-model="form.intro" type="textarea" :rows="4" /></el-form-item>
        <el-form-item label="发展历程"><el-input v-model="form.history" type="textarea" :rows="3" /></el-form-item>
        <el-form-item label="愿景使命"><el-input v-model="form.vision" type="textarea" :rows="3" /></el-form-item>
      </el-form>
      <template #footer>
        <button @click="dialogVisible = false" class="px-4 py-2 border rounded-lg">取消</button>
        <button @click="save()" class="px-4 py-2 bg-primary text-white rounded-lg ml-2">保存</button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import api from '@/services/api.js'

const form = ref({})
const dialogVisible = ref(false)
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await api.get('/association/info')
    if (res.code === 0) form.value = res.data || {}
  } catch (e) { ElMessage.error(e.message) }
  finally { loading.value = false }
}

function openEdit() {
  dialogVisible.value = true
}

async function save() {
  try {
    const payload = { ...form.value, server_profile_id: form.value.server_profile_id || 1 }
    delete payload.id
    delete payload.created_at
    delete payload.updated_at
    delete payload.member_count
    delete payload.updated_by
    const res = await api.put('/association/info', payload)
    if (res.code === 0) {
      ElMessage.success('已保存')
      dialogVisible.value = false
      load()
    }
  } catch (e) { ElMessage.error(e.message) }
}

onMounted(load)
</script>