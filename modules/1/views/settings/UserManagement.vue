<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">{{ $t('nav.userManagement') }}</h1>
      <p class="text-gray-600 mt-1">{{ $t('settings.subtitle') }}</p>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow">
      <div class="border-b">
        <nav class="flex">
          <button
            @click="activeTab = 'internal'"
            :class="[
              'px-6 py-3 font-medium transition',
              activeTab === 'internal' ? 'border-b-2 border-primary text-primary' : 'text-gray-600 hover:text-gray-800'
            ]"
          >
            {{ t('settings.userList') }}
          </button>
          <button
            @click="activeTab = 'external'"
            :class="[
              'px-6 py-3 font-medium transition',
              activeTab === 'external' ? 'border-b-2 border-primary text-primary' : 'text-gray-600 hover:text-gray-800'
            ]"
          >
            {{ t('nav.h5Users') }}
          </button>
        </nav>
      </div>

      <!-- Internal Users (System Users) -->
      <div v-if="activeTab === 'internal'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <input
            v-model="searchInternal"
            type="text"
            :placeholder="t('common.search')"
            class="px-4 py-2 border rounded-lg w-64"
          />
          <button @click="loadInternalUsers" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">
            {{ t('common.search') }}
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.name') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.email') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('settings.role') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('oa.employeeCode') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('identityQR.identityCode') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.status') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="user in internalUsers" :key="user.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm">{{ user.name }}</td>
                <td class="px-4 py-3 text-sm">{{ user.email }}</td>
                <td class="px-4 py-3 text-sm">{{ roleLabel(user.role) }}</td>
                <td class="px-4 py-3 text-sm">{{ user.employee_code || '-' }}</td>
                <td class="px-4 py-3 text-sm">
                  <span v-if="user.identity_code" class="text-xs font-mono bg-gray-100 px-2 py-1 rounded">
                    {{ user.identity_code.substring(0, 12) }}...
                  </span>
                  <span v-else class="text-gray-400">{{ t('common.notSet') }}</span>
                </td>
                <td class="px-4 py-3 text-sm">
                  <span :class="user.status === 'active' ? 'text-green-600' : 'text-red-600'">
                    {{ user.status === 'active' ? t('common.active') : t('common.inactive') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm">
                  <button
                    v-if="!user.identity_code"
                    @click="generateIdentityCode(user.id, 'system')"
                    class="text-primary hover:underline mr-2"
                  >
                    {{ t('identityQR.generateCode') }}
                  </button>
                  <button
                    v-if="user.identity_code"
                    @click="viewIdentityQR(user)"
                    class="text-blue-600 hover:underline mr-2"
                  >
                    {{ t('common.view') }}
                  </button>
                  <button
                    v-if="user.identity_code"
                    @click="deleteIdentityCode(user.id, 'system')"
                    class="text-red-600 hover:underline mr-2"
                  >
                    {{ t('common.delete') }}
                  </button>
                  <!-- 2026-08-29 WorkBuddy APP 连接链接 — 用户用自己密码生成的 token 自动对齐权限 -->
                  <button
                    @click="openWorkBuddyModal(user)"
                    class="text-purple-600 hover:underline"
                    :title="t('workbuddy.copyLinkHelp')"
                  >
                    🔗 {{ t('workbuddy.copyLink') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- External Users (H5 Users) -->
      <div v-if="activeTab === 'external'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <input
            v-model="searchExternal"
            type="text"
            :placeholder="t('common.search')"
            class="px-4 py-2 border rounded-lg w-64"
          />
          <button @click="loadExternalUsers" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">
            {{ t('common.search') }}
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.phone') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('settings.roleCol') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('settings.levelCol') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('settings.storeCol') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('settings.isInternal') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="user in externalUsers" :key="user.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm">{{ user.phone }}</td>
                <td class="px-4 py-3 text-sm">{{ roleLabel(user.role) || 'customer' }}</td>
                <td class="px-4 py-3 text-sm">{{ user.level || 1 }}</td>
                <td class="px-4 py-3 text-sm">{{ user.parent_phone || '-' }}</td>
                <td class="px-4 py-3 text-sm">
                  <span :class="user.is_internal ? 'text-green-600' : 'text-gray-400'">
                    {{ user.is_internal ? t('common.yes') : t('common.no') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm">
                  <button
                    v-if="user.is_internal && !user.identity_code"
                    @click="generateIdentityCode(user.id, 'h5')"
                    class="text-primary hover:underline mr-2"
                  >
                    {{ t('identityQR.generateCode') }}
                  </button>
                  <button
                    @click="editH5User(user)"
                    class="text-blue-600 hover:underline"
                  >
                    {{ t('common.edit') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Identity QR Modal -->
    <div v-if="showQRModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showQRModal = false">
      <div class="bg-white rounded-lg p-6 max-w-md" @click.stop>
        <h3 class="text-lg font-semibold mb-4">{{ t('oa.identityQR') }}</h3>
        <div class="text-center">
          <img v-if="currentQRPath" :src="currentQRPath" alt="Identity QR Code" class="mx-auto mb-4" />
          <p class="text-sm text-gray-600 mb-4">{{ currentIdentityCode }}</p>
          <div class="flex gap-2 justify-center">
            <a :href="currentQRPath" :download="`identity-${currentIdentityCode}.png`" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">
              {{ t('oa.downloadQR') }}
            </a>
            <button @click="showQRModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
              {{ t('common.close') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 2026-08-29 v2 WorkBuddy APP 连接 — 完整说明书 (API 地址 + token + 使用步骤 + 多种复制格式) -->
    <div v-if="showWBModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showWBModal = false">
      <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto" @click.stop>
        <h3 class="text-lg font-semibold mb-2">🔗 WorkBuddy APP 连接包</h3>
        <p class="text-sm text-gray-600 mb-4">
          用户 <span class="font-bold">{{ wbTargetUser?.name }}</span>
          <span class="text-gray-500">({{ wbTargetUser?.phone || wbTargetUser?.email }}, {{ wbTokenInfo?.role || wbTargetUser?.role }})</span>
        </p>

        <!-- ========== Step 1: 输入密码 (尚未生成时) ========== -->
        <div v-if="!wbToken" class="space-y-3 border-t pt-4">
          <p class="text-sm text-gray-700">
            <span class="font-semibold text-purple-700">第一步</span> — 输入该用户的登录密码生成连接包。
            不知道密码 = 拿不到 token (安全设计, 防止越权代生)。
          </p>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">登录账号 (手机/邮箱)</label>
            <input
              v-model="wbLoginKey"
              type="text"
              :placeholder="wbTargetUser?.phone || wbTargetUser?.email"
              class="w-full px-3 py-2 border rounded-lg"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">登录密码</label>
            <input
              v-model="wbPassword"
              type="password"
              placeholder="••••••••"
              class="w-full px-3 py-2 border rounded-lg"
              @keyup.enter="generateWorkBuddyLink"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">有效期 (天, 1-3650)</label>
            <input
              v-model.number="wbExpiresDays"
              type="number"
              min="1"
              max="3650"
              class="w-full px-3 py-2 border rounded-lg"
            />
            <p class="text-xs text-gray-500 mt-1">
              默认 <b>365 天 (长期)</b>. 除非管理员禁用该用户或主动撤销, 否则一直有效。
              最大可设 3650 天 (10 年)。
            </p>
          </div>
          <div v-if="wbError" class="text-sm text-red-600 bg-red-50 p-2 rounded">{{ wbError }}</div>
          <div class="flex gap-2 justify-end">
            <button @click="showWBModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
              {{ t('common.cancel') }}
            </button>
            <button
              @click="generateWorkBuddyLink"
              :disabled="wbLoading || !wbPassword"
              class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50"
            >
              {{ wbLoading ? '生成中...' : '🔐 生成连接包' }}
            </button>
          </div>
        </div>

        <!-- ========== Step 2: 完整连接包 (已生成) ========== -->
        <div v-else class="space-y-4 border-t pt-4">

          <!-- 状态条 -->
          <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm">
            <p class="text-green-800 font-medium mb-1">✅ 连接包已生成</p>
            <p class="text-green-700">
              有效期 <b>{{ wbTokenInfo.expires_in_days }}</b> 天, 至 <b>{{ new Date(wbTokenInfo.expires_at).toLocaleString() }}</b>
            </p>
            <p class="text-green-700">
              权限: <b>{{ wbTokenInfo.role }}</b> 角色, <b>{{ wbTokenInfo.permissions.length }}</b> 个权限点
            </p>
          </div>

          <!-- ⭐ 方式 A: 一键复制完整说明 (人话 + 机器读, 推荐) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              ⭐ 方式 A — 完整说明包 (推荐, 直接复制发给用户)
            </label>
            <div class="flex gap-2">
              <textarea
                ref="wbFullTextRef"
                :value="wbFullText"
                readonly
                rows="10"
                class="flex-1 px-3 py-2 border rounded-lg bg-gray-50 text-xs font-mono"
                @focus="$event.target.select()"
              />
              <button
                @click="copyWorkBuddyFull"
                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 whitespace-nowrap self-start"
              >
                {{ wbCopiedFull ? '✓ 已复制' : '📋 复制全部' }}
              </button>
            </div>
            <p class="text-xs text-gray-500 mt-1">
              用户粘贴到 WorkBuddy APP 或微信对话即可, 包含 API 地址、token、使用步骤。
            </p>
          </div>

          <!-- 方式 B: Deep Link (APP 已支持 workbuddy:// 协议时用) -->
          <details class="text-sm border rounded-lg p-2">
            <summary class="cursor-pointer font-medium text-gray-700 hover:text-gray-900">
              方式 B — Deep Link (APP 支持 <code class="bg-gray-100 px-1 rounded">workbuddy://</code> 协议时一键连接)
            </summary>
            <div class="mt-2 space-y-2">
              <div class="flex gap-2">
                <input
                  ref="wbLinkInputRef"
                  :value="wbTokenInfo.connect_link"
                  readonly
                  class="flex-1 px-3 py-2 border rounded-lg bg-gray-50 text-xs font-mono"
                  @focus="$event.target.select()"
                />
                <button
                  @click="copyWorkBuddyLink"
                  class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 whitespace-nowrap"
                >
                  {{ wbCopied ? '✓ 已复制' : '📋 复制' }}
                </button>
              </div>
            </div>
          </details>

          <!-- 方式 C: 分离的字段 (给开发者 / 自定义集成用) -->
          <details class="text-sm border rounded-lg p-2">
            <summary class="cursor-pointer font-medium text-gray-700 hover:text-gray-900">
              方式 C — 分离字段 (开发者 / 自定义集成)
            </summary>
            <div class="mt-2 space-y-2 text-xs">
              <div>
                <div class="font-semibold text-gray-600">🌐 API Base URL</div>
                <div class="flex gap-2 mt-1">
                  <input :value="wbTokenInfo.server_url" readonly class="flex-1 px-2 py-1 border rounded bg-gray-50 font-mono" @focus="$event.target.select()" />
                  <button @click="copyField(wbTokenInfo.server_url, 'wbCopiedUrl')" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 whitespace-nowrap">
                    {{ wbCopiedUrl ? '✓' : '复制' }}
                  </button>
                </div>
              </div>
              <div>
                <div class="font-semibold text-gray-600">🔑 Bearer Token</div>
                <div class="flex gap-2 mt-1">
                  <input :value="wbTokenInfo.token" readonly class="flex-1 px-2 py-1 border rounded bg-gray-50 font-mono text-xs" @focus="$event.target.select()" />
                  <button @click="copyWorkBuddyToken" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 whitespace-nowrap">
                    {{ wbCopiedToken ? '✓' : '复制' }}
                  </button>
                </div>
              </div>
              <div class="bg-gray-50 rounded p-2 font-mono text-xs">
                <div class="font-semibold text-gray-600 mb-1">📝 curl 示例</div>
                <code class="block break-all">
                  curl -H "Authorization: Bearer {{ wbTokenInfo.token.slice(0, 40) }}..." {{ wbTokenInfo.server_url }}/api/workbuddy/stats
                </code>
              </div>
            </div>
          </details>

          <!-- 权限清单 -->
          <details class="text-sm border rounded-lg p-2">
            <summary class="cursor-pointer font-medium text-gray-700 hover:text-gray-900">
              🔑 该用户在 WorkBuddy APP 里能做的事 ({{ wbTokenInfo.permissions.length }} 项权限)
            </summary>
            <div class="mt-2 p-2 bg-gray-50 rounded text-xs max-h-40 overflow-y-auto">
              <span v-for="p in wbTokenInfo.permissions" :key="p" class="inline-block bg-white border rounded px-2 py-0.5 m-0.5">{{ p }}</span>
            </div>
          </details>

          <!-- 用户使用步骤 -->
          <details class="text-sm border rounded-lg p-2" open>
            <summary class="cursor-pointer font-medium text-gray-700 hover:text-gray-900">
              📱 用户在 WorkBuddy APP 里的 3 步操作
            </summary>
            <ol class="mt-2 pl-5 space-y-1 text-xs text-gray-700 list-decimal">
              <li>打开 WorkBuddy APP, 进入"连接系统"页面</li>
              <li>把上面"方式 A"的完整说明粘贴进去 (或扫码 / Deep Link)</li>
              <li>APP 自动识别 API 地址 + token, 开始连接 → 显示该用户能看到的数据</li>
            </ol>
            <p class="mt-2 text-xs text-gray-500">
              ⚠️ token 过期前 APP 会自动提示重新生成。
              撤销权限: 在 gdqadmin "用户管理" 把 user_sessions 里 WorkBuddy-* 设备踢下线。
            </p>
          </details>

          <div class="flex gap-2 justify-end pt-2 border-t">
            <button @click="closeWorkBuddyModal" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
              关闭
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api'

const { t } = useI18n()

const ROLE_LABELS = {
  admin: 'settings.roleAdmin',
  manager: 'settings.roleManager',
  operator: 'settings.roleOperator',
  member: 'settings.roleMember',
  warehouse: 'settings.roleWarehouse',
  customer: 'settings.roleCustomer',
}
function roleLabel(role) {
  return ROLE_LABELS[role] ? t(ROLE_LABELS[role]) : role
}

const activeTab = ref('internal')
const searchInternal = ref('')
const searchExternal = ref('')
const internalUsers = ref([])
const externalUsers = ref([])
const showQRModal = ref(false)
const currentQRPath = ref('')
const currentIdentityCode = ref('')

onMounted(() => {
  loadInternalUsers()
})

async function loadInternalUsers() {
  try {
    const res = await api.get('/users')
    if (res.code === 0) {
      internalUsers.value = res.data
    }
  } catch (err) {
    console.error('Failed to load internal users:', err)
  }
}

async function loadExternalUsers() {
  try {
    const res = await api.get('/h5-admin/users')
    if (res.code === 0) {
      externalUsers.value = res.data
    }
  } catch (err) {
    console.error('Failed to load external users:', err)
  }
}

async function generateIdentityCode(userId, userType) {
  try {
    const endpoint = userType === 'system' ? '/identity/system/generate' : '/identity/h5/generate'
    const res = await api.post(endpoint, { userId })
    if (res.code === 0) {
      alert(t('identityQR.generateCode') + ' ' + t('common.success'))
      if (userType === 'system') {
        loadInternalUsers()
      } else {
        loadExternalUsers()
      }
    }
  } catch (err) {
    alert(t('common.error') + ': ' + (err.response?.data?.message || err.message))
  }
}

async function deleteIdentityCode(userId, userType) {
  if (!confirm(t('common.confirmDelete'))) return
  try {
    const endpoint = `/identity/system/${userId}`
    await api.delete(endpoint)
    alert(t('common.success'))
    loadInternalUsers()
  } catch (err) {
    alert(t('common.error') + ': ' + (err.response?.data?.message || err.message))
  }
}

function viewIdentityQR(user) {
  currentQRPath.value = user.identity_qr_path
  currentIdentityCode.value = user.identity_code
  showQRModal.value = true
}

function editH5User(user) {
  // TODO: Implement H5 user edit modal
  alert('Edit H5 user: ' + user.phone)
}

// ==================== 2026-08-29 WorkBuddy 连接包 v2 (完整说明书) ====================
const showWBModal = ref(false)
const wbTargetUser = ref(null)
const wbLoginKey = ref('')
const wbPassword = ref('')
const wbExpiresDays = ref(365)
const wbLoading = ref(false)
const wbError = ref('')
const wbToken = ref('')
const wbTokenInfo = ref(null)
const wbCopied = ref(false)
const wbCopiedToken = ref(false)
const wbCopiedFull = ref(false)
const wbCopiedUrl = ref(false)
const wbLinkInputRef = ref(null)
const wbFullTextRef = ref(null)

// ⭐ 完整说明包 — 复制粘贴到 WorkBuddy APP / 微信对话都 OK
// 既给人看 (人话说明), 也给机器读 (API_BASE / TOKEN / ENDPOINTS 块)
const wbFullText = computed(() => {
  if (!wbTokenInfo.value || !wbTargetUser.value) return ''
  const info = wbTokenInfo.value
  const user = wbTargetUser.value
  return `━━━━━━━━━━━━━━━━━━━━━━━━━━
🔗 WorkBuddy APP 连接包
━━━━━━━━━━━━━━━━━━━━━━━━━━

👤 用户: ${user.name}
   账号: ${user.phone || user.email}
   角色: ${info.role}
   有效期: ${info.expires_in_days} 天 (${info.expires_in_days >= 365 ? '长期' : '短期'}, 至 ${new Date(info.expires_at).toLocaleString()})
   ⏰ token 在以下情况自动失效:
      • 管理员禁用该用户
      • 管理员主动撤销 (踢下线)
      • 用户修改密码

━━━━━━━━━━━━━━━━━━━━━━━━━━
📡 API 配置 (机器读)
━━━━━━━━━━━━━━━━━━━━━━━━━━

API_BASE_URL: ${info.server_url}
TOKEN: ${info.token}
AUTH_HEADER: Authorization: Bearer ${info.token}

━━━━━━━━━━━━━━━━━━━━━━━━━━
🌐 可用 API 端点
━━━━━━━━━━━━━━━━━━━━━━━━━━

GET  ${info.server_url}/api/workbuddy/stats           — 总览统计 (任务/会议/未读)
GET  ${info.server_url}/api/workbuddy/today-events   — 今日事件
GET  ${info.server_url}/api/workbuddy/inventory/summary — 库存总览
GET  ${info.server_url}/api/workbuddy/orders/summary — 订单统计
GET  ${info.server_url}/api/workbuddy/approvals/pending — 待审批
GET  ${info.server_url}/api/workbuddy/finance/overview — 财务概览
GET  ${info.server_url}/api/workbuddy/actions/suggestions — 智能建议
POST ${info.server_url}/api/workbuddy/chat           — AI 对话 (body: {message})

━━━━━━━━━━━━━━━━━━━━━━━━━━
📱 在 WorkBuddy APP 里怎么用
━━━━━━━━━━━━━━━━━━━━━━━━━━

方法 1 (推荐): 把整段粘贴到 APP "连接系统" 输入框, APP 自动识别 API_BASE_URL 和 TOKEN
方法 2: 如果 APP 支持 workbuddy:// 协议, 用下面这个链接一键连接
        ${info.connect_link}
方法 3: 开发者集成 — 直接用上面的 API_BASE_URL + TOKEN 调任意端点

━━━━━━━━━━━━━━━━━━━━━━━━━━
🔑 你在 APP 里能做的事
━━━━━━━━━━━━━━━━━━━━━━━━━━

${info.permissions.length} 项权限: ${info.permissions.slice(0, 10).join(', ')}${info.permissions.length > 10 ? ` ... (还有 ${info.permissions.length - 10} 项)` : ''}

━━━━━━━━━━━━━━━━━━━━━━━━━━
📝 curl 测试示例
━━━━━━━━━━━━━━━━━━━━━━━━━━

curl -H "Authorization: Bearer ${info.token}" \\
     "${info.server_url}/api/workbuddy/stats"

━━━━━━━━━━━━━━━━━━━━━━━━━━`
})

function openWorkBuddyModal(user) {
  wbTargetUser.value = user
  wbLoginKey.value = user.phone || user.email || ''
  wbPassword.value = ''
  wbExpiresDays.value = 365
  wbLoading.value = false
  wbError.value = ''
  wbToken.value = ''
  wbTokenInfo.value = null
  wbCopied.value = false
  wbCopiedToken.value = false
  wbCopiedFull.value = false
  wbCopiedUrl.value = false
  showWBModal.value = true
}

function closeWorkBuddyModal() {
  showWBModal.value = false
  wbPassword.value = ''
  wbError.value = ''
}

async function generateWorkBuddyLink() {
  if (!wbPassword.value) {
    wbError.value = '请输入密码'
    return
  }
  if (!wbLoginKey.value) {
    wbLoginKey.value = wbTargetUser.value?.phone || wbTargetUser.value?.email || ''
  }
  wbLoading.value = true
  wbError.value = ''
  try {
    const res = await api.post('/auth/workbuddy-token', {
      login_key: wbLoginKey.value,
      password: wbPassword.value,
      expires_days: wbExpiresDays.value,
    })
    if (res.code === 0 && res.data) {
      wbToken.value = res.data.token
      wbTokenInfo.value = res.data
      wbPassword.value = ''  // 用完即清
    } else {
      wbError.value = res.message || '生成失败'
    }
  } catch (err) {
    wbError.value = err.response?.data?.message || err.message || '生成失败'
  } finally {
    wbLoading.value = false
  }
}

// 通用复制函数 (带 flag + 2 秒自动重置)
async function copyField(text, flagRef) {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    if (flagRef === 'wbCopiedFull') wbCopiedFull.value = true
    else if (flagRef === 'wbCopiedUrl') wbCopiedUrl.value = true
    setTimeout(() => {
      if (flagRef === 'wbCopiedFull') wbCopiedFull.value = false
      else if (flagRef === 'wbCopiedUrl') wbCopiedUrl.value = false
    }, 2000)
  } catch {
    // fallback: 选中 textarea 让用户手动 Ctrl+C
    wbFullTextRef.value?.select?.()
  }
}

async function copyWorkBuddyFull() {
  await copyField(wbFullText.value, 'wbCopiedFull')
  if (!wbCopiedFull.value) wbFullTextRef.value?.select?.()
}

async function copyWorkBuddyLink() {
  if (!wbTokenInfo.value?.connect_link) return
  try {
    await navigator.clipboard.writeText(wbTokenInfo.value.connect_link)
    wbCopied.value = true
    setTimeout(() => { wbCopied.value = false }, 2000)
  } catch {
    wbLinkInputRef.value?.select?.()
  }
}

async function copyWorkBuddyToken() {
  if (!wbTokenInfo.value?.token) return
  try {
    await navigator.clipboard.writeText(wbTokenInfo.value.token)
    wbCopiedToken.value = true
    setTimeout(() => { wbCopiedToken.value = false }, 2000)
  } catch {}
}
</script>

<style scoped>
@media (max-width: 768px) {
  /* 容器内边距缩小 */
  .p-6 {
    padding: 1rem;
  }

  /* 标题区域 */
  .mb-6 {
    margin-bottom: 1rem;
  }

  .text-2xl {
    font-size: 1.25rem;
  }

  /* Tab 导航 - 缩小间距 */
  .border-b button {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
  }

  /* 搜索表单 - 垂直堆叠 */
  .p-6 > .flex {
    flex-direction: column;
    align-items: stretch;
    gap: 0.5rem;
  }

  .p-6 input[type="text"] {
    width: 100%;
  }

  .p-6 button {
    width: 100%;
  }

  /* 表格横向滚动 */
  .overflow-x-auto {
    margin: 0 -1rem;
    padding: 0 1rem;
  }

  /* 表格单元格 - 缩小内边距 */
  table th,
  table td {
    padding: 0.5rem;
    font-size: 0.75rem;
  }

  /* 身份码 - 缩小显示 */
  .text-xs {
    font-size: 0.625rem;
    padding: 0.125rem 0.25rem;
  }

  /* 按钮 - 缩小尺寸 */
  .px-4 {
    padding: 0.375rem 0.75rem;
  }

  /* 操作按钮 */
  td button {
    font-size: 0.75rem;
    margin-right: 0.25rem;
  }

  /* Modal 弹窗 - 全屏适配 */
  .fixed.inset-0 {
    padding: 1rem;
  }

  .bg-white.rounded-lg.p-6 {
    width: 100%;
    max-width: 100%;
    padding: 1rem;
  }

  .max-w-md {
    max-width: 100%;
  }
}
</style>
