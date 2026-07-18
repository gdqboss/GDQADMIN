/**
 * minip-ai-brain.js —— 跨模块 AI 大脑
 * 2026-07-18 02:32 真写
 */
import { Router } from 'express';
import { pool } from '../db/connection.js';
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js';

const router = Router();
const READ = requirePermission(PERMISSIONS.AI_SUPERVISION_READ);
const WRITE = requirePermission(PERMISSIONS.AI_SUPERVISION_WRITE);

// 全公司周报
router.post('/brain/weekly-report', WRITE, async (req, res) => {
  try {
    const [workerCount] = await pool.query("SELECT COUNT(*) as cnt FROM users WHERE role = 'Worker'");
    const [workerLogs] = await pool.query("SELECT COUNT(*) as cnt FROM work_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    const [empCount] = await pool.query("SELECT COUNT(*) as cnt FROM minip_employees WHERE status = 'active'");
    const [[payroll]] = await pool.query("SELECT SUM(net_salary) as total FROM minip_hr_payroll WHERE period = DATE_FORMAT(CURDATE(), '%Y-%m')");
    const [[tickets]] = await pool.query("SELECT COUNT(*) as cnt, SUM(CASE WHEN priority='high' THEN 1 ELSE 0 END) as high FROM support_tickets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    const [[acts]] = await pool.query("SELECT COUNT(*) as cnt FROM minip_activities WHERE status = 'published' OR status = 'ongoing'");

    const insights = [
      workerCount[0].cnt === 0 ? '⚠️ 工人画像缺失，建议引导工人完善资料' : `✅ 有 ${workerCount[0].cnt} 个工人画像`,
      tickets.high > 0 ? `⚠️ 本周 ${tickets.high} 条 high 优先级工单需要审批` : '✅ 本周无 high 优先级异常',
      Number(payroll.total || 0) > 50000 ? `💰 本月工资支出 ¥${payroll.total}，财务需关注` : `💼 本月工资支出 ¥${payroll.total || 0}，正常范围内`,
      acts.cnt === 0 ? '📢 当前无活跃营销活动' : `📢 ${acts.cnt} 个活动进行中`,
    ];

    const ai_recommendations = [
      workerCount[0].cnt > 0 ? '基于工人画像智能排班' : null,
      tickets.high > 0 ? '触发 high 工单自动审批流' : null,
      '周日晚 8 点自动生成下周 OKR',
    ].filter(Boolean);

    const stats = {
      workers: workerCount[0].cnt,
      work_logs_7d: workerLogs[0].cnt,
      active_employees: empCount[0].cnt,
      monthly_payroll: Number(payroll.total || 0),
      tickets_7d: tickets.cnt,
      high_priority_tickets: tickets.high,
      active_campaigns: acts.cnt,
    };

    // 自动存表
    const period = new Date().toISOString().slice(0, 10);
    try {
      await pool.query(
        'INSERT INTO weekly_reports (period, stats_json, insights_json) VALUES (?, ?, ?)',
        [period, JSON.stringify(stats), JSON.stringify(insights)]
      );
    } catch (e) { console.error('[brain/save]', e.message); }

    res.json({
      code: 0,
      data: {
        generated_at: new Date().toISOString(),
        week: `2026-W${Math.ceil((new Date() - new Date(new Date().getFullYear(), 0, 1)) / (1000*60*60*24*7))}`,
        stats,
        insights,
        ai_recommendations,
      }
    });
  } catch (err) {
    console.error('[brain/weekly-report]', err);
    res.json({ code: 500, message: err.message });
  }
});

// 全公司智能问答
router.post('/brain/ask', WRITE, async (req, res) => {
  const { question } = req.body;
  try {
    const q = (question || '').toLowerCase();
    let answer = '请换个问法试试（支持：花了多少/工人多少/营销多少/本周工单）';

    if (q.includes('花了') || q.includes('支出') || q.includes('工资')) {
      const [[r]] = await pool.query("SELECT SUM(net_salary) as total FROM minip_hr_payroll WHERE period = DATE_FORMAT(CURDATE(), '%Y-%m')");
      answer = `本月发了 ¥${r.total || 0} 工资`;
    } else if (q.includes('工人') || q.includes('师傅')) {
      const [[r]] = await pool.query("SELECT COUNT(*) as cnt FROM worker_profiles");
      answer = `当前 ${r.cnt} 个工人`;
    } else if (q.includes('活动') || q.includes('营销')) {
      const [[r]] = await pool.query("SELECT COUNT(*) as cnt FROM minip_activities WHERE status IN ('published','ongoing')");
      answer = `${r.cnt} 个活动进行中`;
    } else if (q.includes('工单') || q.includes('待办')) {
      const [[r]] = await pool.query("SELECT COUNT(*) as cnt FROM support_tickets WHERE status = 'open'");
      answer = `${r.cnt} 条 open 工单`;
    }

    res.json({ code: 0, data: { question, answer, by: 'brain-v2' } });
  } catch (err) {
    res.json({ code: 500, message: err.message });
  }
});

// 主动预警
router.get('/brain/alerts', READ, async (req, res) => {
  try {
    const alerts = [];
    // 检查 high 优先级工单
    const [highTickets] = await pool.query("SELECT id, title, user_id FROM support_tickets WHERE priority = 'high' AND status = 'open'");
    for (const t of highTickets) {
      alerts.push({ id: alerts.length + 1, type: 'finance_high_pending', severity: 'medium', title: t.title, ticket_id: t.id });
    }
    // 检查工人数据缺失
    const [[missWorker]] = await pool.query("SELECT COUNT(*) as cnt FROM users WHERE role = 'Worker'");
    if (missWorker.cnt < 5) {
      alerts.push({ id: alerts.length + 1, type: 'data_insufficient', severity: 'low', title: `只有 ${missWorker.cnt} 个工人，建议引导工人完善资料` });
    }
    res.json({ code: 0, data: { alert_count: alerts.length, alerts } });
  } catch (err) {
    res.json({ code: 500, message: err.message });
  }
});

// 历史周报查询
router.get('/brain/reports', async (req, res) => {
  try {
    const [rows] = await pool.query(
      'SELECT id, period, stats_json, insights_json, generated_at FROM weekly_reports ORDER BY id DESC LIMIT 20'
    );
    const reports = rows.map(r => ({
      ...r,
      stats: r.stats_json ? JSON.parse(r.stats_json) : null,
      insights: r.insights_json ? JSON.parse(r.insights_json) : null,
    }));
    res.json({ code: 0, data: { total: rows.length, reports } });
  } catch (err) {
    res.json({ code: 500, message: err.message });
  }
});

// 跨模块洞察
router.get('/brain/insights', async (req, res) => {
  try {
    const [[workers]] = await pool.query("SELECT COUNT(*) as cnt, AVG(efficiency_score) as avg_eff FROM worker_profiles");
    const [[emps]] = await pool.query("SELECT COUNT(*) as cnt FROM minip_employees WHERE status = 'active'");
    const [[budget]] = await pool.query("SELECT SUM(net_salary) as total FROM minip_hr_payroll WHERE period = DATE_FORMAT(CURDATE(), '%Y-%m')");
    res.json({
      code: 0,
      data: {
        workers_count: workers.cnt,
        avg_worker_efficiency: Number(workers.avg_eff || 0).toFixed(1),
        employees_count: emps.cnt,
        monthly_budget: Number(budget.total || 0),
        insight: `全公司人效比 ≈ ${(Number(workers.avg_eff || 0) / 100 * Number(emps.cnt)).toFixed(1)}（工人效率 × 员工数）`,
      }
    });
  } catch (err) {
    res.json({ code: 500, message: err.message });
  }
});

export default router;
