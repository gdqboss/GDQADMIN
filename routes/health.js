/**
 * 系统健康监控 + 自恢复
 * 路径: /api/system-health/*
 * 每 5 分钟被 cron 调一次
 */
import express from 'express';
const router = express.Router();
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { requirePermission } from '../middleware/rbac.js';
import { pool } from '../db/connection.js';
// --- 独立检查项 ---
async function checkServer() {
  return { name: 'server', status: 'alive', uptime: process.uptime() };
}

async function checkDatabase(pool) {
  try {
    const [rows] = await pool.query('SELECT 1 as ok, NOW() as now, DATABASE() as db');
    const [tables] = await pool.query("SELECT COUNT(*) as n FROM information_schema.tables WHERE table_schema = DATABASE()");
    return {
      name: 'database',
      status: 'healthy',
      db: rows[0].db,
      serverTime: rows[0].now,
      tables: tables[0].n,
    };
  } catch (e) {
    return { name: 'database', status: 'error', error: e.message };
  }
}

function checkDisk() {
  try {
    const out = execSync('df -h / | tail -1', { encoding: 'utf8' }).trim();
    const parts = out.split(/\s+/);
    const usePct = parseInt(parts[4]);
    return {
      name: 'disk',
      status: usePct > 90 ? 'critical' : usePct > 80 ? 'warning' : 'healthy',
      used: parts[2],
      total: parts[1],
      usePercent: usePct + '%',
      raw: out,
    };
  } catch (e) {
    return { name: 'disk', status: 'error', error: e.message };
  }
}

function checkMemory() {
  const m = process.memoryUsage();
  const heapUsedMB = Math.round(m.heapUsed / 1024 / 1024);
  const heapTotalMB = Math.round(m.heapTotal / 1024 / 1024);
  const rssMB = Math.round(m.rss / 1024 / 1024);
  return {
    name: 'memory',
    status: heapUsedMB > 200 ? 'warning' : 'healthy',
    heapUsedMB,
    heapTotalMB,
    rssMB,
    usePercent: Math.round(heapUsedMB / heapTotalMB * 100) + '%',
  };
}

async function checkCronLastRun(pool) {
  try {
    const [rows] = await pool.query(
      "SELECT id, period, generated_at FROM weekly_reports ORDER BY generated_at DESC LIMIT 3"
    );
    return {
      name: 'cron',
      status: rows.length > 0 ? 'healthy' : 'warning',
      lastWeeklyReport: rows[0]?.generated_at || null,
      recent: rows.map(r => ({
        id: r.id,
        period: r.period,
        generated_at: r.generated_at,
      })),
    };
  } catch (e) {
    return { name: 'cron', status: 'error', error: e.message };
  }
}

async function checkAPIEndpoints() {
  // 简化：只标记已检查，避免 curl 自调死锁
  return {
    name: 'endpoints',
    status: 'checked',
    note: 'deep self-check 已跳过以避免 in-flight 死锁',
  };
}

// --- 自动恢复 ---
function autoRecover() {
  const actions = [];
  // 1. 清理 7 天前日志
  try {
    const logDir = '/root/server/logs';
    if (fs.existsSync(logDir)) {
      const files = fs.readdirSync(logDir);
      const oldLogs = files.filter(f => /\.(log|out)$/.test(f));
      for (const f of oldLogs) {
        const fp = path.join(logDir, f);
        const stat = fs.statSync(fp);
        if (Date.now() - stat.mtimeMs > 7 * 86400 * 1000 && stat.size > 100 * 1024 * 1024) {
          fs.truncateSync(fp, 0);
          actions.push(`清空老日志 ${f} (${Math.round(stat.size / 1024 / 1024)}MB)`);
        }
      }
    }
  } catch (e) {
    actions.push(`日志清理失败: ${e.message.split('\n')[0]}`);
  }
  // 2. 检查 pm2 进程在不在
  try {
    const pm2 = '/root/.nvm/versions/node/v22.22.2/bin/pm2';
    const out = execSync(`${pm2} jlist 2>/dev/null`, { encoding: 'utf8' });
    const procs = JSON.parse(out);
    const gdq = procs.find(p => p.name === 'gdq-server');
    if (!gdq || gdq.pm2_env.status !== 'online') {
      execSync(`${pm2} restart gdq-server 2>&1`, { encoding: 'utf8' });
      actions.push('重启 gdq-server');
    } else {
      actions.push('gdq-server 在线，无需重启');
    }
  } catch (e) {
    actions.push(`pm2 检查失败: ${e.message.split('\n')[0]}`);
  }
  // 3. disk critical → 触发白名单清理
  try {
    const disk = checkDisk();
    if (disk.status === 'critical' || disk.status === 'warning') {
      const out = execSync('bash /root/scripts/cron-disk-cleanup.sh 2>&1', { encoding: 'utf8', timeout: 30000 });
      actions.push(`磁盘清理: ${out.trim().substring(0, 200)}`);
    }
  } catch (e) {
    actions.push(`磁盘清理失败: ${e.message.split('\n')[0]}`);
  }
  return { success: actions.length, actions };
}

// --- 主 endpoint ---
router.get('/deep', requirePermission('auto-ops:read'), async (req, res) => {
  try {
    const [server, db, disk, memory, cron, endpoints] = await Promise.all([
      checkServer(),
      checkDatabase(pool),
      Promise.resolve(checkDisk()),
      Promise.resolve(checkMemory()),
      checkCronLastRun(pool),
      Promise.resolve(checkAPIEndpoints()),
    ]);
    const checks = { server, database: db, disk, memory, cron, endpoints };
    const critical = Object.values(checks).filter(c => c.status === 'critical' || c.status === 'error').length;
    const warning = Object.values(checks).filter(c => c.status === 'warning').length;
    const overall = critical > 0 ? 'critical' : warning > 0 ? 'warning' : 'healthy';
    res.json({
      success: true,
      overall,
      summary: { critical, warning, totalChecks: Object.keys(checks).length },
      checks,
      timestamp: new Date().toISOString(),
    });
  } catch (e) {
    res.status(500).json({ success: false, error: e.message });
  }
});

router.get('/quick', requirePermission('auto-ops:read'), async (req, res) => {
  res.json({
    success: true,
    server: 'alive',
    uptime: process.uptime(),
    memory: checkMemory(),
    timestamp: new Date().toISOString(),
  });
});

router.post('/auto-recover', requirePermission('auto-ops:write'), async (req, res) => {
  try {
    const result = autoRecover();
    res.json({ success: true, ...result });
  } catch (e) {
    res.status(500).json({ success: false, error: e.message });
  }
});

export default router;
