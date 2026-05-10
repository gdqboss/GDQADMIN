<template>
  <div class="image-uploader">
    <!-- 已上传图片 -->
    <div v-if="previewUrls.length" class="preview-grid">
      <div v-for="(url, idx) in previewUrls" :key="idx" class="preview-item">
        <img :src="url" class="preview-img" />
        <button class="remove-btn" @click="remove(idx)">×</button>
      </div>
    </div>

    <!-- 上传按钮 -->
    <label v-if="previewUrls.length < maxFiles" class="upload-area">
      <span class="material-symbols-outlined">add_photo_alternate</span>
      <span class="upload-hint">
        {{ modelValue.length === 0 ? $t('imageUploader.uploadImage') : $t('imageUploader.continueAdding', { count: modelValue.length, max: maxFiles }) }}
      </span>
      <input type="file" :accept="accept" multiple @change="handleFile" class="hidden-input" />
    </label>

    <p class="upload-tip">{{ $t('imageUploader.maxUploadHint', { max: maxFiles, size: maxSize / 1024 / 1024 }) }}</p>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  maxFiles: { type: Number, default: 5 },
  maxSize: { type: Number, default: 5 * 1024 * 1024 },
  accept: { type: String, default: 'image/jpeg,image/png,image/webp' }
})
const emit = defineEmits(['update:modelValue'])

const previewUrls = ref([])

watch(() => props.modelValue, (files) => {
  previewUrls.value = files.map(f => URL.createObjectURL(f))
}, { immediate: true })

function handleFile(e) {
  const files = Array.from(e.target.files)
  for (const file of files) {
    if (props.modelValue.length >= props.maxFiles) break
    if (!props.accept.split(',').some(t => file.type === t.trim())) {
      alert(`不支持的格式: ${file.name}`)
      continue
    }
    if (file.size > props.maxSize) {
      alert(`文件太大: ${file.name} (最大${props.maxSize / 1024 / 1024}MB)`)
      continue
    }
    const newFiles = [...props.modelValue, file]
    emit('update:modelValue', newFiles)
  }
  e.target.value = ''
}

function remove(idx) {
  const newFiles = [...props.modelValue]
  newFiles.splice(idx, 1)
  emit('update:modelValue', newFiles)
}
</script>

<style scoped>
.image-uploader { display: flex; flex-direction: column; gap: 8px; }
.preview-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.preview-item { position: relative; aspect-ratio: 1 / 1; }
.preview-img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; border: 1px solid #eee; }
.remove-btn {
  position: absolute; top: -6px; right: -6px;
  width: 22px; height: 22px; border-radius: 50%;
  background: #f44336; color: #fff; border: none;
  font-size: 16px; line-height: 1; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}
.upload-area {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 6px; padding: 20px;
  border: 2px dashed #ddd; border-radius: 8px;
  cursor: pointer; transition: all 0.2s;
  color: #999;
}
.upload-area:hover { border-color: #667eea; background: rgba(102,126,234,0.05); color: #667eea; }
.upload-hint { font-size: 13px; }
.hidden-input { display: none; }
.upload-tip { font-size: 12px; color: #999; }
</style>
