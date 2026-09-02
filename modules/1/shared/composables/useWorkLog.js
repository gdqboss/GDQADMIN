/**
 * useWorkLog - 工作日志共享 composable (2026-09-02 立)
 *
 * 三端 (gdqadmin / minip / labor) 共用的工作日志业务逻辑:
 *  - 数据获取 (list / templates / users)
 *  - 提交 (create / update / draft)
 *  - 互动 (like / comment / read)
 *  - 表单校验 (required 字段)
 *
 * 用法:
 *   const { logs, fetchLogs, submitLog, parseFields, formatPayload } = useWorkLog(api)
 *
 * api: axios instance (gdqadmin 传 services/api.js, minip/labor 传各自 client)
 */

import { ref } from 'vue'

export function useWorkLog(api) {
  const logs = ref([])
  const templates = ref([])
  const users = ref([])
  const loading = ref(false)
  const saving = ref(false)

  // ─── 模板字段解析 ───────────────────────────────────────
  function parseFields(fields) {
    if (!fields) return []
    if (typeof fields === 'object' && !Array.isArray(fields)) return Object.values(fields)
    try {
      const parsed = typeof fields === 'string' ? JSON.parse(fields) : fields
      return Array.isArray(parsed) ? parsed : []
    } catch { return [] }
  }

  // ─── 表单校验 (根据模板 required 字段) ──────────────
  function validateForm(formData, template) {
    if (!template) return null
    const fields = parseFields(template.fields)
    for (const field of fields) {
      if (!field.required) continue
      const val = formData.content?.[field.name]
      const hasValue = val != null
        && !(typeof val === 'string' && !val.trim())
        && !(Array.isArray(val) && val.length === 0)
      if (!hasValue) return field.label || field.name
    }
    // 检查顶层 recipients (gdqadmin 风格)
    if (formData.recipients && formData.recipients.length > 0) return null
    return null
  }

  // ─── Payload 标准化 (兼容字符串 content + 对象 content) ────────
  function formatPayload(formData, template, opts = {}) {
    const { mode = 'create', status = 'submitted' } = opts
    return {
      log_type: formData.log_type,
      submit_date: formData.submit_date || new Date().toISOString().split('T')[0],
      content: typeof formData.content === 'object'
        ? JSON.stringify(formData.content)
        : (formData.content || '{}'),
      recipients: formData.recipients || [],
      participants: formData.participants || [],
      attachments: formData.attachments || [],
      location: formData.location || null,
      gps_lat: formData.gps_lat || null,
      gps_lng: formData.gps_lng || null,
      status,
      template_id: template?.id || formData.template_id,
      ...(mode === 'update' && { id: formData.id })
    }
  }

  // ─── 数据获取 ───────────────────────────────────────
  async function fetchLogs(params = {}) {
    loading.value = true
    try {
      const res = await api.get('/work-logs', { params })
      if (res.code === 0) {
        logs.value = res.data?.list || res.data || []
        return res.data
      }
      return null
    } finally {
      loading.value = false
    }
  }

  async function fetchTemplates(params = {}) {
    try {
      const res = await api.get('/work-logs/templates', { params })
      if (res.code === 0) {
        templates.value = res.data || []
        return templates.value
      }
      return []
    } catch { return [] }
  }

  async function fetchUsers(params = {}) {
    try {
      const res = await api.get('/users', { params: { pageSize: 1000, ...params } })
      if (res.code === 0) {
        users.value = res.data?.list || res.data || []
        return users.value
      }
      return []
    } catch { return [] }
  }

  // ─── 提交 ───────────────────────────────────────────
  async function submitLog(formData, template, opts = {}) {
    const missing = validateForm(formData, template)
    if (missing) {
      return { ok: false, code: 400, message: `必填字段不能为空: ${missing}` }
    }
    saving.value = true
    try {
      const payload = formatPayload(formData, template, opts)
      const res = opts.mode === 'update'
        ? await api.put(`/work-logs/${formData.id}`, payload)
        : await api.post('/work-logs', payload)
      if (res.code === 0) {
        return { ok: true, id: res.data?.id, data: res.data }
      }
      return { ok: false, code: res.code, message: res.message || '提交失败' }
    } catch (e) {
      return { ok: false, code: -1, message: e.message || '网络错误' }
    } finally {
      saving.value = false
    }
  }

  // ─── 互动 ───────────────────────────────────────────
  async function likeLog(logId) {
    return api.post(`/work-logs/${logId}/like`)
  }
  async function unlikeLog(logId) {
    return api.post(`/work-logs/${logId}/dislike`)
  }
  async function commentLog(logId, content) {
    return api.post(`/work-logs/${logId}/comment`, { content })
  }
  async function markRead(logId) {
    return api.post(`/work-logs/${logId}/read`)
  }

  return {
    // state
    logs, templates, users, loading, saving,
    // methods
    parseFields, validateForm, formatPayload,
    fetchLogs, fetchTemplates, fetchUsers,
    submitLog, likeLog, unlikeLog, commentLog, markRead
  }
}

/**
 * 风格适配器 - 根据端选择 UI 变体
 * @param {'gdqadmin'|'minip'|'labor'} type
 */
export function getWorkLogAdapter(type = 'gdqadmin') {
  const adapters = {
    gdqadmin: {
      cardClass: 'bg-white rounded-2xl shadow-sm p-6',
      labelClass: 'text-sm font-medium text-slate-700',
      inputClass: 'w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20',
      buttonPrimary: 'px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium',
      buttonGhost: 'px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm',
      listItemClass: 'border-b border-slate-100 last:border-0 p-4 hover:bg-slate-50',
      pageSize: 20,
      dateFormat: 'YYYY-MM-DD HH:mm'
    },
    minip: {
      cardClass: 'bg-white rounded-xl p-4 mb-3 shadow-sm',
      labelClass: 'text-xs text-slate-500 mb-1',
      inputClass: 'w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-50',
      buttonPrimary: 'w-full py-3 bg-primary text-white rounded-xl text-base font-medium active:opacity-80',
      buttonGhost: 'w-full py-3 text-slate-600 rounded-xl text-sm bg-slate-100',
      listItemClass: 'bg-white rounded-xl p-3 mb-2 shadow-sm active:bg-slate-50',
      pageSize: 10,
      dateFormat: 'MM-DD HH:mm'
    },
    labor: {
      cardClass: 'bg-white rounded-2xl p-5 shadow-md',
      labelClass: 'text-base font-semibold text-slate-800 mb-2',
      inputClass: 'w-full px-4 py-3 border-2 border-slate-200 rounded-xl text-lg focus:border-primary',
      buttonPrimary: 'w-full py-4 bg-primary text-white rounded-2xl text-lg font-bold active:scale-95 transition',
      buttonGhost: 'w-full py-4 text-slate-700 rounded-2xl text-base bg-slate-100 active:bg-slate-200',
      listItemClass: 'bg-white rounded-2xl p-4 mb-3 shadow-md active:bg-slate-50',
      pageSize: 15,
      dateFormat: 'MM-DD HH:mm'
    }
  }
  return adapters[type] || adapters.gdqadmin
}