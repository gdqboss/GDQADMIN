<script setup>
import { ref, computed } from 'vue'
import api from '../services/api.js'

const props = defineProps({
  // 单选模式：v-model绑定单个URL字符串
  modelValue: { type: String, default: '' },
  // 多选模式：v-model:selected绑定URL数组
  selected: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: false },
  max: { type: Number, default: 9 },
})

const emit = defineEmits(['update:modelValue', 'update:selected'])

const showModal = ref(false)
const images = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const limit = 30

async function open() {
  showModal.value = true
  page.value = 1
  await loadImages()
}

async function loadImages() {
  loading.value = true
  try {
    const res = await api.get(`/upload/images?page=${page.value}&limit=${limit}`)
    if (res.code === 0) {
      images.value = res.data.list
      total.value = res.data.total
    }
  } catch (e) {
    console.error('加载图片失败', e)
  } finally {
    loading.value = false
  }
}

function isSelected(img) {
  if (props.multiple) return props.selected.includes(img.url)
  return props.modelValue === img.url
}

function toggleImage(img) {
  if (!props.multiple) {
    emit('update:modelValue', img.url)
    showModal.value = false
    return
  }
  const arr = [...props.selected]
  const idx = arr.indexOf(img.url)
  if (idx > -1) arr.splice(idx, 1)
  else {
    if (arr.length >= props.max) return
    arr.push(img.url)
  }
  emit('update:selected', arr)
}

function confirm() {
  showModal.value = false
}

function nextPage() {
  if (page.value * limit < total.value) {
    page.value++
    loadImages()
  }
}

function prevPage() {
  if (page.value > 1) {
    page.value--
    loadImages()
  }
}
</script>

<template>
  <div>
    <!-- 触发按钮（显示在外部供点击） -->
    <button @click="open" type="button" class="flex items-center gap-2 px-3 py-1.5 border border-dashed border-gray-300 rounded-lg text-sm text-gray-500 hover:border-primary hover:text-primary transition-colors">
      <span class="material-symbols-outlined text-lg">photo_library</span>
      从图片库选择
    </button>

    <!-- 已选预览（小图展示） -->
    <div v-if="multiple && selected.length" class="flex gap-1 mt-2 flex-wrap">
      <div v-for="url in selected" :key="url" class="relative w-12 h-12 rounded overflow-hidden border">
        <img :src="url" class="w-full h-full object-cover" />
      </div>
    </div>
    <div v-else-if="!multiple && modelValue" class="mt-2 w-16 h-16 rounded overflow-hidden border">
      <img :src="modelValue" class="w-full h-full object-cover" />
    </div>

    <!-- 图片选择弹窗 -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-xl w-[640px] max-h-[85vh] flex flex-col">
          <!-- 标题栏 -->
          <div class="flex items-center justify-between px-5 py-4 border-b shrink-0">
            <h3 class="text-base font-semibold">选择图片</h3>
            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- 图片网格 -->
          <div class="flex-1 overflow-y-auto p-4">
            <div v-if="loading" class="flex justify-center py-8 text-gray-400">加载中...</div>
            <div v-else-if="images.length === 0" class="flex flex-col items-center py-8 text-gray-400">
              <span class="material-symbols-outlined text-4xl mb-2">image_not_supported</span>
              <p>暂无已上传图片</p>
            </div>
            <div v-else class="grid grid-cols-5 gap-2">
              <div
                v-for="img in images"
                :key="img.id"
                @click="toggleImage(img)"
                :class="[
                  'relative aspect-square rounded cursor-pointer overflow-hidden border-2 transition-colors',
                  isSelected(img) ? 'border-primary' : 'border-transparent hover:border-gray-300'
                ]"
              >
                <img :src="img.url" class="w-full h-full object-cover" />
                <div v-if="isSelected(img)" class="absolute inset-0 bg-primary/30 flex items-center justify-center">
                  <span class="material-symbols-outlined text-white text-2xl">check_circle</span>
                </div>
              </div>
            </div>
          </div>

          <!-- 底部分页+确认 -->
          <div class="flex items-center justify-between px-5 py-3 border-t bg-gray-50 rounded-b-xl shrink-0">
            <div class="flex items-center gap-2">
              <button @click="prevPage" :disabled="page === 1" class="px-3 py-1 text-sm rounded border hover:bg-gray-100 disabled:opacity-40">上一页</button>
              <span class="text-sm text-gray-500">{{ total === 0 ? 0 : page }}/{{ Math.ceil(total / limit) }}页</span>
              <button @click="nextPage" :disabled="page >= Math.ceil(total / limit)" class="px-3 py-1 text-sm rounded border hover:bg-gray-100 disabled:opacity-40">下一页</button>
            </div>
            <div class="flex items-center gap-3">
              <span v-if="multiple" class="text-sm text-gray-500">已选 {{ selected.length }}/{{ max }} 张</span>
              <button @click="confirm" class="px-4 py-2 bg-primary text-white text-sm rounded-lg hover:bg-primary/90">确认</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>