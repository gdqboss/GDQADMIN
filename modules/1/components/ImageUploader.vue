<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  maxFiles: { type: Number, default: 5 },
  maxSize: { type: Number, default: 5 * 1024 * 1024 }, // 5MB
  accept: { type: String, default: 'image/jpeg,image/png,image/webp' }
})

const emit = defineEmits(['update:modelValue'])

const files = ref([])
const previews = ref([])

const canAddMore = computed(() => files.value.length < props.maxFiles)

function handleFileSelect(event) {
  const selectedFiles = Array.from(event.target.files)

  for (const file of selectedFiles) {
    if (files.value.length >= props.maxFiles) break

    // Validate file type
    if (!props.accept.split(',').some(type => file.type === type.trim())) {
      alert(t('imageUploader.unsupportedFormat', { name: file.name }))
      continue
    }

    // Validate file size
    if (file.size > props.maxSize) {
      alert(t('imageUploader.fileTooLarge', { name: file.name, size: props.maxSize / 1024 / 1024 }))
      continue
    }

    files.value.push(file)

    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      previews.value.push(e.target.result)
    }
    reader.readAsDataURL(file)
  }

  emit('update:modelValue', files.value)
  event.target.value = '' // Reset input
}

function removeFile(index) {
  files.value.splice(index, 1)
  previews.value.splice(index, 1)
  emit('update:modelValue', files.value)
}
</script>

<template>
  <div class="space-y-3">
    <!-- Preview grid -->
    <div v-if="previews.length > 0" class="grid grid-cols-3 gap-2">
      <div v-for="(preview, index) in previews" :key="index" class="relative aspect-square">
        <img :src="preview" class="w-full h-full object-cover rounded-lg border border-gray-200" />
        <button
          @click="removeFile(index)"
          class="absolute -top-2 -right-2 w-6 h-6 bg-danger text-white rounded-full flex items-center justify-center text-xs hover:bg-danger/80 transition-colors shadow-sm"
        >
          ×
        </button>
      </div>
    </div>

    <!-- Upload button -->
    <label v-if="canAddMore" class="flex items-center justify-center gap-2 w-full py-3 border-2 border-dashed border-gray-300 rounded-lg hover:border-primary hover:bg-primary/5 transition-colors cursor-pointer">
      <span class="material-symbols-outlined text-text-secondary">add_photo_alternate</span>
      <span class="text-sm text-text-secondary">
        {{ files.length === 0 ? $t('imageUploader.uploadImage') : $t('imageUploader.continueAdding', { count: files.length, max: maxFiles }) }}
      </span>
      <input
        type="file"
        :accept="accept"
        multiple
        @change="handleFileSelect"
        class="hidden"
      />
    </label>

    <!-- Hint -->
    <p class="text-xs text-text-secondary">
      {{ $t('imageUploader.maxUploadHint', { max: maxFiles, size: maxSize / 1024 / 1024 }) }}
    </p>
  </div>
</template>
