<!--
  WechatAgent.vue — 微信 Agent 服务管理台 (gdqadmin)
  多用户多 Agent 个人微信服务模块
  - 微信用户: 列表 + agent 配置(启用/专属 prompt/模型) + 拉黑
  - 消息流: 所有收发记录 + agent 回复状态
  - 通道管理: 可插拔通道实例 (mock / 真实微信后接插槽)
  - 测试模拟: 模拟微信用户发消息, 验证 agent 回复链路
-->
<template>
  <div class="p-4">
    <PageHeader title="微信 Agent 服务"
      subtitle="多用户多 Agent 个人微信服务 — 每个微信用户绑定专属 agent, 通道可插拔(真实微信后接)" />

    <!-- 统计卡片 -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
      <StatCard title="微信用户" :value="String(stats.users)" icon="group" />
      <StatCard title="消息总数" :value="String(stats.messages)" icon="chat" />
      <StatCard title="接收" :value="String(stats.inbound)" icon="call_received" />
      <StatCard title="Agent回复" :value="String(stats.outbound)" icon="smart_toy" />
      <StatCard title="通道" :value="String(stats.channels)" icon="link" />
    </div>

    <!-- Tab 切换 -->
    <div class="bg-white rounded-lg p-3 mb-4">
      <div class="flex gap-2 text-sm flex-wrap">
        <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
          class="px-4 py-1.5 rounded-md"
          :class="activeTab === t.key ? 'bg-primary text-white' : 'hover:bg-slate-100 text-text-secondary'">
          {{ t.label }}
        </button>
      </div>
    </div>

    <!-- ═══ 微信用户 ═══ -->
    <div v-if="activeTab === 'users'" class="bg-white rounded-lg p-4">
      <div class="flex items-center justify-between mb-3">
        <div class="text-base font-medium">微信用户 ({{ users.length }})</div>
        <button @click="refreshAll" class="px-3 py-1.5 text-sm border border-slate-200 rounded-md">刷新</button>
      </div>
      <div v-if="loadingUsers" class="text-center py-8 text-sm text-text-secondary">加载中…</div>
      <div v-else-if="!users.length" class="text-center py-8 text-sm text-text-secondary">
        暂无微信用户 — 用「测试模拟」发一条消息即可自动创建
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="text-left text-text-secondary border-b">
            <th class="py-2">ID</th><th class="py-2">昵称</th><th class="py-2">Agent</th>
            <th class="py-2">状态</th><th class="py-2">消息数</th><th class="py-2">最近活跃</th>
            <th class="py-2">操作</th>
          </tr></thead>
          <tbody>
            <tr v-for="u in users" :key="u.id" class="border-b last:border-0 hover:bg-slate-50">
              <td class="py-2">{{ u.id }}</td>
              <td class="py-2">
                <div class="flex items-center gap-2">
                  <span>{{ u.nickname || u.openid }}</span>
                  <span class="text-xs text-text-secondary">{{ u.openid }}</span>
                </div>
              </td>
              <td class="py-2">
                <span v-if="u.agent_enabled === 1"
                  class="inline-block px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">
                  {{ u.agent_name || '默认助手' }}
                </span>
                <span v-else class="inline-block px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">已停用</span>
              </td>
              <td class="py-2">
                <span :class="['inline-block px-2 py-0.5 rounded text-xs',
                              u.status === 'active' ? 'bg-green-100 text-green-700' :
                              u.status === 'blocked' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700']">
                  {{ u.status }}
                </span>
              </td>
              <td class="py-2">{{ u.msg_count }}</td>
              <td class="py-2 text-xs text-text-secondary">{{ fmtTime(u.last_active) }}</td>
              <td class="py-2">
                <button @click="openEditUser(u)" class="mr-2 text-primary hover:underline">配置</button>
                <button @click="viewUserMsgs(u)" class="mr-2 text-primary hover:underline">消息</button>
                <button @click="toggleBlock(u)" class="text-red-500 hover:underline">
                  {{ u.status === 'blocked' ? '解封' : '拉黑' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ 消息流 ═══ -->
    <div v-if="activeTab === 'msgs'" class="bg-white rounded-lg p-4">
      <div class="flex items-center justify-between mb-3">
        <div class="text-base font-medium">消息流 ({{ messages.length }})</div>
        <div class="flex items-center gap-2 text-xs">
          <select v-model="msgFilter.user" @change="loadMessages" class="border rounded px-2 py-1">
            <option value="">全部用户</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.nickname || u.openid }}</option>
          </select>
          <select v-model="msgFilter.dir" @change="loadMessages" class="border rounded px-2 py-1">
            <option value="">全部方向</option>
            <option value="in">用户→agent</option>
            <option value="out">agent→用户</option>
          </select>
        </div>
      </div>
      <div v-if="loadingMsgs" class="text-center py-8 text-sm text-text-secondary">加载中…</div>
      <div v-else-if="!messages.length" class="text-center py-8 text-sm text-text-secondary">暂无消息</div>
      <div v-else class="space-y-2 max-h-[65vh] overflow-y-auto">
        <div v-for="m in messages" :key="m.id"
          class="rounded-lg p-3 border"
          :class="m.direction === 'in' ? 'bg-blue-50 border-blue-100' : 'bg-green-50 border-green-100'">
          <div class="flex items-center gap-2 text-xs text-text-secondary mb-1">
            <span class="font-medium">{{ m.direction === 'in' ? '📥 用户' : '🤖 Agent' }}</span>
            <span>{{ m.nickname || '微信用户' }}</span>
            <span>{{ fmtTime(m.created_at) }}</span>
            <span v-if="m.agent_status" class="inline-block px-1.5 rounded text-[10px]"
              :class="m.agent_status === 'ok' ? 'bg-green-100 text-green-700' :
                      m.agent_status === 'error' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'">
              {{ m.agent_status }}
            </span>
          </div>
          <div class="text-sm whitespace-pre-wrap">{{ m.content }}</div>
          <div v-if="m.direction === 'in' && m.ai_reply" class="mt-1 text-xs text-text-secondary">
            🤖 回复: {{ m.ai_reply }}
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 通道管理 ═══ -->
    <div v-if="activeTab === 'channels'" class="bg-white rounded-lg p-4">
      <div class="flex items-center justify-between mb-3">
        <div class="text-base font-medium">通道实例 (可插拔)</div>
        <button @click="showAddChannel = true" class="px-3 py-1.5 text-sm bg-primary text-white rounded-md">新增通道</button>
      </div>
      <div v-if="loadingChannels" class="text-center py-8 text-sm">加载中…</div>
      <div v-else-if="!channels.length" class="text-center py-8 text-sm text-text-secondary">暂无通道</div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div v-for="c in channels" :key="c.id" class="border rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div class="font-medium">{{ c.display_name || c.channel_key }}</div>
            <div class="flex items-center gap-2">
              <span :class="['inline-block h-2.5 w-2.5 rounded-full',
                             c.status === 'active' ? (cReady(c) ? 'bg-green-500' : 'bg-amber-500') : 'bg-gray-300']"
                :title="cReady(c) ? '通道已连接' : '未加载 adapter'"></span>
              <span class="text-xs text-text-secondary">{{ c.channel_key }}</span>
            </div>
          </div>
          <div class="text-xs text-text-secondary mt-1">类型: <span class="font-mono">{{ c.channel_type }}</span></div>
          <div v-if="c.remark" class="text-xs text-text-secondary mt-1">{{ c.remark }}</div>
          <div class="mt-3 flex gap-2 text-xs">
            <button @click="toggleChannel(c)" class="text-primary hover:underline">
              {{ c.status === 'active' ? '停用' : '启用' }}
            </button>
            <button @click="checkHealth(c)" class="text-primary hover:underline">连通检测</button>
            <span v-if="healthResult && healthResult.channel === c.channel_key"
              class="text-text-secondary ml-1">{{ healthResult.status }}</span>
          </div>
        </div>
      </div>

      <!-- 新增通道弹窗 -->
      <div v-if="showAddChannel" class="fixed inset-0 bg-black/30 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-5 w-96">
          <div class="text-base font-medium mb-4">新增通道实例</div>
          <label class="block text-xs text-text-secondary mb-1">通道标识 channel_key *</label>
          <input v-model="newChannel.channel_key" placeholder="如 wechat_padlocal_a"
            class="w-full border rounded px-2 py-1.5 text-sm mb-3" />
          <label class="block text-xs text-text-secondary mb-1">类型 channel_type</label>
          <input v-model="newChannel.channel_type" placeholder="mock / wechat_padlocal / ilink"
            class="w-full border rounded px-2 py-1.5 text-sm mb-3" />
          <label class="block text-xs text-text-secondary mb-1">显示名</label>
          <input v-model="newChannel.display_name" placeholder="真实微信通道A"
            class="w-full border rounded px-2 py-1.5 text-sm mb-3" />
          <label class="block text-xs text-text-secondary mb-1">备注</label>
          <input v-model="newChannel.remark" placeholder="通道说明 / 待接入"
            class="w-full border rounded px-2 py-1.5 text-sm mb-4" />
          <div class="flex justify-end gap-2">
            <button @click="showAddChannel = false" class="px-3 py-1.5 text-sm border rounded-md">取消</button>
            <button @click="addChannel" class="px-4 py-1.5 text-sm bg-primary text-white rounded-md">保存</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 测试模拟 ═══ -->
    <div v-if="activeTab === 'test'" class="bg-white rounded-lg p-4">
      <div class="text-base font-medium mb-3">测试模拟 — 模拟微信用户发消息</div>
      <div class="text-xs text-text-secondary mb-3">
        相当于把收到的微信消息 POST 到 webhook, 走完整链路: 找/建用户 → 触发专属 agent → 记录 + 模拟投递。
        验证"每个微信用户有自己的 agent"。
      </div>
      <div class="grid gap-3 max-w-xl">
        <div>
          <label class="block text-xs text-text-secondary mb-1">openid (唯一标识, 建议用微信 openid 格式)</label>
          <input v-model="testForm.openid" placeholder="wx_xxxxxxxx"
            class="w-full border rounded px-2 py-1.5 text-sm" />
        </div>
        <div>
          <label class="block text-xs text-text-secondary mb-1">昵称</label>
          <input v-model="testForm.senderName" placeholder="测试用户"
            class="w-full border rounded px-2 py-1.5 text-sm" />
        </div>
        <div>
          <label class="block text-xs text-text-secondary mb-1">消息内容 *</label>
          <textarea v-model="testForm.content" rows="2" placeholder="你好, 你是谁"
            class="w-full border rounded px-2 py-1.5 text-sm" />
        </div>
        <div class="flex gap-2">
          <button @click="sendTest" :disabled="sending" class="px-4 py-1.5 text-sm bg-primary text-white rounded-md disabled:opacity-50">
            {{ sending ? '发送中…' : '发送测试消息' }}
          </button>
          <span v-if="testResult" class="text-sm self-center">
            <span class="text-green-600">✅ Agent 回复:</span> {{ testResult }}
          </span>
        </div>
      </div>
    </div>

    <!-- ═══ 用户 agent 配置弹窗 ═══ -->
    <div v-if="editingUser" class="fixed inset-0 bg-black/30 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-5 w-[480px]">
        <div class="text-base font-medium mb-1">配置专属 Agent — {{ editingUser.nickname || editingUser.openid }}</div>
        <div class="text-xs text-text-secondary mb-4">openid: {{ editingUser.openid }}</div>
        <label class="block text-xs text-text-secondary mb-1 flex items-center gap-2">
          <input type="checkbox" v-model="userForm.agent_enabled" class="accent-primary" />
          启用专属 agent 自动回复
        </label>
        <label class="block text-xs text-text-secondary mb-1 mt-3">Agent 名称 (用户可见)</label>
        <input v-model="userForm.agent_name" placeholder="如 张三的专属管家"
          class="w-full border rounded px-2 py-1.5 text-sm mb-3" />
        <label class="block text-xs text-text-secondary mb-1">专属 System Prompt (该用户 agent 的性格/职责)</label>
        <textarea v-model="userForm.system_prompt" rows="3" placeholder="你是张三的专属助理, 精通…"
          class="w-full border rounded px-2 py-1.5 text-sm mb-3" />
        <label class="block text-xs text-text-secondary mb-1">模型 (空=全局默认)</label>
        <input v-model="userForm.model_key" placeholder="留空用默认模型"
          class="w-full border rounded px-2 py-1.5 text-sm mb-4" />
        <div class="flex justify-end gap-2">
          <button @click="editingUser = null" class="px-3 py-1.5 text-sm border rounded-md">取消</button>
          <button @click="saveUser" class="px-4 py-1.5 text-sm bg-primary text-white rounded-md">保存</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/api/request'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'

