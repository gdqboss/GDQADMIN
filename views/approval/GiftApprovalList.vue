<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t } = useI18n()

const activeTab = ref('pending')
const records = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 20

// 弹窗
const showRejectModal = ref(false)
const showResolveModal = ref(false)
const actionTarget = ref(null)
const actionComment = ref('')
const actionLoading = ref(false)

const tabs = computed(() => [
  { key: 'pending', label: t('giftApproval.tabPending') },
  { key: 'approved', label: t('giftApproval.tabApproved') },
  { key: 'rejected', label: t('giftApproval.tabRejected') },
  { key: 'my-requests', label: t('giftApproval.tabMyRequests') },
])

async function fetchRecords() {
  loading.value = true
  try {
    let url, params
    if (activeTab.value === 'my-requests') {
      url = '/gift-approvals/my-requests'
      params = { page: currentPage.value, size: pageSize }
    } else {
      url = '/gift-approvals/pending'
      params = { page: currentPage.value, size: pageSize, status: activeTab.value }
    }
    const res = await api.get(url, { params })
    if (res.code === 0) {
      records.value = res.data.list
      total.value = res.data.total
    }
  } finally { loading.value = false }
}

watch(activeTab, () => { currentPage.value = 1; fetchRecords() })
watch(currentPage, fetchRecords)
onMounted(fetchRecords)

function formatTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function statusType(s) {
  if (s === 'pending') return 'warning'
  if (s === 'approved') return 'success'
  if (s === 'rejected') return 'danger'
  if (s === 'resolved') return 'info'
  return 'default'
}

function statusText(s) {
  const map = { pending: t('giftApproval.statusPending'), approved: t('giftApproval.statusApproved'), rejected: t('giftApproval.statusRejected'), resolved: t('giftApproval.statusResolved') }
  return map[s] || s
}

// 批准
async function handleApprove(row) {
  if (!confirm(t('giftApproval.confirmApprove'))) return
  try {
    const res = await api.put(`/gift-approvals/${row.id}/approve`, {})
    if (res.code === 0) fetchRecords()
    else alert(res.message)
  } catch (e) { alert(e.message) }
}

// 拒绝弹窗
function openReject(row) {
  actionTarget.value = row
  actionComment.value = ''
  showRejectModal.value = true
}

async function confirmReject() {
  if (!actionComment.value.trim()) return alert(t('giftApproval.rejectReasonRequired'))
  actionLoading.value = true
  try {
    const res = await api.put(`/gift-approvals/${actionTarget.value.id}/reject`, { comment: actionComment.value.trim() })
    if (res.code === 0) {
      showRejectModal.value = false
      fetchRecords()
    } else alert(res.message)
  } catch (e) { alert(e.message) }
  finally { actionLoading.value = false }
}

// 解决弹窗
function openResolve(row) {
  actionTarget.value = row
  actionComment.value = ''
  showResolveModal.value = true
}

async function confirmResolve() {
  actionLoading.value = true
  try {
    const res = await api.put(`/gift-approvals/${actionTarget.value.id}/resolve`, { comment: actionComment.value.trim() })
    if (res.code === 0) {
      showResolveModal.value = false
      fetchRecords()
    } else alert(res.message)
  } catch (e) { alert(e.message) }
  finally { actionLoading.value = false }
}
</script>

