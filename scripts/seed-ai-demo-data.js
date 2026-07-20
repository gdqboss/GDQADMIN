#!/usr/bin/env node
/**
 * AI 监督系统 demo 数据灌入器 v2（schema 校正）
 * 2026-07-18 02:25
 *
 * 真实 schema（刚才 DESCRIBE 验证）：
 *   users: phone/name/role/department/password
 *   worker_profiles: user_id/skills(longtext)/skill_level(enum)/hourly_rate/efficiency_score/quality_score/impact_score
 *   work_logs: user_id/date/today_work/tomorrow_plan/issues/log_type(enum work/complaint/share)/status
 *   jobsites: id/name/address/manager_id/status
 *   minip_employees: ?
 *   minip_hr_payroll: ?
 *   support_tickets: ?
 *   minip_wallet_transactions: ?
 *   minip_activities: ?
 *
 * 设计原则：必须长可运行 + 异常样本混入 + 全是真实业务场景。
 */
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
import bcrypt from 'bcryptjs';

dotenv.config();
const db = await mysql.createConnection({
  host: process.env.DB_HOST || '127.0.0.1',
  port: +(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'gdq',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'gdq',
});
console.log('📦 AI 监督 demo 数据灌入 v2 (schema 已校)\n');

// ===== helper =====
async function checkColumn(table, col) {
  const [rows] = await db.query(
    `SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?`,
    [table, col]
  );
  return rows.length > 0;
}

async function getOrCreateUser(phone, name, role = 'member', dept = null) {
  const [existing] = await db.query('SELECT id FROM users WHERE phone = ?', [phone]);
  if (existing.length > 0) return existing[0].id;
  const hash = await bcrypt.hash('123456', 10);
  const [r] = await db.query(
    'INSERT INTO users (phone, email, password, name, role, department, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
    [phone, `${phone}@demo.local`, hash, name, role, dept, 'active']
  );
  return r.insertId;
}

// ============ 阶段 1：3 个 jobsites ============
const jobsiteData = [
  { name: '横琴湾 1 号楼', address: '珠海横琴湾路 1 号' },
  { name: '前海花园 3 期', address: '深圳前海路 88 号' },
  { name: '万象城装修', address: '杭州万象城 A 座' },
];

for (const j of jobsiteData) {
  const code = 'JS' + Math.random().toString(36).slice(2, 8).toUpperCase();
  await db.query(
    `INSERT INTO jobsites (code, name, address, manager_user_id, status, type, start_date)
     SELECT ?, ?, ?, 9, 'active', 'decoration', CURDATE()
     FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM jobsites WHERE name = ?)`,
    [code, j.name, j.address, j.name]
  );
}
const [allJobsites] = await db.query('SELECT id FROM jobsites ORDER BY id');
console.log(`  ✅ jobsites: ${allJobsites.length} 个`);

// ============ 阶段 2：5 个工人 ============
const workerData = [
  { phone: '13800000001', name: '张建国', skill_level: 'master', skill: ['木工', '砌墙', '装修'], years: 8, hourly: 80 },
  { phone: '13800000002', name: '李志强', skill_level: 'master', skill: ['水电', '管道'], years: 12, hourly: 95 },
  { phone: '13800000003', name: '王福生', skill_level: 'junior', skill: ['搬运', '清理'], years: 3, hourly: 50 },
  { phone: '13800000004', name: '赵春梅', skill_level: 'senior', skill: ['油漆', '粉刷'], years: 5, hourly: 65 },
  { phone: '13800000005', name: '钱多多', skill_level: 'rookie', skill: ['杂工'], years: 1, hourly: 40 },
];

const workerIds = [];
for (const w of workerData) {
  const userId = await getOrCreateUser(w.phone, w.name, 'Worker');
  workerIds.push(userId);
  // worker_profiles ON DUPLICATE KEY UPDATE（user_id UNIQUE）
  await db.query(
    `INSERT INTO worker_profiles (user_id, skills, skill_level, hourly_rate, payment_type,
       efficiency_score, quality_score, impact_score, employment_status, hired_at)
     VALUES (?, ?, ?, ?, 'hourly', ?, ?, ?, 'active', DATE_SUB(CURDATE(), INTERVAL ? DAY))
     ON DUPLICATE KEY UPDATE
       skills = VALUES(skills),
       skill_level = VALUES(skill_level),
       hourly_rate = VALUES(hourly_rate),
       efficiency_score = VALUES(efficiency_score),
       quality_score = VALUES(quality_score),
       impact_score = VALUES(impact_score)`,
    [
      userId,
      JSON.stringify(w.skill),
      w.skill_level,
      w.hourly,
      70 + Math.floor(Math.random() * 25),  // efficiency 70-95
      70 + Math.floor(Math.random() * 25),  // quality 70-95
      60 + Math.floor(Math.random() * 30),  // impact 60-90
      Math.floor(Math.random() * 1800) + 90,
    ]
  );
  console.log(`  ✅ worker: ${w.name} (${w.skill_level} · ¥${w.hourly}/h · id=${userId})`);
}

// ============ 阶段 3：work_logs（5 天 × 5 工人） ============
const logTypes = ['work', 'complaint', 'share'];
const contentTemplates = {
  work: today => `今天在 jobsite 完成了 ${today} 工作，进度符合预期。`,
  complaint: () => '材料到场晚了 2 小时，影响下午排程。',
  share: () => '建议统一使用 XX 品牌的钉子，质量更稳定。',
};
let logsCreated = 0;
for (let day = 0; day < 5; day++) {
  for (const workerId of workerIds) {
    if (Math.random() < 0.85) {
      const type = logTypes[Math.floor(Math.random() * 3)];
      const status = Math.random() < 0.85 ? 'submitted' : 'approved';
      const today = ['砌墙', '水电', '搬运', '油漆', '清理'][Math.floor(Math.random() * 5)];
      await db.query(
        `INSERT INTO work_logs (user_id, date, today_work, tomorrow_plan, issues, log_type, status, submit_date)
         VALUES (?, DATE_SUB(CURDATE(), INTERVAL ? DAY), ?, ?, ?, ?, ?, DATE_SUB(CURDATE(), INTERVAL ? DAY))`,
        [
          workerId,
          day,
          contentTemplates[type](today),
          '继续按计划推进。',
          type === 'complaint' ? '材料到场延迟。' : null,
          type,
          status,
          day,
        ]
      );
      logsCreated++;
    }
  }
}
console.log(`  ✅ work_logs: ${logsCreated} 条（5 天 × 5 工人 × 85%）`);

// ============ 阶段 4：5 个员工 ============
const employeeData = [
  { phone: '13900000001', name: '陈晓静', role: 'admin', dept: '财务', base: 12000 },
  { phone: '13900000002', name: '黄大伟', role: 'member', dept: '人事', base: 9000 },
  { phone: '13900000003', name: '周敏',   role: 'member', dept: '营销', base: 10000 },
  { phone: '13900000004', name: '吴亮',   role: 'member', dept: '运营', base: 11000 },
  { phone: '13900000005', name: '郑佳',   role: 'member', dept: '工程', base: 14000 },
];

const employeeIds = [];
for (const e of employeeData) {
  const userId = await getOrCreateUser(e.phone, e.name, e.role, e.dept);
  employeeIds.push({ userId, dept: e.dept, base: e.base });
  console.log(`  ✅ employee: ${e.name} (${e.dept}, ¥${e.base}/月, id=${userId})`);
}

// ============ 阶段 5：minip_employees + payroll ============
for (const e of employeeIds) {
  await db.query(
    `INSERT INTO minip_employees (user_id, employee_code, employee_name, department, position, status, hired_at)
     SELECT ?, CONCAT('EMP', LPAD(?, 4, '0')), u.name, ?, ?, 'active', DATE_SUB(CURDATE(), INTERVAL ? DAY)
     FROM users u WHERE u.id = ?
     ON DUPLICATE KEY UPDATE department = VALUES(department), position = VALUES(position)`,
    [e.userId, e.userId, e.dept, e.dept + '_staff', Math.floor(Math.random() * 800) + 60, e.userId]
  );

  // payroll（按 period）
  const currentPeriod = new Date().toISOString().slice(0, 7);
  await db.query(
    `INSERT INTO minip_hr_payroll (user_id, period, base_salary, bonus, deduction, net_salary, paid_at, status)
     VALUES (?, ?, ?, ?, 0, ?, NOW(), 'paid')
     ON DUPLICATE KEY UPDATE paid_at = NOW()`,
    [e.userId, currentPeriod, e.base, Math.floor(Math.random() * 2000), e.base + Math.floor(Math.random() * 2000)]
  );

  // wallet 3 笔（type 必填）
  for (let i = 0; i < 3; i++) {
    const isIncome = Math.random() < 0.4;
    await db.query(
      `INSERT INTO minip_wallet_transactions (user_id, type, amount, balance_after, source_type, remark, created_at)
       VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))`,
      [
        e.userId,
        isIncome ? 'income' : 'expense',
        (isIncome ? 1 : -1) * (Math.floor(Math.random() * 500) + 50),
        Math.floor(Math.random() * 5000),
        isIncome ? 'payroll' : 'expense_reimburse',
        ['午餐报销', '交通', '奖金', '团建', '采购'][i],
        i * 5,
      ]
    );
  }
}
console.log(`  ✅ minip_employees + payroll + wallet: ${employeeIds.length} 员工`);

// ============ 阶段 6：10 条工单（模拟报销/请假，含 3 条异常） ============
const ticketTypes = ['expense_reimburse', 'leave_request', 'purchase_apply'];
const anomalyReasons = ['接近月度报销上限', '连续 3 次同类采购', '差旅金额异常高'];
let tickets = 0;
for (let i = 0; i < 10; i++) {
  const emp = employeeIds[Math.floor(Math.random() * employeeIds.length)];
  const isAnomaly = i < 3;
  await db.query(
    `INSERT INTO support_tickets (user_id, title, content, priority, status, created_at)
     VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))`,
    [
      emp.userId,
      ticketTypes[i % 3] === 'expense_reimburse' ? `${emp.dept}报销单 #${1000 + i}` :
        ticketTypes[i % 3] === 'leave_request' ? `请假申请 #${1000 + i}` :
        `采购申请 #${1000 + i}`,
      isAnomaly ? `⚠️ 异常标记：${anomalyReasons[i]}` : '常规申请',
      isAnomaly ? 'high' : 'normal',
      ['open', 'processing', 'closed'][i % 3],
      i * 2,
    ]
  );
  tickets++;
}
console.log(`  ✅ support_tickets: ${tickets} 条（含 3 条 high priority 异常）`);

// ============ 阶段 7：3 个营销活动 ============
const acts = [
  { title: '夏季装修满减 1000', max: 100 },
  { title: '老客户推荐返现 200', max: 50 },
  { title: '七月大促全场 8 折', max: 200 },
];
for (const a of acts) {
  await db.query(
    `INSERT INTO minip_activities (title, description, max_participants, current_participants, start_date, end_date, status, enabled)
     SELECT ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'published', 1
     FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM minip_activities WHERE title = ?)`,
    [a.title, 'AI 自动生成文案：' + a.title, a.max, Math.floor(Math.random() * 20), a.title]
  );
}
console.log(`  ✅ minip_activities: ${acts.length} 个`);

// ============ 阶段 8：5 条 ai_worker_events ============
const events = [
  { type: 'work_log_submitted', data: { user_id: workerIds[0], today: '木工收尾', rating: 'positive' } },
  { type: 'attendance_anomaly', data: { user_id: workerIds[2], issue: '迟到 3 次/周' } },
  { type: 'quality_below_threshold', data: { user_id: workerIds[4], score: 58, threshold: 60 } },
  { type: 'cross_module_request', data: { from: 'labor', to: 'finance', content: '加班工资审批' } },
  { type: 'risk_alert', data: { user_id: workerIds[3], risk: '中度：连续高强度 7 天' } },
];
for (const ev of events) {
  await db.query(
    `INSERT INTO ai_worker_events (user_id, event_type, event_data, source_table, source_id)
     VALUES (?, ?, ?, 'system', NULL)`,
    [workerIds[Math.floor(Math.random() * workerIds.length)], ev.type, JSON.stringify(ev.data)]
  );
}
console.log(`  ✅ ai_worker_events: ${events.length} 条（含跨模块请求）`);

await db.end();
console.log('\n🎉 全部 8 阶段完成！现在跑：');
console.log('  curl -X POST localhost:3200/api/minip-ai/audit/expenses-deep');
console.log('  curl -X POST localhost:3200/api/minip-ai/brain/weekly-report');