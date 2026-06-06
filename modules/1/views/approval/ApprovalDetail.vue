<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const approval = ref(null)
const comment = ref('')

onMounted(async () => {
  const res = await api.get('/approvals/' + route.params.id)
  approval.value = res.data
})

async function handleApprove() {
  await api.put('/approvals/' + route.params.id + '/approve', { comment: comment.value })
  const res = await api.get('/approvals/' + route.params.id)
  approval.value = res.data
}

async function handleReject() {
  await api.put('/approvals/' + route.params.id + '/reject', { comment: comment.value })
  const res = await api.get('/approvals/' + route.params.id)
  approval.value = res.data
}

const statusMap = computed(() => ({
  pending: { type: 'warning', text: t('approval.pending') },
  approved: { type: 'success', text: t('approval.passed') },
  rejected: { type: 'danger', text: t('approval.rejected') },
}))
</script>

<template>
  <div v-if="approval">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
      <button @click="router.push('/approvals')" class="p-2 hover:bg-gray-100 rounded-lg transition-colors text-text-secondary">
        <span class="material-symbols-outlined">arrow_back</span>
      </button>
      <div class="flex-1">
        <h2 class="text-xl font-bold text-text-primary">{{ approval.title }}</h2>
        <p class="text-sm text-text-secondary">{{ approval.id }} · {{ approval.type }}</p>
      </div>
      <StatusTag :type="statusMap[approval.status]?.type" :text="statusMap[approval.status]?.text" />
    </div>

    <!-- Steps -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-6 mb-6">
      <h3 class="font-bold text-text-primary mb-4">{{ $t('approval.approvalFlow') }}</h3>
      <div class="flex items-center">
        <template v-for="(step, i) in approval.steps" :key="i">
          <div class="flex flex-col items-center">
            <div
              :class="[
                'size-10 rounded-full flex items-center justify-center text-sm font-bold border-2',
                i < approval.current_step
                  ? 'bg-success border-success text-white'
                  : i === approval.current_step && approval.status === 'rejected'
                  ? 'bg-danger border-danger text-white'
                  : i === approval.current_step
                  ? 'bg-primary border-primary text-white'
                  : 'bg-gray-100 border-gray-200 text-text-secondary'
              ]"
            >
              <span v-if="i < approval.current_step" class="material-symbols-outlined text-[18px]">check</span>
              <span v-else-if="i === approval.current_step && approval.status === 'rejected'" class="material-symbols-outlined text-[18px]">close</span>
              <span v-else>{{ i + 1 }}</span>
            </div>
            <span class="text-xs mt-2 text-text-secondary text-center max-w-[80px]">{{ step }}</span>
          </div>
          <div
            v-if="i < approval.steps.length - 1"
            :class="['flex-1 h-0.5 mx-2 mb-6', i < approval.current_step ? 'bg-success' : 'bg-gray-200']"
          ></div>
        </template>
      </div>
    </div>

    <!-- Details -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-6">
        <h3 class="font-bold text-text-primary mb-4">{{ $t('approval.applicationInfo') }}</h3>
        <div class="space-y-3">
          <div class="flex justify-between py-2 border-b border-gray-50">
            <span class="text-sm text-text-secondary">{{ $t('approval.applicant') }}</span>
            <span class="text-sm font-medium text-text-primary">{{ approval.applicant }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-50">
            <span class="text-sm text-text-secondary">{{ $t('approval.department') }}</span>
            <span class="text-sm font-medium text-text-primary">{{ approval.department }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-50">
            <span class="text-sm text-text-secondary">{{ $t('approval.applicationDate') }}</span>
            <span class="text-sm font-medium text-text-primary">{{ approval.date }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-50">
            <span class="text-sm text-text-secondary">{{ $t('approval.type') }}</span>
            <span class="text-sm font-medium text-text-primary">{{ approval.type }}</span>
          </div>
          <div class="flex justify-between py-2">
            <span class="text-sm text-text-secondary">{{ $t('approval.amount') }}</span>
            <span class="text-sm font-bold text-text-primary">{{ approval.amount ? '¥' + approval.amount.toLocaleString() : '-' }}</span>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-6">
        <h3 class="font-bold text-text-primary mb-4">{{ $t('approval.approvalRecords') }}</h3>
        <div class="space-y-4">
          <div class="flex gap-3">
            <div class="size-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-primary text-[16px]">person</span>
            </div>
            <div>
              <p class="text-sm font-medium text-text-primary">{{ approval.applicant }} {{ $t('approval.submittedApplication') }}</p>
              <p class="text-xs text-text-secondary">{{ approval.date }} 09:00</p>
            </div>
          </div>
          <div v-if="approval.current_step >= 2" class="flex gap-3">
            <div class="size-8 rounded-full bg-success/10 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-success text-[16px]">check</span>
            </div>
            <div>
              <p class="text-sm font-medium text-text-primary">{{ $t('approval.purchaseManagerApproved') }}</p>
              <p class="text-xs text-text-secondary">{{ approval.date }} 14:30</p>
              <p class="text-xs text-text-secondary mt-1">{{ $t('approval.commentPriceReasonable') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div v-if="approval.status === 'pending'" class="bg-white rounded-lg border border-gray-100 shadow-card p-6">
      <h3 class="font-bold text-text-primary mb-4">{{ $t('approval.approvalAction') }}</h3>
      <div>
        <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('approval.approvalOpinion') }}</label>
        <textarea v-model="comment" rows="3" :placeholder="$t('approval.opinionPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none mb-4"></textarea>
      </div>
      <div class="flex gap-3">
        <button @click="handleApprove" class="flex items-center gap-2 bg-success hover:bg-green-600 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors">
          <span class="material-symbols-outlined text-[18px]">check</span>
          {{ $t('approval.approve') }}
        </button>
        <button @click="handleReject" class="flex items-center gap-2 bg-danger hover:bg-red-600 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors">
          <span class="material-symbols-outlined text-[18px]">close</span>
          {{ $t('approval.reject') }}
        </button>
        <button class="flex items-center gap-2 border border-gray-200 text-text-primary hover:bg-gray-50 px-6 py-2 rounded-lg text-sm font-medium transition-colors">
          <span class="material-symbols-outlined text-[18px]">send</span>
          {{ $t('approval.transfer') }}
        </button>
      </div>
    </div>
  </div>
  <div v-else class="text-center py-20 text-text-secondary">{{ $t('approval.notFound') }}</div>
</template>

<style scoped>
/* 手机端适配 */
@media (max-width: 768px) {
  /* Header */
  .flex.items-center.gap-3.mb-6 {
    flex-wrap: wrap;
  }
  .flex.items-center.gap-3.mb-6 .text-xl {
    font-size: 16px;
  }
  .flex.items-center.gap-3.mb-6 .text-sm {
    font-size: 12px;
  }

  /* Steps 区域 */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-6:has(.flex.items-center) {
    padding: 16px;
    overflow-x: auto;
  }
  .size-10 {
    width: 32px;
    height: 32px;
    font-size: 12px;
  }
  .text-xs.mt-2 {
    font-size: 10px;
    max-width: 60px;
  }

  /* Details 表格 */
  .grid.grid-cols-1.lg\:grid-cols-2 {
    grid-template-columns: 1fr;
  }
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-6 {
    padding: 16px;
  }
  .space-y-3 .flex {
    flex-direction: column;
    gap: 4px;
  }

  /* 操作按钮区 */
  .flex.gap-3 {
    flex-direction: column;
  }
  .flex.gap-3 button {
    width: 100%;
    justify-content: center;
  }

  /* textarea */
  textarea {
    font-size: 14px;
  }
}
</style>