<template>
  <div>
    <PageHeader :title="t('giftApproval.title')" :subtitle="t('giftApproval.subtitle')" />

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 bg-gray-100 rounded-lg p-1 w-fit">
      <button
        v-for="tab in tabs" :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'px-4 py-2 rounded-md text-sm font-medium transition-all',
          activeTab === tab.key ? 'bg-white text-primary shadow-sm' : 'text-text-secondary hover:text-text-primary'
        ]"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ t('giftApproval.time') }}</th>
              <th class="px-4 py-3 font-medium">{{ t('giftApproval.product') }}</th>
              <th class="px-4 py-3 font-medium">{{ t('giftApproval.qrCode') }}</th>
              <th class="px-4 py-3 font-medium">{{ activeTab === 'my-requests' ? t('giftApproval.approver') : t('giftApproval.applicant') }}</th>
              <th class="px-4 py-3 font-medium">{{ t('giftApproval.customer') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ t('giftApproval.status') }}</th>
              <th class="px-4 py-3 font-medium">{{ t('giftApproval.comment') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ t('giftApproval.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ t('common.loading') }}...</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ t('common.noData') }}</td>
            </tr>
            <tr v-for="r in records" :key="r.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">{{ formatTime(r.created_at) }}</td>
              <td class="px-4 py-3">
                <p class="font-medium text-text-primary">{{ r.product_name || '-' }}</p>
                <p v-if="r.product_spec" class="text-xs text-text-secondary">{{ r.product_spec }}</p>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-primary">{{ r.qr_code || '-' }}</td>
              <td class="px-4 py-3 text-sm text-text-secondary">
                <template v-if="activeTab === 'my-requests'">{{ r.approver_name || '-' }}</template>
                <template v-else>{{ r.requested_by_name || '-' }}</template>
              </td>
              <td class="px-4 py-3 text-sm text-text-secondary">
                <span v-if="r.buyer_name">{{ r.buyer_name }}</span>
                <span v-if="r.buyer_phone" class="text-xs block">{{ r.buyer_phone }}</span>
                <span v-if="!r.buyer_name && !r.buyer_phone">-</span>
              </td>
              <td class="px-4 py-3 text-center">
                <StatusTag :type="statusType(r.status)" :text="statusText(r.status)" />
              </td>
              <td class="px-4 py-3 text-xs text-text-secondary max-w-[150px] truncate" :title="r.comment">{{ r.comment || r.retail_note || '-' }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button v-if="r.status === 'pending' && activeTab !== 'my-requests'"
                    @click="handleApprove(r)"
                    class="text-xs px-2.5 py-1 bg-green-500 text-white rounded hover:bg-green-600 transition-colors"
                  >{{ t('giftApproval.approve') }}</button>
                  <button v-if="r.status === 'pending' && activeTab !== 'my-requests'"
                    @click="openReject(r)"
                    class="text-xs px-2.5 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors"
                  >{{ t('giftApproval.reject') }}</button>
                  <button v-if="r.status === 'rejected' && activeTab !== 'my-requests'"
                    @click="openResolve(r)"
                    class="text-xs px-2.5 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
                  >{{ t('giftApproval.resolve') }}</button>
                  <span v-if="activeTab === 'my-requests' || (r.status !== 'pending' && r.status !== 'rejected')" class="text-xs text-text-secondary">-</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100">
        <Pagination :total="total" :page="currentPage" :pageSize="pageSize" @update:page="currentPage = $event" />
      </div>
    </div>

    <!-- 拒绝弹窗 -->
    <div v-if="showRejectModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showRejectModal = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-lg font-bold text-text-primary mb-4">{{ t('giftApproval.rejectTitle') }}</h3>
        <textarea v-model="actionComment" :placeholder="t('giftApproval.rejectReasonPlaceholder')" rows="3"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none mb-4"
        ></textarea>
        <div class="flex justify-end gap-3">
          <button @click="showRejectModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">{{ t('common.cancel') }}</button>
          <button @click="confirmReject" :disabled="actionLoading" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600 disabled:opacity-50">
            {{ actionLoading ? t('common.loading') + '...' : t('giftApproval.confirmReject') }}
          </button>
        </div>
      </div>
    </div>

    <!-- 解决弹窗 -->
    <div v-if="showResolveModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showResolveModal = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-lg font-bold text-text-primary mb-4">{{ t('giftApproval.resolveTitle') }}</h3>
        <p class="text-sm text-text-secondary mb-3">{{ t('giftApproval.resolveDesc') }}</p>
        <textarea v-model="actionComment" :placeholder="t('giftApproval.resolveCommentPlaceholder')" rows="2"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none mb-4"
        ></textarea>
        <div class="flex justify-end gap-3">
          <button @click="showResolveModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">{{ t('common.cancel') }}</button>
          <button @click="confirmResolve" :disabled="actionLoading" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600 disabled:opacity-50">
            {{ actionLoading ? t('common.loading') + '...' : t('giftApproval.confirmResolve') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