const tabs = [
  { key: 'users', label: '微信用户' },
  { key: 'msgs', label: '消息流' },
  { key: 'channels', label: '通道管理' },
  { key: 'test', label: '测试模拟' },
]
const activeTab = ref('users')

const stats = reactive({ users: 0, messages: 0, inbound: 0, outbound: 0, channels: 0 })

// 用户
const users = ref([])
const loadingUsers = ref(false)
const editingUser = ref(null)
const userForm = reactive({ agent_enabled: 1, agent_name: '', system_prompt: '', model_key: '' })

// 消息
const messages = ref([])
const loadingMsgs = ref(false)
const msgFilter = reactive({ user: '', dir: '' })

// 通道
const channels = ref([])
const loadingChannels = ref(false)
const showAddChannel = ref(false)
const newChannel = reactive({ channel_key: '', channel_type: 'mock', display_name: '', remark: '' })
const healthResult = ref(null)

// 测试
const testForm = reactive({ openid: '', senderName: '', content: '' })
const sending = ref(false)
const testResult = ref('')

const fmtTime = (t) => t ? String(t).replace('T', ' ').slice(0, 19) : '—'

async function loadStats() {
  try { const { data } = await api.get('/wechat-agent/admin/stats'); Object.assign(stats, data.data || {}) } catch (e) {}
}
async function loadUsers() {
  loadingUsers.value = true
  try { const { data } = await api.get('/wechat-agent/admin/users'); users.value = data.data || [] }
  catch (e) { console.error(e) }
  finally { loadingUsers.value = false }
}
async function loadMessages() {
  loadingMsgs.value = true
  try {
    const params = {}
    if (msgFilter.user) params.wxUserId = msgFilter.user
    if (msgFilter.dir) params.direction = msgFilter.dir
    const { data } = await api.get('/wechat-agent/admin/messages', { params })
    messages.value = (data.data || []).slice(0, 100)
  } catch (e) { console.error(e) } finally { loadingMsgs.value = false }
}
async function loadChannels() {
  loadingChannels.value = true
  try { const { data } = await api.get('/wechat-agent/admin/channels'); channels.value = data.data || [] }
  catch (e) { console.error(e) } finally { loadingChannels.value = false }
}
const refreshAll = async () => { await Promise.all([loadStats(), loadUsers(), loadChannels()]) }

