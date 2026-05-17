/**
 * Cron jobs for WeCom data synchronization
 * Uses node-cron (already installed).
 * Only starts if WECOM_CORP_ID and WECOM_SECRET are configured.
 */

import cron from 'node-cron'
import { isWeComConfigured } from './services/wecom.js'
import { syncAttendance, syncApprovals, syncContacts } from './services/wecom-sync.js'

function today() {
  return new Date().toLocaleString('sv-SE', { timeZone: 'Asia/Shanghai' }).slice(0, 10)
}

function yesterday() {
  const d = new Date()
  d.setDate(d.getDate() - 1)
  return d.toLocaleString('sv-SE', { timeZone: 'Asia/Shanghai' }).slice(0, 10)
}

export function startCronJobs() {
  if (!isWeComConfigured()) {
    console.log('[cron] WeCom not configured, skipping sync jobs')
    return
  }

  // Attendance sync: every 30 minutes, workdays 8-20
  cron.schedule('*/30 8-19 * * 1-5', async () => {
    try {
      console.log('[cron] Syncing attendance...')
      const result = await syncAttendance(today(), today())
      console.log(`[cron] Attendance sync done: ${result.synced} records`)
    } catch (err) {
      console.error('[cron] Attendance sync failed:', err.message)
    }
  })

  // Approvals sync: every hour, workdays 8-20
  cron.schedule('0 8-20 * * 1-5', async () => {
    try {
      console.log('[cron] Syncing approvals...')
      const result = await syncApprovals(yesterday(), today())
      console.log(`[cron] Approvals sync done: ${result.synced} records`)
    } catch (err) {
      console.error('[cron] Approvals sync failed:', err.message)
    }
  })

  // Contacts sync: daily at 6:30, workdays
  cron.schedule('30 6 * * 1-5', async () => {
    try {
      console.log('[cron] Syncing contacts...')
      const result = await syncContacts()
      console.log(`[cron] Contacts sync done: ${result.synced} records`)
    } catch (err) {
      console.error('[cron] Contacts sync failed:', err.message)
    }
  })

  console.log('[cron] WeCom sync jobs scheduled')
}
