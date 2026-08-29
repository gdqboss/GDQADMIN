<template>
  <div
    @click="$emit('select')"
    class="border-2 rounded-lg p-4 cursor-pointer transition-all hover:shadow-lg hover:border-primary"
    :class="selected ? 'border-primary bg-primary/5' : 'border-gray-200'"
  >
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 rounded-full flex items-center justify-center" :class="selected ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600'">
        <span class="material-symbols-outlined text-2xl">{{ icon }}</span>
      </div>
      <div class="flex-1">
        <h3 class="font-semibold text-gray-800">{{ name }}</h3>
        <p class="text-sm text-gray-500">{{ description }}</p>
      </div>
      <span v-if="selected" class="material-symbols-outlined text-primary">check_circle</span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  icon: String,
  name: String,
  description: String,
  selected: Boolean
})

defineEmits(['select'])
</script>
