<!--
  AI 数据中心 - 员工画像列表
  2026-09-18 江小鱼立
-->
<template>
  <div class="p-4">
    <PageHeader title="员工 AI 画像" subtitle="基于全员行为自动累积 + AI 评分" />

    <div v-if="loading" class="bg-white rounded-lg p-8 text-center text-text-secondary text-sm">
      加载中…
    </div>
    <div v-else-if="!profiles.length" class="bg-white rounded-lg p-8 text-center text-text-secondary text-sm">
      暂无员工画像
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div v-for="p in profiles" :key="p.user_id"
           class="bg-white rounded-lg p-4">
        <!-- 头部 -->
        <div class="flex items-center gap-3 mb-3">
          <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center font-medium">
            {{ p.user_id }}
          </div>
          <div class="flex-1">
            <div class="font-medium">{{ styleLabel(p.thinking_style) }}</div>
            <div class="text-xs text-text-secondary">
              可靠 {{ (p.reliability_score * 100).toFixed(0) }}% ·
              速度 {{ (p.speed_score * 100).toFixed(0) }}% ·
              协作 {{ (p.collaboration_score * 100).toFixed(0) }}%
            </div>
          </div>
        </div>

        <!-- 4 个评分 -->
        <div class="grid grid-cols-4 gap-2 mb-3 text-center text-xs">
          <div>
            <div class="text-text-secondary">可靠</div>
            <div class="font-medium text-base">{{ (p.reliability_score * 100).toFixed(0) }}</div>
          </div>
          <div>
            <div class="text-text-secondary">速度</div>
            <div class="font-medium text-base">{{ (p.speed_score * 100).toFixed(0) }}</div>
          </div>
          <div>
            <div class="text-text-secondary">创造</div>
            <div class="font-medium text-base">{{ (p.creativity_score * 100).toFixed(0) }}</div>
          </div>
          <div>
            <div class="text-text-secondary">协作</div>
            <div class="font-medium text-base">{{ (p.collaboration_score * 100).toFixed(0) }}</div>
          </div>
        </div>

        <!-- 描述 -->
        <div v-if="p.trait_summary" class="text-sm text-slate-700 mb-2">{{ p.trait_summary }}</div>
        <div v-if="p.best_at" class="text-xs">
          <span class="text-green-700 font-medium">✓ 擅长:</span>
          <span class="text-slate-600 ml-1">{{ p.best_at }}</span>
        </div>
        <div v-if="p.watch_out" class="text-xs mt-1">
          <span class="text-amber-700 font-medium">⚠ 注意:</span>
          <span class="text-slate-600 ml-1">{{ p.watch_out }}</span>
        </div>

        <!-- 统计 -->
        <div class="flex gap-4 mt-3 pt-3 border-t border-slate-100 text-xs text-text-secondary">
          <div>{{ p.total_events }} 事件</div>
          <div>{{ p.wisdom_count }} 知识</div>
          <div>最近分析 {{ p.last_analyzed_at ? new Date(p.last_analyzed_at).toLocaleDateString() : '-' }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import PageHeader from '../../components/PageHeader.vue'

const loading = ref(true)
const profiles = ref([])

const styleMap = {
  relationship_first: '关系型员工',
  data_driven: '数据型员工',
  quick_executor: '执行型员工',
  mentor_type: '导师型员工',
}
const styleLabel = (s) => styleMap[s] || s || '未分类'

const fetchProfiles = async () => {
  loading.value = true
  try {
    const r = await api.get('/api/agent-memory/profiles')
    if (r.code === 0) profiles.value = r.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchProfiles)
</script>