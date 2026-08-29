<template>
  <div class="invoice-uploader">
    <div
      class="upload-area"
      :class="{ 'drag-over': isDragOver }"
      @drop.prevent="handleDrop"
      @dragover.prevent="isDragOver = true"
      @dragleave.prevent="isDragOver = false"
    >
      <input
        ref="fileInput"
        type="file"
        accept=".jpg,.jpeg,.png,.pdf"
        @change="handleFileSelect"
        class="hidden"
      />

      <div v-if="!file && !imageUrl" class="upload-prompt">
        <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <p class="mt-2 text-sm text-gray-600">{{ $t('invoice.dragOrClick') }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ $t('invoice.supportFormats') }}</p>
        <button
          type="button"
          @click="$refs.fileInput.click()"
          class="mt-3 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
        >
          {{ $t('invoice.selectFile') }}
        </button>
      </div>

      <div v-else class="preview-area">
        <div v-if="uploading" class="upload-progress">
          <div class="progress-bar">
            <div class="progress-fill" :style="{ width: uploadProgress + '%' }"></div>
          </div>
          <p class="text-sm text-gray-600 mt-2">{{ $t('invoice.uploading') }} {{ uploadProgress }}%</p>
        </div>

        <div v-else class="preview-content">
          <div v-if="isPdf" class="pdf-placeholder">
            <svg class="w-20 h-20 mx-auto text-red-500" fill="currentColor" viewBox="0 0 20 20">
              <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
              <path d="M8 10a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
            </svg>
            <p class="text-sm text-gray-600 mt-2">PDF</p>
          </div>
          <img v-else :src="imageUrl" alt="Invoice" class="invoice-preview" />

          <div class="preview-actions">
            <button
              type="button"
              @click="removeFile"
              class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600"
            >
              {{ $t('common.remove') }}
            </button>
            <button
              type="button"
              @click="$refs.fileInput.click()"
              class="px-3 py-1 bg-gray-500 text-white rounded text-sm hover:bg-gray-600 ml-2"
            >
              {{ $t('common.change') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  modelValue: {
    type: [File, String],
    default: null
  }
})

const emit = defineEmits(['update:modelValue'])

const fileInput = ref(null)
const file = ref(null)
const imageUrl = ref('')
const isDragOver = ref(false)
const uploading = ref(false)
const uploadProgress = ref(0)

const isPdf = computed(() => {
  if (file.value) {
    return file.value.type === 'application/pdf'
  }
  if (typeof props.modelValue === 'string') {
    return props.modelValue.endsWith('.pdf')
  }
  return false
})

watch(() => props.modelValue, (newVal) => {
  if (typeof newVal === 'string' && newVal) {
    imageUrl.value = newVal
  } else if (!newVal) {
    file.value = null
    imageUrl.value = ''
  }
}, { immediate: true })

const handleFileSelect = (event) => {
  const selectedFile = event.target.files[0]
  if (selectedFile) {
    processFile(selectedFile)
  }
}

const handleDrop = (event) => {
  isDragOver.value = false
  const droppedFile = event.dataTransfer.files[0]
  if (droppedFile) {
    processFile(droppedFile)
  }
}

const processFile = (selectedFile) => {
  const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf']
  const maxSize = 10 * 1024 * 1024 // 10MB

  if (!validTypes.includes(selectedFile.type)) {
    alert(t('invoice.onlyJpgPngPdf'))
    return
  }

  if (selectedFile.size > maxSize) {
    alert(t('invoice.fileSizeLimit'))
    return
  }

  file.value = selectedFile

  if (selectedFile.type !== 'application/pdf') {
    const reader = new FileReader()
    reader.onload = (e) => {
      imageUrl.value = e.target.result
    }
    reader.readAsDataURL(selectedFile)
  } else {
    imageUrl.value = ''
  }

  emit('update:modelValue', selectedFile)
}

const removeFile = () => {
  file.value = null
  imageUrl.value = ''
  if (fileInput.value) {
    fileInput.value.value = ''
  }
  emit('update:modelValue', null)
}
</script>

<style scoped>
.invoice-uploader {
  width: 100%;
}

.upload-area {
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  padding: 2rem;
  text-align: center;
  transition: all 0.3s;
  min-height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.upload-area.drag-over {
  border-color: #3b82f6;
  background-color: #eff6ff;
}

.hidden {
  display: none;
}

.preview-area {
  width: 100%;
}

.upload-progress {
  width: 100%;
}

.progress-bar {
  width: 100%;
  height: 8px;
  background-color: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background-color: #3b82f6;
  transition: width 0.3s;
}

.preview-content {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.invoice-preview {
  max-width: 100%;
  max-height: 300px;
  object-fit: contain;
  border-radius: 4px;
}

.pdf-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.preview-actions {
  margin-top: 1rem;
}
</style>