function openEditUser(u) {
  editingUser.value = u
  Object.assign(userForm, {
    agent_enabled: u.agent_enabled === 1, agent_name: u.agent_name || '',
    system_prompt: u.system_prompt || '', model_key: u.model_key || '',
  })
}
async function saveUser() {
  try {
    const payload = {
      nickname: editingUser.value.nickname,
      agent_enabled: userForm.agent_enabled ? 1 : 0,
      agent_name: userForm.agent_name, system_prompt: userForm.system_prompt, model_key: userForm.model_key,
    }
    await api.patch(`/wechat-agent/admin/users/${editingUser.value.id}`, payload)
    editingUser.value = null
    await loadUsers()
  } catch (e) { alert('保存失败: ' + (e.response?.data?.message || e.message)) }
}
async function toggleBlock(u) {
  const next = u.status === 'blocked' ? 'active' : 'blocked'
  if (!confirm(`确定要${next === 'blocked' ? '拉黑' : '解封'}用户 ${u.nickname || u.openid} 吗?`)) return
  await api.patch(`/wechat-agent/admin/users/${u.id}`, { status: next })
  await loadUsers()
}
function viewUserMsgs(u) {
  msgFilter.user = String(u.id); activeTab.value = 'msgs'; loadMessages()
}

async function addChannel() {
  if (!newChannel.channel_key) { alert('channel_key 必填'); return }
  await api.post('/wechat-agent/admin/channels', { ...newChannel })
  showAddChannel.value = false
  Object.assign(newChannel, { channel_key: '', channel_type: 'mock', display_name: '', remark: '' })
  await loadChannels()
}
async function toggleChannel(c) {
  await api.patch(`/wechat-agent/admin/channels/${c.id}`, { status: c.status === 'active' ? 'disabled' : 'active' })
  await loadChannels()
}
async function checkHealth(c) {
  try { const { data } = await api.get(`/wechat-agent/admin/channels/${c.id}/health`); healthResult.value = data.data || {} }
  catch (e) { alert('检测失败') }
}
const cReady = (c) => c.channel_key === 'mock'

async function sendTest() {
  if (!testForm.openid || !testForm.content) { alert('openid 和消息内容必填'); return }
  sending.value = true; testResult.value = ''
  try {
    // 走 /simulate-message (管理台等价 webhook)
    const { data } = await api.post('/wechat-agent/admin/simulate-message', {
      openid: testForm.openid, senderName: testForm.senderName || undefined, content: testForm.content,
    })
    testResult.value = data.data?.reply || '(无回复 — 可能 agent 停用或用户被拉黑)'
    await refreshAll()
  } catch (e) { alert('发送失败: ' + (e.response?.data?.message || e.message)) }
  finally { sending.value = false }
}

onMounted(refreshAll)
</script>
