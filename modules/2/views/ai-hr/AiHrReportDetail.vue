<!--
  AI HR 报告详情（gdqadmin）
  - 顶部: 综合分 + 推荐 + summary
  - 中部: 预设条件结果 + 软素质评分（带 evidence）
  - 底部: 完整对话记录（HR 回看）
-->
<template>
  <div class="p-4 max-w-5xl mx-auto">
    <PageHeader title="招聘报告详情" :subtitle="`#${evaluation?.id || ''} · ${evaluation?.candidate_name || ''}`">
      <template #actions>
        <button @click="$router.back()" class="px-3 py-1.5 text-sm border border-slate-200 rounded-md">
          返回
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-12 text-text-secondary">加载中…</div>
    <div v-else-if="!evaluation" class="text-center py-12 text-red-600">报告不存在</div>

    <template v-else>
      <!-- 概览卡 -->
      <div class="bg-gradient-to-br from-primary to-primary/70 text-white rounded-2xl p-6 mb-4 shadow-md">
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="text-xs opacity-80">应聘岗位</div>
            <div class="text-lg font-medium mt-0.5">{{ evaluation.job_title }}</div>
          </div>
          <span :class="['px-3 py-1 rounded-full text-xs', recClassBg(evaluation.recommendation)]">
            {{ recLabel(evaluation.recommendation) }}
          </span>
        </div>
        <div class="flex items-end gap-6">
          <div>
            <div class="text-4xl font-bold">{{ evaluation.overall_score }}</div>
            <div class="text-xs opacity-80">综合评分 / 100</div>
          </div>
          <div v-if="evaluation.summary" class="flex-1 text-sm opacity-95 leading-relaxed">
            {{ evaluation.summary }}
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <!-- 预设条件 -->
        <div class="bg-white rounded-xl p-4 border border-slate-100">
          <div class="text-sm font-medium text-text-primary mb-3 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base">rule</span>
            预设硬性条件
          </div>
          <div v-if="!parseJson(evaluation.preset_results).length" class="text-xs text-text-secondary py-3">
            本次对话未触发预设条件（AI 未提取到完整回答）
          </div>
          <div v-else class="space-y-2.5">
            <div v-for="(p, i) in parseJson(evaluation.preset_results)" :key="i"
              class="flex items-start gap-2 text-xs">
              <span :class="['material-symbols-outlined text-base mt-0.5',
                            p.passed ? 'text-green-500' : 'text-red-500']">
                {{ p.passed ? 'check_circle' : 'cancel' }}
              </span>
              <div class="flex-1">
                <div class="text-text-primary">{{ p.question }}</div>
                <div class="text-text-secondary mt-0.5">
                  要求 <span class="text-text-primary">{{ p.requirement }}</span> · 回答
                  <span class="text-text-primary">{{ p.answer || '未回答' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 软素质评分 -->
        <div class="bg-white rounded-xl p-4 border border-slate-100">
          <div class="text-sm font-medium text-text-primary mb-3 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base">psychology</span>
            软素质评价
          </div>
          <div v-if="!parseJson(evaluation.soft_scores).length" class="text-xs text-text-secondary py-3">
            暂无评分
          </div>
          <div v-else class="space-y-3">
            <div v-for="(s, i) in parseJson(evaluation.soft_scores)" :key="i">
              <div class="flex items-center justify-between text-xs mb-1">
                <span class="text-text-primary">{{ s.label }} <span class="text-text-secondary">({{ s.key }})</span></span>
                <span class="font-medium text-text-primary">{{ s.score }}/10</span>
              </div>
              <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-primary transition-all" :style="{ width: (s.score * 10) + '%' }"></div>
              </div>
              <div v-if="s.evidence" class="text-[11px] text-text-secondary mt-1.5 italic bg-slate-50 px-2 py-1 rounded">
                "{{ s.evidence }}"
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 红黄牌 + 亮点 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <div v-if="parseJson(evaluation.red_flags).length" class="bg-red-50 rounded-xl p-4 border border-red-100">
          <div class="text-sm font-medium text-red-700 mb-2 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base">flag</span>
            红黄牌 ({{ parseJson(evaluation.red_flags).length }})
          </div>
          <ul class="space-y-1.5">
            <li v-for="(r, i) in parseJson(evaluation.red_flags)" :key="i" class="text-xs text-red-600">· {{ r }}</li>
          </ul>
        </div>
        <div v-if="parseJson(evaluation.highlights).length" class="bg-green-50 rounded-xl p-4 border border-green-100">
          <div class="text-sm font-medium text-green-700 mb-2 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base">star</span>
            亮点 ({{ parseJson(evaluation.highlights).length }})
          </div>
          <ul class="space-y-1.5">
            <li v-for="(h, i) in parseJson(evaluation.highlights)" :key="i" class="text-xs text-green-700">· {{ h }}</li>
          </ul>
        </div>
      </div>

      <!-- 完整对话 -->
      <div class="bg-white rounded-xl p-4 border border-slate-100">
        <div class="text-sm font-medium text-text-primary mb-3 flex items-center justify-between">
          <span class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base">forum</span>
            完整对话记录 ({{ parseJson(evaluation.conversation_messages || '[]').length }} 条)
          </span>
          <span class="text-xs text-text-secondary">用于 HR 回看与质询</span>
        </div>
        <div class="space-y-2 max-h-[500px] overflow-y-auto">
          <div v-for="(m, i) in parseJson(evaluation.conversation_messages || '[]')" :key="i"
            :class="['flex', m.role === 'user' ? 'justify-end' : 'justify-start']">
            <div :class="['max-w-[80%] rounded-lg px-3 py-2 text-xs leading-relaxed',
                          m.role === 'user'
                            ? 'bg-primary/10 text-text-primary rounded-tr-sm'
                            : 'bg-slate-50 text-text-primary rounded-tl-sm']">
              <div class="text-[10px] text-text-secondary mb-0.5">
                {{ m.role === 'user' ? '应聘者' : 'AI' }} · {{ formatTime(m.ts) }}
              </div>
              <div class="whitespace-pre-wrap">{{ m.content }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 元信息 -->
      <div class="mt-4 text-xs text-text-secondary text-center">
        候选人 {{ evaluation.candidate_name || '—' }}
        · 联系电话 {{ evaluation.candidate_phone || '未填写' }}
        · 到岗时间 {{ evaluation.available_at || '未提及' }}
        · 报告生成 {{ formatTime(evaluation.created_at) }}
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const route = useRoute()
const evaluation = ref(null)
const loading = ref(true)

function recLabel(r) {
  return { strong: '强烈推荐', consider: '可考虑', reject: '不推荐' }[r] || '—'
}

function recClassBg(r) {
  return {
    strong: 'bg-white text-green-700',
    consider: 'bg-white text-amber-700',
    reject: 'bg-white text-red-700'
  }[r] || 'bg-white/20 text-white'
}

function formatTime(t) {
  if (!t) return '—'
  const d = new Date(t)
  return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

function parseJson(v) {
  if (Array.isArray(v)) return v
  if (typeof v === 'string') {
    try { return JSON.parse(v) } catch { return [] }
  }
  if (v && typeof v === 'object') return [v]
  return []
}

onMounted(async () => {
  loading.value = true
  try {
    const res = await api.get(`/ai-hr/evaluations/${route.params.id}`)
    if (res.code === 0) {
      evaluation.value = res.data
    } else {
      alert(res.message || '加载失败')
    }
  } catch (e) {
    console.error(e)
    alert('加载失败: ' + (e.message || ''))
  } finally {
    loading.value = false
  }
})
</script>