/**
 * useAttendance - 出勤共享 composable (2026-09-02 立)
 *
 * 三端共用: gdqadmin / minip / labor
 *
 * 用法:
 *   const { records, todayRecords, fetchToday, checkIn, checkOut, fetchStats } = useAttendance(api)
 */

import { ref, computed } from 'vue'

export function useAttendance(api) {
  const records = ref([])
  const todayRecords = ref([])
  const stats = ref(null)
  const loading = ref(false)
  const saving = ref(false)

  // ─── 打卡 ─────────────────────────────────────────
  async function checkIn(payload = {}) {
    saving.value = true
    try {
      const gps = await getCurrentGPS().catch(() => ({}))
      const res = await api.post('/oa/attendance/check-in', {
        ...payload,
        ...gps,
        check_in_at: new Date().toISOString()
      })
      if (res.code === 0) {
        return { ok: true, data: res.data }
      }
      return { ok: false, code: res.code, message: res.message }
    } catch (e) {
      return { ok: false, code: -1, message: e.message }
    } finally {
      saving.value = false
    }
  }

  async function checkOut(payload = {}) {
    saving.value = true
    try {
      const gps = await getCurrentGPS().catch(() => ({}))
      const res = await api.post('/oa/attendance/check-out', {
        ...payload,
        ...gps,
        check_out_at: new Date().toISOString()
      })
      if (res.code === 0) return { ok: true, data: res.data }
      return { ok: false, code: res.code, message: res.message }
    } catch (e) {
      return { ok: false, code: -1, message: e.message }
    } finally {
      saving.value = false
    }
  }

  // ─── 获取 ─────────────────────────────────────────
  async function fetchRecords(params = {}) {
    loading.value = true
    try {
      const res = await api.get('/oa/attendance', { params })
      if (res.code === 0) {
        records.value = res.data?.list || res.data || []
        return res.data
      }
      return null
    } finally {
      loading.value = false
    }
  }

  async function fetchToday() {
    try {
      const res = await api.get('/oa/attendance/today')
      if (res.code === 0) {
        todayRecords.value = res.data?.records || res.data || []
        return todayRecords.value
      }
      return []
    } catch { return [] }
  }

  async function fetchStats(params = {}) {
    try {
      const res = await api.get('/oa/attendance/summary', { params })
      if (res.code === 0) {
        stats.value = res.data
        return res.data
      }
      return null
    } catch { return null }
  }

  // ─── 工具: GPS ───────────────────────────────────
  function getCurrentGPS() {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) return reject(new Error('当前环境不支持定位'))
      navigator.geolocation.getCurrentPosition(
        (pos) => resolve({
          gps_lat: pos.coords.latitude,
          gps_lng: pos.coords.longitude
        }),
        (err) => reject(err),
        { enableHighAccuracy: true, timeout: 10000 }
      )
    })
  }

  // ─── 工具: 计算工时 ──────────────────────────────
  function calcWorkHours(checkIn, checkOut) {
    if (!checkIn || !checkOut) return 0
    const start = new Date(checkIn).getTime()
    const end = new Date(checkOut).getTime()
    if (isNaN(start) || isNaN(end) || end <= start) return 0
    return ((end - start) / (1000 * 60 * 60)).toFixed(2)
  }

  // ─── 风格适配器 ─────────────────────────────────
  function getAdapter(type = 'gdqadmin') {
    const adapters = {
      gdqadmin: {
        cardClass: 'bg-white rounded-2xl shadow-sm p-6',
        listItemClass: 'border-b border-slate-100 last:border-0 p-4',
        buttonPrimary: 'px-6 py-3 bg-primary text-white rounded-xl font-medium hover:bg-primary/90',
        buttonCheckIn: 'w-full py-4 bg-green-500 text-white rounded-2xl font-bold text-lg',
        buttonCheckOut: 'w-full py-4 bg-amber-500 text-white rounded-2xl font-bold text-lg',
        pageSize: 20
      },
      minip: {
        cardClass: 'bg-white rounded-xl p-4 mb-3 shadow-sm',
        listItemClass: 'bg-white rounded-xl p-3 mb-2 shadow-sm',
        buttonPrimary: 'w-full py-3 bg-primary text-white rounded-xl font-medium',
        buttonCheckIn: 'w-full py-4 bg-green-500 text-white rounded-2xl font-bold text-lg active:scale-95',
        buttonCheckOut: 'w-full py-4 bg-amber-500 text-white rounded-2xl font-bold text-lg active:scale-95',
        pageSize: 15
      },
      labor: {
        cardClass: 'bg-white rounded-2xl p-5 shadow-md',
        listItemClass: 'bg-white rounded-2xl p-4 mb-3 shadow-md',
        buttonPrimary: 'w-full py-4 bg-primary text-white rounded-2xl font-bold text-lg',
        buttonCheckIn: 'w-full py-5 bg-green-500 text-white rounded-2xl font-bold text-xl active:scale-95',
        buttonCheckOut: 'w-full py-5 bg-amber-500 text-white rounded-2xl font-bold text-xl active:scale-95',
        pageSize: 15
      }
    }
    return adapters[type] || adapters.gdqadmin
  }

  return {
    records, todayRecords, stats, loading, saving,
    checkIn, checkOut, fetchRecords, fetchToday, fetchStats,
    calcWorkHours, getCurrentGPS, getAdapter
  }
}