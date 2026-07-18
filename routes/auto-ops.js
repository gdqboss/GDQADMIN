/**
 * AutoOps — 老板日常工作 AI 化
 * 老板说一句话 → AI 自动执行
 *
 * 不烧 token：纯规则 + lib 调用
 * 降级：所有命令 catch 异常，返失败原因（不挂）
 */
import express from 'express';
const router = express.Router();
import { execSync } from 'child_process';
import { requirePermission } from '../middleware/rbac.js';

const PM2 = '/root/.nvm/versions/node/v22.22.2/bin/pm2';

// 安全白名单（只允许以下命令）
const SAFE_COMMANDS = {
  'pm2:status': '/root/.nvm/versions/node/v22.22.2/bin/pm2 jlist 2>/dev/null | head -100',
  'pm2:restart-gdq': '/root/.nvm/versions/node/v22.22.2/bin/pm2 restart gdq-server',
  'pm2:logs': '/root/.nvm/versions/node/v22.22.2/bin/pm2 logs gdq-server --lines 30 --nostream --no-color 2>&1 | tail -40',
  'disk:usage': 'df -h / | tail -1',
  'memory:usage': 'free -h | head -3',
  'cpu:load': 'uptime',
  'db:tables': "sqlite3 /root/server/data/gdqshop.db \".tables\" 2>/dev/null | head -20",
  'db:health-supervision': "sqlite3 /root/server/data/gdqshop.db \"SELECT COUNT(*) as n FROM ai_worker_profiles; SELECT COUNT(*) FROM weekly_reports;\" 2>/dev/null",
  'cron:list': 'crontab -l 2>/dev/null',
  'server:uptime': "ps -o etime= -p $(pgrep -f 'gdq-server' | head -1) 2>/dev/null",
  'logs:tail-error': 'tail -30 /root/server/logs/error.log 2>/dev/null || echo "no error log"',
  'logs:tail-out': 'tail -30 /root/server/logs/out.log 2>/dev/null || echo "no out log"',
  'health:deep': 'curl -s http://localhost:3200/api/health/deep',
  'weekly-report:run': 'bash /root/scripts/cron-ai-weekly-report.sh 2>&1 | tail -20',
  'health-monitor:run': 'bash /root/scripts/cron-health-monitor.sh 2>&1 | tail -20',
};

function safeExec(cmd, timeoutMs = 10000) {
  try {
    return execSync(cmd, { timeout: timeoutMs, encoding: 'utf8', maxBuffer: 1024 * 1024 });
  } catch (e) {
    return `[执行出错] ${e.message.split('\n')[0]}`;
  }
}

// 老板命令关键词 → action
function parseIntent(prompt) {
  const p = (prompt || '').toLowerCase();
  if (p.includes('重启') || p.includes('restart')) return 'pm2:restart-gdq';
  if (p.includes('pm2') || p.includes('进程')) return 'pm2:status';
  if (p.includes('日志') && p.includes('错误')) return 'logs:tail-error';
  if (p.includes('日志')) return 'logs:tail-out';
  if (p.includes('磁盘') || p.includes('disk')) return 'disk:usage';
  if (p.includes('内存') || p.includes('memory')) return 'memory:usage';
  if (p.includes('负载') || p.includes('cpu') || p.includes('load')) return 'cpu:load';
  if (p.includes('表') || p.includes('tables')) return 'db:tables';
  if (p.includes('健康') || p.includes('health')) return 'health:deep';
  if (p.includes('周报')) return 'weekly-report:run';
  if (p.includes('自监控') || p.includes('健康检查')) return 'health-monitor:run';
  if (p.includes('跑了多久') || p.includes('uptime') || p.includes('在线多久')) return 'server:uptime';
  if (p.includes('cron')) return 'cron:list';
  return null;
}

// ================== 主 endpoint ==================
router.post('/chat', requirePermission('auto-ops:write'), async (req, res) => {
  try {
    const { prompt } = req.body || {};
    if (!prompt) return res.status(400).json({ success: false, error: 'prompt required' });

    const intent = parseIntent(prompt);
    if (!intent) {
      return res.json({
        success: true,
        bot: `🤖 **我能帮你做的事**（直接问）：\n\n` +
             `- "重启 server" / "看 pm2 状态"\n` +
             `- "看错误日志" / "看运行日志"\n` +
             `- "磁盘占用" / "内存使用" / "CPU 负载"\n` +
             `- "DB 有哪些表" / "健康检查" / "跑自监控"\n` +
             `- "跑周报" / "cron 列表" / "server 跑了多久"\n\n` +
             `纯本地执行，0 token。`,
        action: 'help',
      });
    }

    const cmd = SAFE_COMMANDS[intent];
    const output = safeExec(cmd);

    const actionLabels = {
      'pm2:restart-gdq': '🔄 重启 server',
      'pm2:status': '📊 PM2 状态',
      'logs:tail-error': '📕 错误日志（最近 30 行）',
      'logs:tail-out': '📗 运行日志（最近 30 行）',
      'disk:usage': '💾 磁盘占用',
      'memory:usage': '🧠 内存使用',
      'cpu:load': '⚡ CPU 负载',
      'db:tables': '🗄️ DB 表',
      'health:deep': '🏥 深度健康检查',
      'weekly-report:run': '📋 跑 AI 周报',
      'health-monitor:run': '🩺 跑自监控',
      'server:uptime': '⏱️ Server 运行时长',
      'cron:list': '📅 Cron 列表',
    };

    return res.json({
      success: true,
      bot: `${actionLabels[intent] || intent}\n\n\`\`\`\n${output.trim()}\n\`\`\``,
      action: intent,
      executed: true,
    });
  } catch (e) {
    return res.status(500).json({ success: false, error: e.message });
  }
});

// 列出所有可用命令（给前端 dropdown 用）
router.get('/commands', requirePermission('auto-ops:read'), (req, res) => {
  res.json({
    success: true,
    commands: Object.keys(SAFE_COMMANDS).map(k => ({
      key: k,
      label: k.replace(/:/g, ' / '),
    })),
  });
});

export default router;
