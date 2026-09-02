/**
 * useTask - 任务共享 composable (2026-09-02 立)
 *
 * 三端共用: gdqadmin / minip / labor
 *
 * 用法:
 *   const { tasks, fetchTasks, submitTask, updateStatus } = useTask(api)
 */

import { ref, computed } from 'vue'

export function useTask(api) {
  const tasks = ref([])
  const stats = ref(null)
  const loading = ref(false)
  const saving = ref(false)

  // ─── 获取 ─────────────────────────────────────────
  async function fetchTasks(params = {}) {
    loading.value = true
    try {
      const res = await api.get('/tasks', { params })
      if (res.code === 0) {
        tasks.value = res.data?.list || res.data || []
        return res.data
      }
      return null
    } finally {
      loading.value = false
    }
  }

  async function fetchStats(params = {}) {
    try {
      const res = await api.get('/tasks/stats', { params })
      if (res.code === 0) {
        stats.value = res.data
        return res.data
      }
      return null
    } catch { return null }
  }

  // ─── 提交 ─────────────────────────────────────────
  async function submitTask(payload, mode = 'create') {
    saving.value = true
    try {
      const res = mode === 'update'
        ? await api.put(`/tasks/${payload.id}`, payload)
        : await api.post('/tasks', payload)
      if (res.code === 0) {
        return { ok: true, id: res.data?.id, data: res.data }
      }
      return { ok: false, code: res.code, message: res.message }
    } catch (e) {
      return { ok: false, code: -1, message: e.message }
    } finally {
      saving.value = false
    }
  }

  async function updateStatus(taskId, status) {
    try {
      const res = await api.patch(`/tasks/${taskId}/review`, { status })
      if (res.code === 0) {
        return { ok: true, data: res.data }
      }
      return { ok: false, code: res.code, message: res.message }
    } catch (e) {
      return { ok: false, code: -1, message: e.message }
    }
  }

  async function deleteTask(taskId) {
    try {
      const res = await api.delete(`/tasks/${taskId}`)
      if (res.code === 0) return { ok: true }
      return { ok: false, code: res.code, message: res.message }
    } catch (e) {
      return { ok: false, code: -1, message: e.message }
    }
  }

  // ─── 风格适配器 ─────────────────────────────────
  function getAdapter(type = 'gdqadmin') {
    const adapters = {
      gdqadmin: {
        cardClass: 'bg-white rounded-2xl shadow-sm p-6',
        listItemClass: 'border-b border-slate-100 last:border-0 p-4 hover:bg-slate-50',
        buttonPrimary: 'px-4 py-2 bg-primary text-white rounded-lg font-medium',
        pageSize: 20
      },
      minip: {
        cardClass: 'bg-white rounded-xl p-4 mb-3 shadow-sm',
        listItemClass: 'bg-white rounded-xl p-3 mb-2 shadow-sm active:bg-slate-50',
        buttonPrimary: 'w-full py-3 bg-primary text-white rounded-xl font-medium',
        pageSize: 15
      },
      labor: {
        cardClass: 'bg-white rounded-2xl p-5 shadow-md',
        listItemClass: 'bg-white rounded-2xl p-4 mb-3 shadow-md',
        buttonPrimary: 'w-full py-4 bg-primary text-white rounded-2xl font-bold text-lg',
        pageSize: 15
      }
    }
    return adapters[type] || adapters.gdqadmin
  }

  // ─── 任务状态颜色 ────────────────────────────────
  function statusClass(status) {
    return {
      pending: 'bg-slate-100 text-slate-600',
      in_progress: 'bg-blue-100 text-blue-700',
      completed: 'bg-green-100 text-green-700',
      approved: 'bg-green-100 text-green-700',
      rejected: 'bg-red-100 text-red-700',
      cancelled: 'bg-slate-100 text-slate-400'
    }[status] || 'bg-slate-100 text-slate-600'
  }

  function statusLabel(status) {
    return {
      pending: '待处理',
      in_progress: '进行中',
      completed: '已完成',
      approved: '已批准',
      rejected: '已驳回',
      cancelled: '已取消'
    }[status] || status
  }

  function priorityClass(p) {
    return {
      high: 'bg-red-100 text-red-700',
      medium: 'bg-amber-100 text-amber-700',
      low: 'bg-slate-100 text-slate-600'
    }[p] || 'bg-slate-100 text-slate-600'
  }

  return {
    tasks, stats, loading, saving,
    fetchTasks, fetchStats, submitTask, updateStatus, deleteTask,
    getAdapter, statusClass, statusLabel, priorityClass
  }
}