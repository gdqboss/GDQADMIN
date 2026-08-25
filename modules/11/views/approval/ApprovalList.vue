<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'

const { t } = useI18n()
const router = useRouter()
const userStore = useUserStore()
const activeTab = ref('pending')
const approvals = ref([])

// ─── Delete State ──────────────────────────────────────────────────────────────
const showDeleteDialog = ref(false)
const deletingId = ref(null)
const deleting = ref(false)

const isAdmin = computed(() => userStore.user?.role === 'admin')

const confirmDelete = (id) => {
  deletingId.value = id
  showDeleteDialog.value = true
}

const doDelete = async () => {
  if (!deletingId.value) return
  deleting.value = true
  try {
    const res = await api.delete(`/approvals/${deletingId.value}`)
    if (res.code === 0) {
      approvals.value = approvals.value.filter(a => a.id !== deletingId.value)
      showDeleteDialog.value = false
      deletingId.value = null
    }
  } catch (e) {
    console.error('Delete approval failed:', e)
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  try {
    const res = await api.get('/approvals')
    approvals.value = res.data.list || []
  } catch { approvals.value = [] }
})

const tabs = computed(() => [
  { key: 'pending', label: t('approval.pendingMine') },
  { key: 'mine', label: t('approval.myInitiated') },
  { key: 'all', label: t('approval.all') },
])

const filteredApprovals = computed(() => {
  if (activeTab.value === 'pending') return approvals.value.filter(a => a.status === 'pending')
  if (activeTab.value === 'mine') return approvals.value.filter(a => String(a.applicant_id) === String(userStore.userId))
  return approvals.value
})

const statusMap = computed(() => ({
  pending: { type: 'warning', text: t('approval.pending') },
  approved: { type: 'success', text: t('approval.passed') },
  rejected: { type: 'danger', text: t('approval.rejected') },
}))

const typeIconMap = {
  '采购审批': 'shopping_cart',
  '出库审批': 'local_shipping',
  '调拨审批': 'swap_horiz',
  '盘点审批': 'assignment',
}
</script>

<template>
  <div>
    <PageHeader :title="$t('approval.title')" :subtitle="$t('approval.subtitle')" />

    <!-- Link to unified approval center -->
    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
      <span class="text-sm text-blue-700">{{ $t('approval.goToOaApproval') }}</span>
      <router-link to="/oa/approvals" class="text-sm text-primary font-medium hover:underline">
        {{ $t('approval.goToOaApprovalBtn') }} →
      </router-link>
    </div>

    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <!-- Tabs -->
      <div class="flex border-b border-gray-100">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
            activeTab === tab.key
              ? 'border-primary text-primary'
              : 'border-transparent text-text-secondary hover:text-text-primary'
          ]"
        >{{ tab.label }}</button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('approval.approvalNo') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('approval.type') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('approval.approvalTitle') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('approval.applicant') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('inout.date') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('approval.amount') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="ap in filteredApprovals" :key="ap.id" class="hover:bg-gray-50 transition-colors cursor-pointer" @click="router.push(`/approvals/${ap.id}`)">
              <td class="px-4 py-3 font-mono text-xs text-primary font-medium">{{ ap.id }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined text-[16px] text-text-secondary">{{ typeIconMap[ap.type] || 'description' }}</span>
                  <span class="text-text-secondary text-xs">{{ ap.type }}</span>
                </div>
              </td>
              <td class="px-4 py-3 font-medium text-text-primary max-w-[300px] truncate">{{ ap.title }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ ap.applicant }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ ap.date }}</td>
              <td class="px-4 py-3 text-right text-text-primary">{{ ap.amount ? '¥' + ap.amount.toLocaleString() : '-' }}</td>
              <td class="px-4 py-3 text-center">
                <StatusTag :type="statusMap[ap.status]?.type || 'info'" :text="statusMap[ap.status]?.text || ap.status" />
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('approval.view') }}</button>
                  <button v-if="isAdmin" @click.stop="confirmDelete(ap.id)"
                    class="text-danger hover:text-red-700 text-xs font-medium">删除</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100 text-sm text-text-secondary">
        {{ filteredApprovals.length }} {{ $t('common.records') }}
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <div v-if="showDeleteDialog"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="showDeleteDialog = false">
      <div class="w-full max-w-sm bg-white rounded-xl shadow-2xl p-6">
        <h3 class="text-lg font-bold text-text-primary mb-2">确认删除</h3>
        <p class="text-sm text-text-secondary mb-6">确定要删除这条审批记录吗？此操作不可撤销。</p>
        <div class="flex gap-3 justify-end">
          <button @click="showDeleteDialog = false"
            class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50">取消</button>
          <button @click="doDelete" :disabled="deleting"
            class="px-4 py-2 bg-danger text-white rounded-lg text-sm font-medium hover:bg-red-600 disabled:opacity-50">
            {{ deleting ? '删除中...' : '确认删除' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* 表格横向滚动 */
  .overflow-x-auto {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  /* 搜索表单适配 */
  .mb-4 {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  /* 内边距调整 */
  .bg-white.rounded-lg {
    border-radius: 8px;
  }

  /* Tabs 横向滚动 */
  .flex.border-b {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
  }
  .flex.border-b::-webkit-scrollbar {
    display: none;
  }

  /* 表格单元格调整 */
  table.w-full.text-left.text-sm th,
  table.w-full.text-left.text-sm td {
    padding: 8px 6px;
    font-size: 12px;
  }

  /* 审批编号缩小 */
  td .font-mono {
    font-size: 10px;
  }

  /* 操作按钮 */
  button.text-primary {
    padding: 4px 8px;
    font-size: 11px;
  }
}
</style>
