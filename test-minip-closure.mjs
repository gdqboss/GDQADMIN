// 测试 minip D.2 + D.3 闭环 (按真实 schema 字段)
import { createConnection } from 'mysql2/promise'
const db = await createConnection({ host: '127.0.0.1', port: 3306, user: 'gdq', password: 'Re78g0A1XcNmr1T8', database: 'gdq' })

const BASE = 'http://localhost:3200/api'
const ADMIN_PHONE = '18676970008'
const MGR_PHONE   = '13900099991'

async function getLastSmsCode(phone) {
  const [rows] = await db.query('SELECT code FROM sms_codes WHERE phone = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1', [phone])
  return rows[0]?.code
}
async function login(phone) {
  await fetch(`${BASE}/auth/sms-code`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone })
  })
  await new Promise(r => setTimeout(r, 400))
  const code = await getLastSmsCode(phone)
  if (!code) throw new Error(`没拿到验证码 for ${phone}`)
  const r = await fetch(`${BASE}/auth/sms-login`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone, code })
  }).then(r => r.json())
  if (r.code !== 0) throw new Error(`登录失败: ${JSON.stringify(r)}`)
  return r.data.token
}
async function authReq(method, path, token, body) {
  const opts = { method, headers: { 'Content-Type': 'application/json' } }
  if (token) opts.headers.Authorization = `Bearer ${token}`
  if (body) opts.body = JSON.stringify(body)
  const r = await fetch(`${BASE}${path}`, opts)
  return { status: r.status, body: await r.json() }
}

const results = { D2: {}, D3: {} }

// ===== D.2 应用申请闭环 =====
try {
  console.log('\n=== D.2 应用申请闭环 ===')
  const applicantToken = await login(MGR_PHONE)
  console.log('✓ 申请人登录 (Manager)')
  const submit = await authReq('POST', '/minip/applications', applicantToken, {
    company_name: 'D2测试公司',
    contact_name: '张三',
    contact_phone: MGR_PHONE,
    contact_email: 'zhangsan@test.com',
    business_type: 'tech',
    team_size: '10-50',
    expected_join_date: '2026-08-01',
    remarks: 'D.2 闭环测试'
  })
  console.log('提交:', submit.status, JSON.stringify(submit.body).slice(0, 200))
  if (submit.body.code !== 0) throw new Error('提交失败')
  const appId = submit.body.data.id

  const adminToken = await login(ADMIN_PHONE)
  console.log('✓ admin 登录')
  const review = await authReq('PUT', `/minip/admin/applications/${appId}/review`, adminToken, {
    status: 'approved', review_remarks: 'D.2 approved'
  })
  console.log('审批:', review.status, JSON.stringify(review.body))

  await new Promise(r => setTimeout(r, 500))
  const [empRows] = await db.query(
    `SELECT * FROM minip_employees WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND) ORDER BY id DESC LIMIT 1`
  )
  const [notifRows] = await db.query(
    `SELECT * FROM notifications WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND) ORDER BY id DESC LIMIT 1`
  )
  results.D2 = {
    submit: submit.body.code === 0 ? 'OK' : 'FAIL',
    review: review.status,
    reviewMsg: review.body.message,
    employeeCreated: empRows.length > 0,
    employeeRecord: empRows[0] || null,
    notificationCreated: notifRows.length > 0,
    notification: notifRows[0] || null
  }
  console.log('✓ employee 自动创建:', empRows.length > 0, empRows[0]?.employee_code)
  console.log('✓ 通知创建:', notifRows.length > 0, notifRows[0]?.title)
} catch (e) {
  results.D2.error = e.message
  console.log('✗ D.2 失败:', e.message)
}

// ===== D.3 财务打款闭环 =====
try {
  console.log('\n=== D.3 财务打款闭环 ===')
  const applicantToken2 = await login(ADMIN_PHONE)
  console.log('✓ 报销人登录 (admin)')
  const submit = await authReq('POST', '/minip/enterprise/expenses', applicantToken2, {
    record_no: 'D3EXP' + Date.now(),
    expense_date: '2026-07-13',
    category: 'travel',
    amount: 500,
    payment_method: 'bank',
    description: 'D.3 报销测试',
    payee: '供应商A'
  })
  console.log('提交报销:', submit.status, JSON.stringify(submit.body))
  if (submit.body.code !== 0) throw new Error('报销提交失败')
  const expId = submit.body.data.id

  const reviewerToken = await login(ADMIN_PHONE)
  const review = await authReq('PUT', `/minip/enterprise/expenses/${expId}/review`, reviewerToken, {
    action: 'approve'
  })
  console.log('审批:', review.status, JSON.stringify(review.body))

  await new Promise(r => setTimeout(r, 500))
  const [walletRows] = await db.query(
    `SELECT * FROM minip_wallet_transactions WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND) AND source_id = ? ORDER BY id DESC LIMIT 1`,
    [expId]
  )
  const [notifRows2] = await db.query(
    `SELECT * FROM notifications WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND) AND type = 'expense_approved' ORDER BY id DESC LIMIT 1`
  )
  results.D3 = {
    submit: submit.body.code === 0 ? 'OK' : 'FAIL',
    review: review.status,
    reviewMsg: review.body.message,
    walletTransactionCreated: walletRows.length > 0,
    walletRecord: walletRows[0] || null,
    notificationCreated: notifRows2.length > 0,
    notification: notifRows2[0] || null
  }
  console.log('✓ 钱包流水创建:', walletRows.length > 0, walletRows[0]?.amount)
  console.log('✓ 通知创建:', notifRows2.length > 0, notifRows2[0]?.title)
} catch (e) {
  results.D3.error = e.message
  console.log('✗ D.3 失败:', e.message)
}

console.log('\n========= 最终结果 =========')
console.log(JSON.stringify(results, null, 2))
await db.end()
