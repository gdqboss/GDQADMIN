<!--
  AI 数据中心 - 知识财富
  2026-09-18 江小鱼立
-->
<template>
  <div class="p-4">
    <PageHeader title="知识财富" subtitle="员工优秀思维 + AI 提炼 + 老板标记传承" />

    <!-- 筛选 -->
    <div class="bg-white rounded-lg p-3 mb-4 flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs text-text-secondary mb-1">类别</label>
        <select v-model="filterCategory" @change="fetchList"
          class="border border-slate-200 rounded px-2 py-1.5 text-sm">
          <option value="">全部</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-text-secondary mb-1">类型</label>
        <select v-model="filterInheritance" @change="fetchList"
          class="border border-slate-200 rounded px-2 py-1.5 text-sm">
          <option value="">全部</option>
          <option value="1">⭐ 老板传承</option>
          <option value="0">普通智慧</option>
        </select>
      </div>
      <div class="ml-auto text-xs text-text-secondary">
        共 {{ list.length }} 条 · 传承 {{ inheritCount }} 条
      </div>
    </div>

    <div v-if="loading" class="bg-white rounded-lg p-8 text-center text-text-secondary text-sm">
      加载中…
    </div>
    <div v-else-if="!list.length" class="bg-white rounded-lg p-8 text-center text-text-secondary text-sm">
      暂无知识财富
    </div>

    <div v-else class="space-y-3">
      <div v-for="w in list" :key="w.id"
           class="bg-white rounded-lg p-4 border-l-4"
           :class="w.is_inheritance ? 'border-amber-400' : 'border-slate-200'">
        <!-- 标题 + 传承标签 -->
        <div class="flex items-start gap-2 mb-2">
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <span v-if="w.is_inheritance"
                    class="text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded">
                ⭐ 老板传承
              </span>
              <span class="text-xs text-text-secondary">{{ w.category }}</span>
              <span v-if="w.inheritance_priority"
                    class="text-xs text-text-secondary">优先级 {{ w.inheritance_priority }}</span>
            </div>
            <div class="font-medium mt-1">{{ w.title }}</div>
          </div>
        </div>

        <!-- 三段式 (situation + action + reasoning) -->
        <div class="text-sm space-y-1 text-slate-700">
          <div v-if="w.situation">
            <span class="text-text-secondary text-xs">场景:</span>
            <span class="ml-1">{{ w.situation }}</span>
          </div>
          <div v-if="w.action">
            <span class="text-text-secondary text-xs">行动:</span>
            <span class="ml-1">{{ w.action }}</span>
          </div>
          <div v-if="w.reasoning">
            <span class="text-text-secondary text-xs">原因:</span>
            <span class="ml-1">{{ w.reasoning }}</span>
          </div>
        </div>

        <!-- tags + 统计 -->
        <div class="flex flex-wrap items-center gap-3 mt-3 pt-2 border-t border-slate-100 text-xs text-text-secondary">
          <div v-if="w.tags" class="flex flex-wrap gap-1">
            <span v-for="t in parseTags(w.tags)" :key="t"
                  class="px-2 py-0.5 bg-slate-100 rounded">{{ t }}</span>
          </div>
          <div class="ml-auto">被引用 {{ w.applied_count }} 次 · 效果 {{ ((w.effectiveness_score || 0) * 100).toFixed(0) }}%</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api.js'
import PageHeader from '../../components/PageHeader.vue'

const loading = ref(true)
const list = ref([])
const filterCategory = ref('')
const filterInheritance = ref('')

const categories = computed(() =>
  [...new Set(list.value.map(w => w.category).filter(Boolean))]
)
const inheritCount = computed(() => list.value.filter(w => w.is_inheritance).length)

const parseTags = (raw) => {
  if (Array.isArray(raw)) return raw
  if (typeof raw === 'string') {
    try { return JSON.parse(raw) } catch { return [] }
  }
  return []
}

const fetchList = async () => {
  loading.value = true
  try {
    let url = '/api/agent-memory/wisdom'
    const params = []
    if (filterCategory.value) params.push(`category=${encodeURIComponent(filterCategory.value)}`)
    if (filterInheritance.value !== '') params.push(`is_inheritance=${filterInheritance.value}`)
    if (params.length) url += '?' + params.join('&')
    const r = await api.get(url)
    if (r.code === 0) list.value = r.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchList)
</script>