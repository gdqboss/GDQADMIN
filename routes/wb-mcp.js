import { Router } from 'express'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// ===================== MCP Tool 注册表 =====================
// Phase-3: 将 WorkBuddy 全部接口封装为标准 MCP Tool schema，供原生 tool_use 调用。
// 每个工具通过 METHOD + PATH 映射到已有 /api/workbuddy/* 接口（复用正确实现，不另起灶）。
// execute 时内部转发本机 + 复用调用者的 Authorization（保留权限语义）。
const MCP_TOOLS = [
  // ── 经营总览 ──
  { name: 'get_dashboard_stats', description: 'WorkBuddy 仪表盘核心指标（库存/订单/商品/员工统计）', method: 'GET', path: '/stats', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_boss_todo_priorities', description: '老板今日待办优先级聚合（逾期任务/待审日志/待审批/低库存/未交日志）', method: 'GET', path: '/action/priorities', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_ai_suggestions', description: 'AI 智能经营建议（基于当前数据自动发现待办/风险）', method: 'GET', path: '/action/suggestions', inputSchema: { type: 'object', properties: {} } },

  // ── 库存 ──
  { name: 'get_inventory_summary', description: '库存总览（各仓库数量/预警数）', method: 'GET', path: '/inventory/summary', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_inventory_alerts', description: '库存预警列表（低于预警线的商品）', method: 'GET', path: '/inventory/alerts', inputSchema: { type: 'object', properties: {} } },
  { name: 'search_inventory', description: '按 SKU/品名搜索库存', method: 'GET', path: '/inventory/check', inputSchema: { type: 'object', properties: { q: { type: 'string', description: '搜索关键词' } }, required: ['q'] } },
  { name: 'get_warehouse_inventory', description: '指定仓库的库存明细', method: 'GET', path: '/inventory/warehouse/:id', inputSchema: { type: 'object', properties: { id: { type: 'number', description: '仓库 ID' } }, required: ['id'] } },

  // ── 订单 ──
  { name: 'get_orders_summary', description: '订单统计（今日/本周/本月订单数与销售额）', method: 'GET', path: '/orders/summary', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_recent_orders', description: '最新订单列表', method: 'GET', path: '/orders/recent', inputSchema: { type: 'object', properties: { limit: { type: 'number', description: '数量，默认10' } } } },
  { name: 'get_order_detail', description: '订单详情（含订单明细）', method: 'GET', path: '/orders/:id', inputSchema: { type: 'object', properties: { id: { type: 'number', description: '订单 ID' } }, required: ['id'] } },
  { name: 'search_orders', description: '按关键词搜索订单', method: 'GET', path: '/orders/search', inputSchema: { type: 'object', properties: { q: { type: 'string', description: '搜索关键词' } }, required: ['q'] } },

  // ── 商品 ──
  { name: 'get_products_summary', description: '商品总览（总数/上架/低库存预警）', method: 'GET', path: '/products/summary', inputSchema: { type: 'object', properties: {} } },
  { name: 'list_products', description: '商品分页列表', method: 'GET', path: '/products/list', inputSchema: { type: 'object', properties: { page: { type: 'number', description: '页码，默认1' }, limit: { type: 'number', description: '每页数量，默认20' } } } },
  { name: 'search_products', description: '搜索商品（SKU/品名/分类）', method: 'GET', path: '/products/search', inputSchema: { type: 'object', properties: { q: { type: 'string', description: '搜索关键词' } }, required: ['q'] } },
  { name: 'get_product_detail', description: '商品详情（含各仓库库存分布）', method: 'GET', path: '/products/:id', inputSchema: { type: 'object', properties: { id: { type: 'number', description: '商品 ID' } }, required: ['id'] } },

  // ── 仓库 ──
  { name: 'list_warehouses', description: '仓库列表', method: 'GET', path: '/warehouses', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_warehouse_detail', description: '仓库详情（含库存汇总）', method: 'GET', path: '/warehouses/:id', inputSchema: { type: 'object', properties: { id: { type: 'number', description: '仓库 ID' } }, required: ['id'] } },

  // ── 审批 ──
  { name: 'get_pending_approvals', description: '待审批列表', method: 'GET', path: '/approvals/pending', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_approval_history', description: '最近审批记录', method: 'GET', path: '/approvals/recent', inputSchema: { type: 'object', properties: { limit: { type: 'number', description: '数量，默认10' } } } },
  { name: 'approve_request', description: '审批通过（需 confirm=true）', method: 'POST', path: '/approvals/:id/approve', inputSchema: { type: 'object', properties: { id: { type: 'number', description: '审批 ID' }, confirm: { type: 'boolean', description: '二次确认，必须为 true' } }, required: ['id', 'confirm'] } },
  { name: 'reject_request', description: '审批拒绝（需 confirm=true）', method: 'POST', path: '/approvals/:id/reject', inputSchema: { type: 'object', properties: { id: { type: 'number', description: '审批 ID' }, confirm: { type: 'boolean', description: '二次确认，必须为 true' } }, required: ['id', 'confirm'] } },

  // ── 财务 ──
  { name: 'get_finance_overview', description: '财务总览（今日/本月营收、待审批金额、财务提醒）', method: 'GET', path: '/finance/overview', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_finance_recent', description: '最新收支记录（费用/应收/应付）', method: 'GET', path: '/finance/recent', inputSchema: { type: 'object', properties: { limit: { type: 'number', description: '数量，默认15' } } } },
  { name: 'get_finance_reminders', description: '财务提醒列表', method: 'GET', path: '/finance/reminders', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_sales_report', description: '销售报表（近7日销售统计）', method: 'GET', path: '/reports/sales', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_daily_report', description: '日报（某日销售/日志/考勤/任务/知识动态）', method: 'GET', path: '/reports/daily', inputSchema: { type: 'object', properties: { date: { type: 'string', description: '日期 YYYY-MM-DD，默认今天' } } } },
  { name: 'get_weekly_report', description: '周报（近7日销售趋势/汇总）', method: 'GET', path: '/reports/weekly', inputSchema: { type: 'object', properties: {} } },

  // ── 考勤 ──
  { name: 'get_attendance_summary', description: '今日考勤概览（出勤/迟到/早退/待请假/待加班）', method: 'GET', path: '/attendance/summary', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_attendance_pending', description: '待审批请假/加班/异常考勤', method: 'GET', path: '/attendance/pending', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_my_attendance', description: '我的考勤记录', method: 'GET', path: '/attendance/my', inputSchema: { type: 'object', properties: { limit: { type: 'number', description: '数量，默认10' } } } },

  // ── 任务 ──
  { name: 'get_tasks_summary', description: '任务统计（总/待办/进行中/已完成/逾期）', method: 'GET', path: '/tasks/summary', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_pending_tasks', description: '待办任务列表', method: 'GET', path: '/tasks/pending', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_overdue_tasks', description: '逾期任务列表', method: 'GET', path: '/tasks/overdue', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_task_detail', description: '任务详情', method: 'GET', path: '/tasks/:id', inputSchema: { type: 'object', properties: { id: { type: 'number', description: '任务 ID' } }, required: ['id'] } },

  // ── 工作日志 ──
  { name: 'get_logs_summary', description: '工作日志统计（今日提交/待审核/未提交人数）', method: 'GET', path: '/logs/summary', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_today_logs', description: '今日工作日志', method: 'GET', path: '/logs/today', inputSchema: { type: 'object', properties: {} } },
  { name: 'get_pending_logs', description: '待审核工作日志', method: 'GET', path: '/logs/pending', inputSchema: { type: 'object', properties: {} } },

  // ── AI 课堂 / 培训 ──
  { name: 'get_training_summary', description: 'AI 课堂培训概览（知识库规模/闪卡/知识缺口）', method: 'GET', path: '/training/summary', inputSchema: { type: 'object', properties: {} } },
  { name: 'search_training_kb', description: '检索 AI 课堂知识库（培训问答素材，45条知识）', method: 'GET', path: '/training/kb', inputSchema: { type: 'object', properties: { q: { type: 'string', description: '搜索关键词' }, doc_type: { type: 'string', description: '文档类型过滤' } }, required: ['q'] } },
  { name: 'get_training_kb_item', description: '获取知识条目全文', method: 'GET', path: '/training/kb/:id', inputSchema: { type: 'object', properties: { id: { type: 'number', description: '知识 ID' } }, required: ['id'] } },

  // ── 企业微信 ──
  { name: 'get_wecom_unread', description: '企微未读消息（会话级 + 最近来信）', method: 'GET', path: '/wecom/unread', inputSchema: { type: 'object', properties: {} } },
  { name: 'list_wecom_contacts', description: '企微联系人', method: 'GET', path: '/wecom/contacts', inputSchema: { type: 'object', properties: { q: { type: 'string', description: '搜索姓名/职位/手机' } } } },
  { name: 'list_wecom_conversations', description: '企微最近会话', method: 'GET', path: '/wecom/conversations', inputSchema: { type: 'object', properties: {} } },
  { name: 'send_wecom_message', description: '发送企微消息（需要 conversation_id 或 conversation_name）', method: 'POST', path: '/wecom/send', inputSchema: { type: 'object', properties: { conversation_id: { type: 'number', description: '会话 ID（二选一）' }, conversation_name: { type: 'string', description: '会话名（二选一，没有则自动新建）' }, content: { type: 'string', description: '消息内容' }, sender: { type: 'string', description: '发送者姓名，默认江清波' } }, required: ['content'] } },

  // ── 快捷操作 ──
  { name: 'create_stock_count', description: '发起库存盘点（创建盘点任务，事务写）', method: 'POST', path: '/action/stock-count', inputSchema: { type: 'object', properties: { warehouse_id: { type: 'number', description: '仓库 ID' }, note: { type: 'string', description: '备注' } }, required: ['warehouse_id'] } },
  { name: 'generate_restock_suggestions', description: '生成低库存补货建议（只读，不改库存）', method: 'POST', path: '/action/cleanup-low-stock', inputSchema: { type: 'object', properties: { limit: { type: 'number', description: '建议数量，默认10' } } } },
]

// GET /api/workbuddy/mcp/tools - 列出全部 MCP 工具 schema（WorkBuddy 注册用）
router.get('/mcp/tools', auth, requirePermission('workbuddy:read'), (req, res) => {
  res.json({
    protocol: 'mcp',
    server: 'smartbiz-workbuddy',
    tool_count: MCP_TOOLS.length,
    tools: MCP_TOOLS.map(({ name, description, inputSchema }) => ({ name, description, inputSchema })),
  })
})

// GET /api/workbuddy/openapi.json - OpenAPI 3.0 schema (给 agent 自动生成 client 用)
// 2026-09-10 江小鱼 — 波哥指令 "WorkBuddy API 可不可以其它 agent 也可以接人"
// 从 MCP_TOOLS 自动转 OpenAPI, 一处改同步 MCP + OpenAPI, 不另起灶.
// 任何 agent 拿到 /openapi.json 就能用 openapi-generator 生成 Python/TS/Rust client.
function buildOpenApiSchema() {
  const pathMap = {}
  for (const t of MCP_TOOLS) {
    // 把 /:id → /{id}, 保持 OpenAPI path template 风格
    const oaPath = t.path.replace(/:(\w+)/g, '{$1}')
    const opId = t.name
    const op = {
      operationId: opId,
      summary: t.description,
      description: t.description,
      tags: [t.path.split('/')[1] || 'misc'], // 业务域做 tag
      responses: {
        '200': { description: 'OK', content: { 'application/json': { schema: { type: 'object' } } } },
        '400': { description: '参数错误' },
        '401': { description: '未认证' },
        '403': { description: '无权限' },
        '404': { description: '资源不存在' },
      },
    }
    // parameters from inputSchema.properties (required / optional)
    const props = t.inputSchema?.properties || {}
    const required = t.inputSchema?.required || []
    const params = []
    // path params
    const pathParams = [...oaPath.matchAll(/\{(\w+)\}/g)].map(m => m[1])
    for (const p of pathParams) {
      params.push({
        name: p,
        in: 'path',
        required: true,
        schema: props[p] || { type: 'string' },
        description: props[p]?.description || `path parameter ${p}`,
      })
    }
    // query params (for GET)
    if (t.method === 'GET') {
      for (const [k, v] of Object.entries(props)) {
        if (pathParams.includes(k)) continue
        params.push({
          name: k,
          in: 'query',
          required: required.includes(k),
          schema: v,
          description: v.description || `${k} parameter`,
        })
      }
    }
    if (params.length) op.parameters = params
    // body (for POST)
    if (t.method === 'POST') {
      const bodyProps = {}
      const bodyReq = []
      for (const [k, v] of Object.entries(props)) {
        if (pathParams.includes(k)) continue
        bodyProps[k] = v
        if (required.includes(k)) bodyReq.push(k)
      }
      op.requestBody = {
        required: bodyReq.length > 0,
        content: {
          'application/json': {
            schema: { type: 'object', properties: bodyProps, required: bodyReq },
          },
        },
      }
    }
    // security — all routes need Bearer auth except health
    if (!opId.includes('health')) {
      op.security = [{ BearerAuth: [] }]
    }
    pathMap[`/api/workbuddy${oaPath}`] = { [t.method.toLowerCase()]: op }
  }
  return {
    openapi: '3.0.3',
    info: {
      title: 'SmartBiz WorkBuddy API',
      version: '1.0.0',
      description:
        'WorkBuddy 是 SGP / 横琴 / 多 profile SmartBiz 系统的统一 API Gateway。\n' +
        '任何 LLM agent (Claude Code / OpenClaw / Codex / Cursor / Trae) 都能用 sbk_ token 或 JWT 接入。\n' +
        '完整接入文档: /root/docs/WORKBUDDY-AGENT-INTEGRATION.md',
      contact: { name: '江小鱼', url: 'https://wecom.gdqshop.cn/gdqadmin' },
    },
    servers: [
      { url: 'https://wecom.gdqshop.cn/api/workbuddy', description: 'SGP 生产 (profile 1)' },
      { url: 'https://hatch.gdqshop.cn/api/workbuddy', description: '横琴孵化器 (profile 6)' },
    ],
    components: {
      securitySchemes: {
        BearerAuth: {
          type: 'http',
          scheme: 'bearer',
          description:
            'agent sbk_ token (admin 在 gdqadmin 后台创建) 或 JWT (登录用户)。' +
            'Header: Authorization: Bearer <token>',
        },
      },
    },
    tags: [
      { name: 'inventory', description: '库存' },
      { name: 'orders', description: '订单' },
      { name: 'products', description: '商品' },
      { name: 'warehouses', description: '仓库' },
      { name: 'approvals', description: '审批' },
      { name: 'finance', description: '财务' },
      { name: 'attendance', description: '考勤' },
      { name: 'tasks', description: '任务' },
      { name: 'logs', description: '工作日志' },
      { name: 'training', description: 'AI 课堂 / 培训' },
      { name: 'wecom', description: '企业微信' },
      { name: 'reminders', description: '提醒中心' },
      { name: 'push', description: '推送' },
      { name: 'action', description: '快捷操作 / AI 建议' },
    ],
    paths: pathMap,
  }
}

// GET /api/workbuddy/openapi.json - OpenAPI 3.0 schema 自动生成
// 公开, 任何 agent 都能拉, 不需 auth (schema 本身不泄漏敏感信息, 端点鉴权另算)
router.get('/openapi.json', (req, res) => {
  res.json(buildOpenApiSchema())
})

// POST /api/workbuddy/mcp/execute - 执行一个 MCP 工具
// body: { tool: string, args: {...} }
// 内部转发本机对应 /api/workbuddy/* 接口 + 复用调用者 Authorization（保留权限语义）
router.post('/mcp/execute', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const { tool, args } = req.body || {}
    const t = MCP_TOOLS.find(x => x.name === tool)
    if (!t) return res.status(404).json({ error: `unknown tool: ${tool}`, available: MCP_TOOLS.slice(0,100).map(x=>x.name) })

    // 参数校验：必填字段
    const required = (t.inputSchema.required || [])
    for (const k of required) {
      if (args === undefined || args[k] === undefined || args[k] === '') {
        return res.status(400).json({ error: `missing required arg: ${k}` })
      }
    }

    // 路径参数替换 (:id) + query/body 组装
    let path = t.path
    const query = new URLSearchParams()
    const body = {}
    if (args) {
      for (const [k, v] of Object.entries(args)) {
        if (path.includes(`:${k}`)) {
          path = path.replace(`:${k}`, encodeURIComponent(v))
        } else if (t.method === 'GET') {
          query.set(k, v)
        } else {
          body[k] = v
        }
      }
    }
    // 动态取当前服务端口（SGP=3200, 横琴/HK=3300, 由 pm2 PORT env 注入）
    // 不能硬编码 3200，否则跨部署（如横琴 3300）时 MCP execute 转发失败
    const selfPort = process.env.PORT || 3200
    const url = `http://localhost:${selfPort}/api/workbuddy${path}${query.toString() ? '?' + query.toString() : ''}`

    const authHeader = req.headers['authorization']
    const resp = await fetch(url, {
      method: t.method,
      headers: {
        'Content-Type': 'application/json',
        ...(authHeader ? { Authorization: authHeader } : {}),
      },
      ...(t.method === 'POST' ? { body: JSON.stringify(body) } : {}),
    })
    const json = await resp.json().catch(() => ({}))
    res.status(resp.status).json({
      tool: t.name,
      status: resp.status,
      ok: resp.ok,
      result: json,
    })
  } catch (err) { next(err) }
})

export default router
export { MCP_TOOLS }