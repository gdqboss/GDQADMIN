/**
 * WeCom (企业微信) API Client
 * All WeCom API calls go through this module.
 */

const BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin'

// In-memory token cache
let tokenCache = {
  token: null,
  expiresAt: 0
}
let tokenRefreshPromise = null

function getConfig() {
  return {
    corpId: process.env.WECOM_CORP_ID,
    secret: process.env.WECOM_SECRET,
    agentId: process.env.WECOM_AGENT_ID
  }
}

export function isWeComConfigured() {
  const { corpId, secret } = getConfig()
  return !!(corpId && secret)
}

/**
 * Get access_token, using cache with 5-minute early refresh.
 * Deduplicates concurrent refresh requests.
 */
export async function getAccessToken() {
  const now = Date.now()
  // Return cached token if still valid (with 5 min buffer)
  if (tokenCache.token && tokenCache.expiresAt > now + 5 * 60 * 1000) {
    return tokenCache.token
  }

  // Deduplicate: if a refresh is already in-flight, wait for it
  if (tokenRefreshPromise) {
    return tokenRefreshPromise
  }

  tokenRefreshPromise = refreshToken()
  try {
    return await tokenRefreshPromise
  } finally {
    tokenRefreshPromise = null
  }
}

async function refreshToken() {
  const { corpId, secret } = getConfig()
  if (!corpId || !secret) {
    throw new Error('WECOM_CORP_ID 和 WECOM_SECRET 未配置')
  }

  const url = `${BASE_URL}/gettoken?corpid=${encodeURIComponent(corpId)}&corpsecret=${encodeURIComponent(secret)}`
  const res = await fetch(url)
  const data = await res.json()

  if (data.errcode !== 0) {
    throw new Error(`获取 access_token 失败: ${data.errmsg} (errcode: ${data.errcode})`)
  }

  tokenCache = {
    token: data.access_token,
    expiresAt: Date.now() + data.expires_in * 1000
  }

  return tokenCache.token
}

/**
 * Get checkin data for specified users and time range
 * @param {string[]} userIds - WeCom user IDs
 * @param {number} startTime - Unix timestamp (seconds)
 * @param {number} endTime - Unix timestamp (seconds)
 */
export async function getCheckinData(userIds, startTime, endTime) {
  const token = await getAccessToken()
  const res = await fetch(`${BASE_URL}/checkin/getcheckindata?access_token=${token}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      opencheckindatatype: 3, // 3 = all types
      starttime: startTime,
      endtime: endTime,
      useridlist: userIds
    })
  })
  const data = await res.json()
  if (data.errcode !== 0) {
    throw new Error(`获取打卡数据失败: ${data.errmsg} (errcode: ${data.errcode})`)
  }
  return data.checkindata || []
}

/**
 * Get approval list for a time range
 * @param {number} startTime - Unix timestamp (seconds)
 * @param {number} endTime - Unix timestamp (seconds)
 */
export async function getApprovalList(startTime, endTime) {
  const token = await getAccessToken()
  const allSpNos = []
  let cursor = 0

  // Paginate through results
  while (true) {
    const res = await fetch(`${BASE_URL}/oa/getapprovalinfo?access_token=${token}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        starttime: startTime,
        endtime: endTime,
        cursor,
        size: 100,
        filters: []
      })
    })
    const data = await res.json()
    if (data.errcode !== 0) {
      throw new Error(`获取审批列表失败: ${data.errmsg} (errcode: ${data.errcode})`)
    }
    const spNos = data.sp_no_list || []
    allSpNos.push(...spNos)

    if (!data.next_cursor) break
    cursor = data.next_cursor
  }

  return allSpNos
}

/**
 * Get approval detail by sp_no
 * @param {string} spNo - Approval number
 */
export async function getApprovalDetail(spNo) {
  const token = await getAccessToken()
  const res = await fetch(`${BASE_URL}/oa/getapprovaldetail?access_token=${token}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ sp_no: spNo })
  })
  const data = await res.json()
  if (data.errcode !== 0) {
    throw new Error(`获取审批详情失败: ${data.errmsg} (errcode: ${data.errcode})`)
  }
  return data.info || null
}

/**
 * Send a message via WeCom
 * @param {Object} params
 * @param {string} params.touser - Recipient user ID(s), pipe-separated
 * @param {string} params.msgtype - Message type (text, markdown, etc.)
 * @param {string} params.content - Message content
 */
export async function sendMessage({ touser, msgtype = 'text', content }) {
  const token = await getAccessToken()
  const { agentId } = getConfig()
  if (!agentId) {
    throw new Error('WECOM_AGENT_ID 未配置')
  }

  const body = {
    touser,
    msgtype,
    agentid: Number(agentId)
  }

  if (msgtype === 'text') {
    body.text = { content }
  } else if (msgtype === 'markdown') {
    body.markdown = { content }
  }

  const res = await fetch(`${BASE_URL}/message/send?access_token=${token}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  })
  const data = await res.json()
  if (data.errcode !== 0) {
    throw new Error(`发送消息失败: ${data.errmsg} (errcode: ${data.errcode})`)
  }
  return data
}

/**
 * Get department list
 * @param {number} [parentId] - Parent department ID (optional, default root)
 */
export async function getDepartmentList(parentId) {
  const token = await getAccessToken()
  let url = `${BASE_URL}/department/list?access_token=${token}`
  if (parentId !== undefined) {
    url += `&id=${parentId}`
  }
  const res = await fetch(url)
  const data = await res.json()
  if (data.errcode !== 0) {
    throw new Error(`获取部门列表失败: ${data.errmsg} (errcode: ${data.errcode})`)
  }
  return data.department || []
}

/**
 * Get user list by department
 * @param {number} deptId - Department ID
 */
export async function getUserListByDept(deptId) {
  const token = await getAccessToken()
  const url = `${BASE_URL}/user/list?access_token=${token}&department_id=${deptId}`
  const res = await fetch(url)
  const data = await res.json()
  if (data.errcode !== 0) {
    throw new Error(`获取部门用户列表失败: ${data.errmsg} (errcode: ${data.errcode})`)
  }
  return data.userlist || []
}
