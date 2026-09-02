<template>
  <!-- Agent Tokens Dialog — admin 给某用户开 API token (sbk_xxx) 给 agent 接入用 -->
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="close">
    <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-5 border-b border-gray-100">
        <div>
          <h3 class="text-lg font-bold text-text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">key</span>
            {{ $t('agentTokens.title') }}
          </h3>
          <p class="text-xs text-text-secondary mt-1">
            {{ $t('agentTokens.subtitle', { name: user?.name || '-', role: user?.role || '-' }) }}
          </p>
        </div>
        <button @click="close" class="text-text-secondary hover:text-text-primary">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- 新建表单区 -->
      <div class="p-5 border-b border-gray-100 bg-blue-50/30">
        <h4 class="text-sm font-bold text-text-primary mb-3">{{ $t('agentTokens.newToken') }}</h4>
        <div class="grid grid-cols-12 gap-3">
          <div class="col-span-12 md:col-span-5">
            <label class="block text-xs text-text-secondary mb-1">{{ $t('agentTokens.name') }} *</label>
            <input v-model="newForm.name" type="text" :placeholder="$t('agentTokens.namePlaceholder')"
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary" />
          </div>
          <div class="col-span-6 md:col-span-3">
            <label class="block text-xs text-text-secondary mb-1">{{ $t('agentTokens.expiresIn') }}</label>
            <select v-model="newForm.expires_in_days"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary bg-white">
              <option :value="null">{{ $t('agentTokens.neverExpire') }}</option>
              <option :value="30">{{ $t('agentTokens.days30') }}</option>
              <option :value="90">{{ $t('agentTokens.days90') }}</option>
              <option :value="180">{{ $t('agentTokens.days180') }}</option>
              <option :value="365">{{ $t('agentTokens.days365') }}</option>
            </select>
          </div>
          <div class="col-span-6 md:col-span-2">
            <label class="block text-xs text-text-secondary mb-1">{{ $t('agentTokens.scopesMode') }}</label>
            <select v-model="newForm.scopesMode"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary bg-white">
              <option value="all">{{ $t('agentTokens.scopesAll') }}</option>
              <option value="custom">{{ $t('agentTokens.scopesCustom') }}</option>
            </select>
          </div>
          <div class="col-span-12 md:col-span-2 flex items-end">
            <button @click="createToken" :disabled="!newForm.name.trim() || creating"
                    class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
              <span class="material-symbols-outlined text-[18px]">add</span>
              {{ creating ? $t('common.loading') : $t('agentTokens.create') }}
            </button>
          </div>
          <!-- 自定义 scopes 输入框 -->
          <div v-if="newForm.scopesMode === 'custom'" class="col-span-12">
            <label class="block text-xs text-text-secondary mb-1">{{ $t('agentTokens.scopesHint') }}</label>
            <textarea v-model="newForm.scopesText" rows="2"
                      :placeholder="$t('agentTokens.scopesPlaceholder')"
                      class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs font-mono focus:outline-none focus:border-primary"></textarea>
          </div>
        </div>
      </div>

      <!-- 一次性明文展示 (创建后) -->
      <div v-if="revealedToken" class="p-5 border-b border-yellow-200 bg-yellow-50">
        <div class="flex items-start gap-3">
          <span class="material-symbols-outlined text-yellow-600">warning</span>
          <div class="flex-1">
            <p class="text-sm font-bold text-yellow-900 mb-2">{{ $t('agentTokens.copyNowWarning') }}</p>
            <div class="flex items-stretch gap-2">
              <input :value="revealedToken.token" readonly
                     class="flex-1 px-3 py-2 bg-white border border-yellow-300 rounded-lg font-mono text-xs select-all" />
              <button @click="copyToClipboard(revealedToken.token, 'token')"
                      class="flex items-center gap-1 bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <span class="material-symbols-outlined text-[18px]">content_copy</span>
                {{ copyStatus.token || $t('common.copy') }}
              </button>
            </div>
            <details class="mt-3">
              <summary class="text-xs text-yellow-800 cursor-pointer hover:underline">{{ $t('agentTokens.curlExample') }}</summary>
              <pre class="mt-2 p-3 bg-white border border-yellow-200 rounded text-xs font-mono overflow-x-auto whitespace-pre-wrap break-all">{{ curlExample(revealedToken.token) }}</pre>
            </details>
          </div>
        </div>
      </div>

      <!-- 已开 token 列表 -->
      <div class="flex-1 overflow-y-auto p-5">
        <h4 class="text-sm font-bold text-text-primary mb-3">
          {{ $t('agentTokens.existingTokens') }}
          <span class="text-xs font-normal text-text-secondary ml-2">({{ tokens.length }})</span>
        </h4>
        <div v-if="loading" class="text-center py-8 text-text-secondary text-sm">
          <span class="material-symbols-outlined animate-spin">progress_activity</span>
          {{ $t('common.loading') }}
        </div>
        <div v-else-if="!tokens.length" class="text-center py-8 text-text-secondary text-sm">
          {{ $t('agentTokens.noTokens') }}
        </div>
        <table v-else class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-3 py-2 font-medium">{{ $t('agentTokens.colName') }}</th>
              <th class="px-3 py-2 font-medium">{{ $t('agentTokens.colPrefix') }}</th>
              <th class="px-3 py-2 font-medium">{{ $t('agentTokens.colScopes') }}</th>
              <th class="px-3 py-2 font-medium">{{ $t('agentTokens.colStatus') }}</th>
              <th class="px-3 py-2 font-medium">{{ $t('agentTokens.colUsage') }}</th>
              <th class="px-3 py-2 font-medium">{{ $t('agentTokens.colCreated') }}</th>
              <th class="px-3 py-2 font-medium text-right">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="t in tokens" :key="t.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 font-medium">{{ t.name }}</td>
              <td class="px-3 py-2 font-mono text-xs text-text-secondary">{{ t.token_prefix }}</td>
              <td class="px-3 py-2 text-xs">
                <span v-if="!t.scopes || t.scopes.length === 0" class="text-green-600">{{ $t('agentTokens.scopeAll') }}</span>
                <span v-else class="text-orange-600">{{ t.scopes.length }} {{ $t('agentTokens.scopeRestricted') }}</span>
              </td>
              <td class="px-3 py-2">
                <StatusTag :type="statusColor(t.status)" :text="$t('agentTokens.status_' + t.status)" />
              </td>
              <td class="px-3 py-2 text-xs text-text-secondary">
                {{ t.use_count }}{{ t.last_used_at ? ` (${formatDate(t.last_used_at)})` : '' }}
              </td>
              <td class="px-3 py-2 text-xs text-text-secondary">{{ formatDate(t.created_at) }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <button v-if="t.status === 'active'" @click="rotate(t)" :title="$t('agentTokens.rotate')"
                        class="text-primary hover:text-primary-hover text-xs font-medium mr-2">
                  {{ $t('agentTokens.rotate') }}
                </button>
                <button v-if="t.status === 'active'" @click="revoke(t)" :title="$t('agentTokens.revoke')"
                        class="text-danger hover:text-red-700 text-xs font-medium">
                  {{ $t('agentTokens.revoke') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api.js'
import StatusTag from './StatusTag.vue'

const { t } = useI18n()

const props = defineProps({
  show: { type: Boolean, default: false },
  user: { type: Object, default: null }  // { id, name, role }
})
const emit = defineEmits(['close', 'created'])

const tokens = ref([])
const loading = ref(false)
const creating = ref(false)
const revealedToken = ref(null)  // 创建/rotate 后的一次性明文
const copyStatus = ref({})

const newForm = ref({
  name: '',
  expires_in_days: null,
  scopesMode: 'all',
  scopesText: ''  // 一行一个 scope
})

watch(() => props.show, async (val) => {
  if (val && props.user) {
    newForm.value = { name: '', expires_in_days: null, scopesMode: 'all', scopesText: '' }
    revealedToken.value = null
    await loadTokens()
  }
})

async function loadTokens() {
  if (!props.user?.id) return
  loading.value = true
  try {
    const res = await api.get(`/agent-access/tokens?user_id=${props.user.id}`)
    if (res.code === 0) tokens.value = res.data
    else tokens.value = []
  } catch (e) {
    console.error('loadTokens error:', e)
    tokens.value = []
  } finally {
    loading.value = false
  }
}

async function createToken() {
  if (!props.user?.id || !newForm.value.name.trim()) return
  creating.value = true
  try {
    const body = {
      user_id: props.user.id,
      name: newForm.value.name.trim(),
      expires_in_days: newForm.value.expires_in_days
    }
    if (newForm.value.scopesMode === 'custom' && newForm.value.scopesText.trim()) {
      body.scopes = newForm.value.scopesText.split(/[\n,]+/).map(s => s.trim()).filter(Boolean)
    }
    const res = await api.post('/agent-access/tokens', body)
    if (res.code === 0) {
      revealedToken.value = res.data
      newForm.value.name = ''
      newForm.value.scopesText = ''
      await loadTokens()
      emit('created', res.data)
    } else {
      alert(res.message || t('agentTokens.createFailed'))
    }
  } catch (e) {
    alert(e.message || t('agentTokens.createFailed'))
  } finally {
    creating.value = false
  }
}

async function rotate(t) {
  if (!confirm(t('agentTokens.confirmRotate', { name: t.name }))) return
  try {
    const res = await api.post(`/agent-access/tokens/${t.id}/rotate`)
    if (res.code === 0) {
      revealedToken.value = res.data
      await loadTokens()
    } else {
      alert(res.message)
    }
  } catch (e) { alert(e.message) }
}

async function revoke(t) {
  if (!confirm(t('agentTokens.confirmRevoke', { name: t.name }))) return
  try {
    const res = await api.delete(`/agent-access/tokens/${t.id}`)
    if (res.code === 0) {
      await loadTokens()
    } else {
      alert(res.message)
    }
  } catch (e) { alert(e.message) }
}

function copyToClipboard(text, key) {
  navigator.clipboard.writeText(text).then(() => {
    copyStatus.value[key] = t('common.copied')
    setTimeout(() => { copyStatus.value[key] = '' }, 2000)
  }).catch(() => {
    // fallback
    const ta = document.createElement('textarea')
    ta.value = text
    document.body.appendChild(ta)
    ta.select()
    try { document.execCommand('copy') } catch {}
    document.body.removeChild(ta)
    copyStatus.value[key] = t('common.copied')
    setTimeout(() => { copyStatus.value[key] = '' }, 2000)
  })
}

function curlExample(token) {
  return `curl -H "Authorization: Bearer ${token}" \\
  https://wecom.gdqshop.cn/api/workbuddy/stats

# 或用 Python:
# import requests
# r = requests.get('https://wecom.gdqshop.cn/api/workbuddy/stats',
#                  headers={'Authorization': 'Bearer ${token}'})`
}

function statusColor(status) {
  return { active: 'success', revoked: 'danger', expired: 'info' }[status] || 'info'
}

function formatDate(s) {
  if (!s) return '-'
  return new Date(s).toLocaleString('zh-CN', { hour12: false }).slice(0, 16)
}

function close() {
  revealedToken.value = null
  emit('close')
}
</script>
