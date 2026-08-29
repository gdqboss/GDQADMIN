<template>
  <div class="min-h-screen bg-gray-50">
    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl md:text-4xl font-bold mb-3">{{ $t('portal.nav.org') }}</h1>
        <p class="text-amber-100">{{ $t('portal.sections.organization') }}</p>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div v-if="loading" class="text-center py-20 text-gray-400">{{ $t('portal.loading') }}</div>

      <div v-else-if="flat.length === 0" class="text-center py-20 text-gray-400 bg-white rounded-xl">
        {{ $t('portal.list.no_data') }}
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="n in flat"
          :key="n.id"
          class="bg-white rounded-xl border border-gray-100 p-5 hover:shadow-sm transition"
          :class="{ 'border-l-4 border-l-amber-500': n.depth === 0 }"
          :style="{ marginLeft: (n.depth * 24) + 'px' }"
        >
          <div class="flex items-center gap-3">
            <div v-if="n.avatar" class="w-12 h-12 rounded-full overflow-hidden flex-shrink-0">
              <img :src="n.avatar" class="w-full h-full object-cover" />
            </div>
            <div v-else class="w-12 h-12 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white font-bold flex-shrink-0">
              {{ n.name?.[0] || '?' }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-bold text-gray-800">{{ n.name }}</div>
              <div v-if="n.title" class="text-sm text-amber-600">{{ n.title }}</div>
            </div>
          </div>
          <p v-if="n.bio" class="text-sm text-gray-500 mt-3 line-clamp-2">{{ n.bio }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const flat = ref([])
const loading = ref(false)

function flattenTree(tree, depth = 0, result = []) {
  for (const node of tree) {
    result.push({ ...node, depth })
    if (node.children?.length) flattenTree(node.children, depth + 1, result)
  }
  return result
}

onMounted(async () => {
  loading.value = true
  try {
    const { api } = await import('@/services/api.js')
    const r = await api.get('/association/org', { params: { server_profile_id: 7 } })
    if (r.code === 0) {
      const list = Array.isArray(r.data) ? r.data : (r.data.list || [])
      flat.value = flattenTree(list)
    }
  } catch (e) {} finally { loading.value = false }
})
</script>