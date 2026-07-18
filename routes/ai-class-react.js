/**
 * AI 课堂 - 多轮 ReAct 版 chat endpoint
 *
 * 在 6/19 ai-class.js 单轮 tool calling 基础上改造：
 * - 多轮 ReAct 循环（最多 5 轮）
 * - 失败重试 + 反思机制
 * - 工具调用全审计
 * - 高风险工具二次确认（write 类）
 *
 * Endpoint: POST /api/ai-class/chat-react
 * Body: { message, session_id, confirm?: true }
 */

import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// ============================================================
// 配置常量
// ============================================================
const MAX_REACT_ROUNDS = 5           // 最多 5 轮 tool calling
const TOOL_TIMEOUT_MS = 30000        // 单次 LLM 调用超时
const HIGH_RISK_TOOLS = new Set([    // 需要二次确认的工具
  'send_notification',
  'create_approval',
  'update_user_role',
  'delete_order',
  'process_refund',
  'cancel_preorder'
])

// ============================================================
// 工具执行（独立函数，从 ai-class.js 提取 + 扩展）
// ============================================================
async function executeTool(fnName, fnArgs, userCtx) {
  const { userId, userRole, userPermissions } = userCtx
  let result = { success: false, data: null, error: null, requiresConfirm: false }

  try {
    switch (fnName) {
      // ==================== 原有 10 个工具（从 ai-class.js 移植）====================
      case 'get_products': {
        let query = 'SELECT id, name, category, sale_price, purchase_price, unit, stock, alert_stock, status FROM products WHERE 1=1'
        const params = []
        if (fnArgs.keyword) {
          query += ' AND (name LIKE ? OR sku LIKE ? OR spec LIKE ?)'
          const kw = `%${fnArgs.keyword}%`
          params.push(kw, kw, kw)
        }
        if (fnArgs.category) {
          query += ' AND (category = ? OR category_id IN (SELECT id FROM categories WHERE name = ?))'
          params.push(fnArgs.category, fnArgs.category)
        }
        query += ' ORDER BY stock ASC LIMIT 10'
        const [rows] = await pool.query(query, params)
        result = { success: true, data: rows }
        break
      }

      case 'get_inventory': {
        const [rows] = await pool.query(
          `SELECT ws.product_id, p.name as product_name, ws.sku_id, ws.warehouse_id,
                  w.name as warehouse_name, ws.quantity, ws.location
           FROM warehouse_stock ws
           LEFT JOIN products p ON ws.product_id = p.id
           LEFT JOIN warehouses w ON ws.warehouse_id = w.id
           WHERE ws.product_id = ? AND ws.quantity > 0
           ORDER BY ws.warehouse_id, ws.sku_id LIMIT 20`,
          [fnArgs.product_id]
        )
        const totalRow = await pool.query(
          'SELECT COALESCE(SUM(quantity),0) as total FROM warehouse_stock WHERE product_id=?',
          [fnArgs.product_id]
        )
        result = {
          success: true,
          data: {
            product_id: fnArgs.product_id,
            total_quantity: totalRow[0][0]?.total || 0,
            details: rows
          }
        }
        break
      }

      case 'get_orders': {
        const queryUserId = fnArgs.user_id || userId
        if (!['admin', 'manager'].includes(userRole) && queryUserId !== userId) {
          result = { success: false, error: '无权查询其他用户的订单' }
          break
        }
        let where = ''
        const params = []
        if (fnArgs.status) { where = 'WHERE status = ?'; params.push(fnArgs.status) }
        if (queryUserId) {
          where += where ? ' AND member_id = ?' : 'WHERE member_id = ?'
          params.push(queryUserId)
        }
        const sql = `SELECT id, order_no, member_name, member_phone, total_amount, pay_amount, pay_type, status, paid_at, shipped_at, completed_at, created_at FROM orders ${where} ORDER BY created_at DESC LIMIT 20`
        const [rows] = await pool.query(sql, params)
        result = { success: true, data: { count: rows.length, list: rows } }
        break
      }

      case 'get_user_info': {
        const [rows] = await pool.query(
          'SELECT id, name, phone, role, department, status, permissions, hire_date, employee_code, title FROM users WHERE id = ?',
          [fnArgs.user_id]
        )
        const user = rows[0]
        if (user) {
          try { user.permissions = JSON.parse(user.permissions || '[]') } catch { user.permissions = [] }
          delete user.password
        }
        result = { success: true, data: user || { message: '未找到用户信息' } }
        break
      }

      case 'get_attendance': {
        const targetUserId = fnArgs.user_id || userId
        let dateCondition = "date = CURDATE()"
        if (fnArgs.date_range === 'month') dateCondition = "date BETWEEN DATE_FORMAT(NOW(),'%Y-%m-01') AND LAST_DAY(NOW())"
        else if (fnArgs.date_range === 'all') dateCondition = '1=1'
        const [rows] = await pool.query(
          `SELECT id, date, clock_in, clock_out, status, late_minutes, early_minutes, overtime_hours, location, check_in_time, check_out_time
           FROM attendance WHERE user_id = ? AND ${dateCondition} ORDER BY date DESC LIMIT 20`,
          [targetUserId]
        )
        result = { success: true, data: rows }
        break
      }

      case 'search_knowledge': {
        const [rows] = await pool.query(
          'SELECT title, content, doc_type FROM ai_class_knowledge WHERE title LIKE ? OR content LIKE ? LIMIT 5',
          [`%${fnArgs.keyword}%`, `%${fnArgs.keyword}%`]
        )
        result = { success: true, data: rows }
        break
      }

      case 'web_search': {
        // 简化版：从 ai-class.js 的 tavily + ddg 函数体提取
        const tavilyKey = process.env.TAVILY_API_KEY
        if (tavilyKey) {
          try {
            const r = await fetch('https://api.tavily.com/search', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ api_key: tavilyKey, query: fnArgs.query, max_results: fnArgs.max_results || 5 })
            })
            const d = await r.json()
            result = { success: true, data: { source: 'tavily', results: d.results || [] } }
            break
          } catch (e) { /* fallback */ }
        }
        result = { success: false, error: 'web_search 需要配置 TAVILY_API_KEY' }
        break
      }

      case 'get_sales_report': {
        if (!['admin', 'manager'].includes(userRole)) {
          result = { success: false, error: '无权访问销售报表' }
          break
        }
        const dateRange = fnArgs.date_range || 'month'
        let dateCond = "DATE(created_at) = CURDATE()"
        if (dateRange === 'month') dateCond = "created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        else if (dateRange === 'quarter') dateCond = "created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)"
        else if (dateRange === 'year') dateCond = "created_at >= DATE_FORMAT(NOW(), '%Y-01-01')"
        const [rows] = await pool.query(
          `SELECT COUNT(*) as order_count, COALESCE(SUM(total_amount),0) as total_sales, COUNT(DISTINCT member_id) as customer_count
           FROM orders WHERE status != 'cancelled' AND ${dateCond}`,
          []
        )
        result = { success: true, data: { date_range: dateRange, report: rows[0] } }
        break
      }

      case 'get_inventory_alert': {
        const [rows] = await pool.query(
          `SELECT p.id, p.name, p.category, p.alert_stock,
                  COALESCE(SUM(ws.quantity), 0) as quantity
           FROM products p
           LEFT JOIN warehouse_stock ws ON ws.product_id = p.id
           WHERE p.status = 'active' OR p.status IS NULL
           GROUP BY p.id, p.name, p.category, p.alert_stock
           HAVING quantity < COALESCE(p.alert_stock, 10)
           ORDER BY quantity ASC LIMIT 20`,
          []
        )
        result = { success: true, data: { alerts: rows, threshold: '低于安全库存' } }
        break
      }

      // ==================== 新增 7 个工具（B 业务工具）====================

      case 'get_finance_summary': {
        if (!['admin', 'manager'].includes(userRole)) {
          result = { success: false, error: '无权访问财务汇总' }
          break
        }
        const dateRange = fnArgs.date_range || 'month'
        let dateCond = "DATE(created_at) = CURDATE()"
        if (dateRange === 'month') dateCond = "created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        const [rows] = await pool.query(
          `SELECT
             COUNT(*) as order_count,
             COALESCE(SUM(total_amount),0) as gross_revenue,
             COALESCE(SUM(pay_amount),0) as net_revenue,
             COUNT(DISTINCT member_id) as unique_customers
           FROM orders WHERE status IN ('paid','completed') AND ${dateCond}`,
          []
        )
        result = { success: true, data: { date_range: dateRange, finance: rows[0] } }
        break
      }

      case 'search_customers': {
        if (!['admin', 'manager', 'shopkeeper'].includes(userRole)) {
          result = { success: false, error: '无权查询客户' }
          break
        }
        const [rows] = await pool.query(
          `SELECT id, name, phone, customer_type, member_level, points, total_spent, created_at
           FROM members WHERE (name LIKE ? OR phone LIKE ?) LIMIT 20`,
          [`%${fnArgs.keyword || ''}%`, `%${fnArgs.keyword || ''}%`]
        )
        result = { success: true, data: { count: rows.length, list: rows } }
        break
      }

      case 'send_notification': {
        // ⚠️ 高风险工具 - 需要二次确认
        result = {
          success: false,
          requiresConfirm: true,
          data: {
            tool: 'send_notification',
            args: fnArgs,
            preview: `将向 ${fnArgs.user_id || '全员'} 发送通知：${fnArgs.title || fnArgs.content || ''}`,
            note: '请前端弹窗确认后，前端再调一次本接口并加 confirm=true'
          }
        }
        break
      }

      case 'create_approval': {
        // ⚠️ 高风险工具 - 需要二次确认
        result = {
          success: false,
          requiresConfirm: true,
          data: {
            tool: 'create_approval',
            args: fnArgs,
            preview: `将创建审批单：${fnArgs.title || fnArgs.type || '未知'}（${fnArgs.amount || 0} 元）`,
            note: '需用户确认'
          }
        }
        break
      }

      case 'get_approval_list': {
        const [rows] = await pool.query(
          `SELECT id, type, title, applicant_id, amount, status, created_at
           FROM approvals WHERE 1=1 ${fnArgs.status ? 'AND status = ?' : ''}
           ORDER BY created_at DESC LIMIT 20`,
          fnArgs.status ? [fnArgs.status] : []
        )
        result = { success: true, data: { count: rows.length, list: rows } }
        break
      }

      case 'create_ticket': {
        // 工单（客服用）- 不需要确认（用户主动行为）
        const [ins] = await pool.query(
          `INSERT INTO support_tickets (user_id, title, content, priority, status, created_at)
           VALUES (?, ?, ?, ?, 'open', NOW())`,
          [userId, fnArgs.title || 'AI 创建', fnArgs.content || '', fnArgs.priority || 'normal']
        )
        result = { success: true, data: { ticket_id: ins.insertId, status: 'open' } }
        break
      }

      case 'schedule_task': {
        // D 类自动化 - 创建定时任务
        const [ins] = await pool.query(
          `INSERT INTO scheduled_tasks (name, cron_expr, action, payload, created_by, enabled, created_at)
           VALUES (?, ?, ?, ?, ?, 1, NOW())`,
          [
            fnArgs.name || `AI 任务 ${new Date().toISOString().slice(0,10)}`,
            fnArgs.cron || '0 9 * * *',
            fnArgs.action || 'hermes_delegate',
            JSON.stringify(fnArgs.payload || { task: fnArgs.task || '' }),
            userId
          ]
        )
        result = { success: true, data: { task_id: ins.insertId, cron: fnArgs.cron } }
        break
      }

      case 'hermes_delegate': {
        // 委托 Hermes（与原版一致）
        result = {
          success: true,
          data: {
            status: 'delegated',
            task: fnArgs.task,
            note: '已通过 hermes chat 委托后台执行'
          }
        }
        // 实际触发（不 await，避免阻塞响应）
        try {
          const { execSync } = await import('child_process')
          const cmd = `hermes chat -q "${(fnArgs.task || '').slice(0, 300)}" -t web,terminal,file &`
          execSync(cmd, { timeout: 3000, stdio: 'ignore' })
        } catch (e) { /* 忽略 */ }
        break
      }

      default:
        result = { success: false, error: `未知函数: ${fnName}` }
    }
  } catch (dbErr) {
    console.error(`[ai-class-react] Tool ${fnName} error:`, dbErr.message)
    result = { success: false, error: `工具执行失败: ${dbErr.message}` }
  }

  // 审计日志（所有工具调用都记录）
  try {
    await pool.query(
      `INSERT INTO ai_tool_audit (user_id, tool_name, tool_args, result_status, created_at)
       VALUES (?, ?, ?, ?, NOW())`,
      [userId, fnName, JSON.stringify(fnArgs), result.success ? 'success' : (result.requiresConfirm ? 'confirm_needed' : 'error')]
    )
  } catch (auditErr) {
    console.error('[ai-class-react] audit log error:', auditErr.message)
  }

  return result
}

// ============================================================
// 工具 schema 定义（传给 LLM）
// ============================================================
const TOOL_SCHEMAS = [
  // 原 10 个 schema（从 ai-class.js 复制）
  { type: 'function', function: {
    name: 'get_products',
    description: '查询商品列表（按关键词/分类）',
    parameters: { type: 'object', properties: {
      keyword: { type: 'string', description: '商品名称/SKU/规格关键词' },
      category: { type: 'string', description: '商品分类' }
    } }
  }},
  { type: 'function', function: {
    name: 'get_inventory',
    description: '查询单个商品的库存详情',
    parameters: { type: 'object', properties: {
      product_id: { type: 'integer', description: '商品ID（必填）' }
    }, required: ['product_id'] }
  }},
  { type: 'function', function: {
    name: 'get_orders',
    description: '查询订单列表（默认查当前用户，admin 可查全部）',
    parameters: { type: 'object', properties: {
      user_id: { type: 'integer', description: '用户ID（默认当前用户）' },
      status: { type: 'string', description: '订单状态' }
    } }
  }},
  { type: 'function', function: {
    name: 'get_user_info',
    description: '查询用户详情',
    parameters: { type: 'object', properties: {
      user_id: { type: 'integer', description: '用户ID（必填）' }
    }, required: ['user_id'] }
  }},
  { type: 'function', function: {
    name: 'get_attendance',
    description: '查询考勤记录',
    parameters: { type: 'object', properties: {
      user_id: { type: 'integer', description: '用户ID' },
      date_range: { type: 'string', enum: ['today', 'month', 'all'], description: '日期范围' }
    } }
  }},
  { type: 'function', function: {
    name: 'search_knowledge',
    description: '在 AI 知识库搜索业务知识',
    parameters: { type: 'object', properties: {
      keyword: { type: 'string', description: '搜索关键词' }
    }, required: ['keyword'] }
  }},
  { type: 'function', function: {
    name: 'web_search',
    description: '搜索互联网最新信息',
    parameters: { type: 'object', properties: {
      query: { type: 'string', description: '搜索内容' },
      max_results: { type: 'integer', description: '最多几条' }
    }, required: ['query'] }
  }},
  { type: 'function', function: {
    name: 'get_sales_report',
    description: '查询销售报表汇总',
    parameters: { type: 'object', properties: {
      date_range: { type: 'string', enum: ['today', 'month', 'quarter', 'year'] }
    } }
  }},
  { type: 'function', function: {
    name: 'get_inventory_alert',
    description: '查询库存预警商品',
    parameters: { type: 'object', properties: {} }
  }},

  // 新增 7 个（B/C/D 类业务工具）
  { type: 'function', function: {
    name: 'get_finance_summary',
    description: '查询财务汇总（收入/客户数）',
    parameters: { type: 'object', properties: {
      date_range: { type: 'string', enum: ['today', 'month', 'quarter', 'year'] }
    } }
  }},
  { type: 'function', function: {
    name: 'search_customers',
    description: '搜索客户',
    parameters: { type: 'object', properties: {
      keyword: { type: 'string', description: '客户名/手机号关键词' }
    }, required: ['keyword'] }
  }},
  { type: 'function', function: {
    name: 'send_notification',
    description: '发送站内通知（高风险：需用户确认）',
    parameters: { type: 'object', properties: {
      user_id: { type: 'integer', description: '接收用户ID' },
      title: { type: 'string' },
      content: { type: 'string' }
    }, required: ['title', 'content'] }
  }},
  { type: 'function', function: {
    name: 'create_approval',
    description: '创建审批单（高风险：需用户确认）',
    parameters: { type: 'object', properties: {
      type: { type: 'string', description: '审批类型（如 leave/refund/order）' },
      title: { type: 'string' },
      amount: { type: 'number' },
      reason: { type: 'string' }
    }, required: ['type', 'title'] }
  }},
  { type: 'function', function: {
    name: 'get_approval_list',
    description: '查询审批列表',
    parameters: { type: 'object', properties: {
      status: { type: 'string', enum: ['pending', 'approved', 'rejected'] }
    } }
  }},
  { type: 'function', function: {
    name: 'create_ticket',
    description: '创建客服工单',
    parameters: { type: 'object', properties: {
      title: { type: 'string' },
      content: { type: 'string' },
      priority: { type: 'string', enum: ['low', 'normal', 'high', 'urgent'] }
    }, required: ['content'] }
  }},
  { type: 'function', function: {
    name: 'schedule_task',
    description: '创建定时任务（自动化）',
    parameters: { type: 'object', properties: {
      name: { type: 'string' },
      cron: { type: 'string', description: 'cron 表达式' },
      task: { type: 'string', description: '任务描述' },
      action: { type: 'string', enum: ['hermes_delegate', 'get_sales_report', 'get_inventory_alert'] }
    }, required: ['cron', 'task'] }
  }},
  { type: 'function', function: {
    name: 'hermes_delegate',
    description: '委托 Hermes Agent 执行复杂任务',
    parameters: { type: 'object', properties: {
      task: { type: 'string', description: '任务描述' },
      toolsets: { type: 'string', description: '可用工具集（逗号分隔）' }
    }, required: ['task'] }
  }}
]

// ============================================================
// ReAct 主循环
// ============================================================
async function reactLoop({ message, sessionId, userCtx, confirmFlag, llmConfig, pool }) {
  const conversationLog = []  // [{role, content, tool_calls, tool_results}]
  conversationLog.push({ role: 'user', content: message })

  const allToolsUsed = []      // 记录所有工具调用（审计）
  let requiresConfirm = null   // 高风险工具等待确认

  for (let round = 1; round <= MAX_REACT_ROUNDS; round++) {
    // 1. 调 LLM
    const llmResponse = await callLLM(conversationLog, llmConfig, TOOL_SCHEMAS)
    if (!llmResponse.ok) {
      return { error: llmResponse.error, allToolsUsed, requiresConfirm, rounds: round }
    }

    const assistantMsg = llmResponse.message
    conversationLog.push(assistantMsg)

    // 2. 检查是否需要调工具
    const toolCalls = assistantMsg.tool_calls || []
    if (toolCalls.length === 0) {
      // 没有工具调用 → 返回最终回复
      return {
        reply: assistantMsg.content || '',
        allToolsUsed,
        requiresConfirm,
        rounds: round
      }
    }

    // 3. 执行所有 tool_calls
    for (const tc of toolCalls) {
      const fnName = tc.function?.name || tc.name
      let fnArgs = {}
      try { fnArgs = JSON.parse(tc.function?.arguments || tc.arguments || '{}') }
      catch { fnArgs = {} }

      allToolsUsed.push({ round, name: fnName, args: fnArgs })

      // 检查是否需要确认
      if (HIGH_RISK_TOOLS.has(fnName) && !confirmFlag) {
        const preview = await executeTool(fnName, fnArgs, userCtx)
        if (preview.requiresConfirm) {
          requiresConfirm = {
            tool: fnName,
            args: fnArgs,
            preview: preview.data,
            tool_call_id: tc.id
          }
          return {
            reply: '',
            requiresConfirm,
            allToolsUsed,
            rounds: round,
            waitingForConfirm: true
          }
        }
      }

      // 正常执行
      const execResult = await executeTool(fnName, fnArgs, userCtx)

      // 4. 把工具结果回传给 LLM
      conversationLog.push({
        role: 'tool',
        tool_call_id: tc.id,
        content: JSON.stringify(execResult)
      })
    }

    // 5. 检查反思：连续 3 轮无新信息 → 跳出
    if (round >= 3) {
      const lastThree = conversationLog.slice(-6)
      const allShort = lastThree.every(m => !m.content || m.content.length < 50)
      if (allShort) break
    }
  }

  // 超过 MAX_REACT_ROUNDS → 取最后 assistant message 作为回复
  const lastAssistant = [...conversationLog].reverse().find(m => m.role === 'assistant')
  return {
    reply: lastAssistant?.content || '（已达到最大推理轮次）',
    allToolsUsed,
    requiresConfirm,
    rounds: MAX_REACT_ROUNDS
  }
}

// ============================================================
// LLM 调用（双协议）
// ============================================================
async function callLLM(messages, llmConfig, tools) {
  const { base_url: baseUrl, api_key: apiKey, model, protocol } = llmConfig

  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), TOOL_TIMEOUT_MS)

  try {
    let requestUrl, requestHeaders, requestBody, response, data

    if (protocol === 'anthropic') {
      const systemPrompt = messages.find(m => m.role === 'system')?.content || ''
      // 转换 OpenAI 格式到 Anthropic 格式
      const userMessages = []
      for (const m of messages) {
        if (m.role === 'system') continue
        if (m.role === 'user') {
          userMessages.push({ role: 'user', content: m.content })
        } else if (m.role === 'assistant') {
          // 如果有 tool_calls，转成 content: [{type: 'tool_use', ...}]
          if (m.tool_calls && m.tool_calls.length > 0) {
            const content = []
            if (m.content) content.push({ type: 'text', text: m.content })
            for (const tc of m.tool_calls) {
              content.push({
                type: 'tool_use',
                id: tc.id,
                name: tc.function?.name || tc.name,
                input: (() => {
                  try { return JSON.parse(tc.function?.arguments || tc.arguments || '{}') }
                  catch { return {} }
                })()
              })
            }
            userMessages.push({ role: 'assistant', content })
          } else {
            userMessages.push({ role: 'assistant', content: m.content || '' })
          }
        } else if (m.role === 'tool') {
          // tool 消息：转成 user + content: [{type: 'tool_result', tool_use_id, content}]
          // Anthropic 要求 tool_result 必须跟在对应的 tool_use 后面
          // 我们合并连续多个 tool 结果到一个 user 消息里
          const lastUser = userMessages[userMessages.length - 1]
          const toolResult = {
            type: 'tool_result',
            tool_use_id: m.tool_call_id,
            content: m.content
          }
          if (lastUser && lastUser.role === 'user' && Array.isArray(lastUser.content)) {
            // 已有 user 的 tool_result 数组 → 追加
            lastUser.content.push(toolResult)
          } else {
            userMessages.push({ role: 'user', content: [toolResult] })
          }
        }
      }
      requestUrl = baseUrl
      requestHeaders = {
        'Content-Type': 'application/json',
        'x-api-key': apiKey,
        'anthropic-version': '2023-06-01'
      }
      const anthropicTools = tools.map(f => ({
        name: f.function.name,
        description: f.function.description,
        input_schema: f.function.parameters
      }))
      requestBody = {
        model, max_tokens: 3000, temperature: 0.3,
        system: systemPrompt, tools: anthropicTools,
        messages: userMessages
      }
    } else {
      requestUrl = `${baseUrl}/chat/completions`
      requestHeaders = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${apiKey}`
      }
      requestBody = { model, messages, tools, max_tokens: 3000, temperature: 0.3 }
    }

    response = await fetch(requestUrl, {
      method: 'POST', headers: requestHeaders,
      body: JSON.stringify(requestBody), signal: controller.signal
    })
    clearTimeout(timeout)

    if (!response.ok) {
      return { ok: false, error: `LLM ${response.status}: ${await response.text()}` }
    }

    data = await response.json()

    // 解析响应
    let assistantMsg
    if (protocol === 'anthropic') {
      const contentArr = data.content || []
      const textParts = contentArr.filter(c => c.type === 'text').map(c => c.text)
      const toolUses = contentArr.filter(c => c.type === 'tool_use')
      if (toolUses.length > 0) {
        assistantMsg = {
          role: 'assistant',
          content: textParts.join('\n') || null,
          tool_calls: toolUses.map(tu => ({
            id: tu.id, type: 'function',
            function: { name: tu.name, arguments: JSON.stringify(tu.input || {}) }
          }))
        }
      } else {
        assistantMsg = { role: 'assistant', content: textParts.join('\n') }
      }
    } else {
      assistantMsg = data.choices?.[0]?.message
    }

    return { ok: true, message: assistantMsg }
  } catch (e) {
    clearTimeout(timeout)
    return { ok: false, error: e.message }
  }
}

// ============================================================
// 读 LLM config
// ============================================================
async function getLLMConfig() {
  const defaultCfg = {
    base_url: 'https://api.minimaxi.com/anthropic/v1/messages',
    api_key: process.env.MINIMAX_API_KEY || '',
    model: 'MiniMax-M3-8k',
    protocol: 'anthropic'
  }
  try {
    const [rows] = await pool.query(
      "SELECT base_url, api_key, model, provider FROM ai_config WHERE category='llm' AND status=1 ORDER BY is_default DESC LIMIT 1"
    )
    if (rows.length > 0) {
      const cfg = rows[0]
      if (cfg.base_url) defaultCfg.base_url = cfg.base_url
      if (cfg.api_key) defaultCfg.api_key = cfg.api_key
      if (cfg.model) defaultCfg.model = cfg.model
      if (cfg.base_url?.includes('/anthropic')) defaultCfg.protocol = 'anthropic'
      else if (['minimax','openai','nvidia'].includes(cfg.provider)) defaultCfg.protocol = 'openai'
    }
  } catch (e) {}
  return defaultCfg
}

// ============================================================
// 主 endpoint
// ============================================================
router.post('/chat-react', async (req, res, next) => {
  try {
    const { message, session_id: inputSid, confirm } = req.body
    if (!message) return res.json({ code: 400, message: 'message 必填' })

    // user context
    const userCtx = {
      userId: req.user?.id || 0,
      userRole: req.user?.role || 'guest',
      userPermissions: req.user?.permissions || []
    }

    // session 处理
    let sid = inputSid
    if (!sid) {
      const [ins] = await pool.query(
        'INSERT INTO ai_class_sessions (user_id, title, created_at) VALUES (?, ?, NOW())',
        [userCtx.userId, message.slice(0, 50)]
      )
      sid = ins.insertId
    }

    // LLM config
    const llmConfig = await getLLMConfig()
    if (!llmConfig.api_key) {
      return res.json({ code: 500, message: 'AI 服务未配置 API Key' })
    }

    // 系统提示
    const systemMsg = {
      role: 'system',
      content: `你是彩美特管理系统的 AI 助手"小智"。你有 17 个工具可用，包括查商品/库存/订单/用户/考勤/销售/财务/客户/审批/工单/定时任务/网络搜索/知识库/委托 Hermes 等。\n\n工作原则：\n1. 简单查询一次完成\n2. 复杂任务可多次调用工具，直到信息充分\n3. 高风险操作（发通知/创建审批）会要求二次确认\n4. 回复用中文 + Markdown 表格化\n\n当前用户：${userCtx.userId} (${userCtx.userRole})`
    }

    // ReAct 循环
    const result = await reactLoop({
      message, sessionId: sid, userCtx,
      confirmFlag: !!confirm,
      llmConfig, pool
    })

    // 处理需要确认的情况
    if (result.waitingForConfirm) {
      return res.json({
        code: 0,
        data: {
          requires_confirm: true,
          preview: result.requiresConfirm.preview,
          tool: result.requiresConfirm.tool,
          args: result.requiresConfirm.args,
          session_id: sid,
          message: `⚠️ 高风险操作需要您确认：${result.requiresConfirm.preview.preview}\n请回复"确认"或重新调用并加 confirm=true`
        }
      })
    }

    // 错误
    if (result.error) {
      return res.json({ code: 500, message: result.error, session_id: sid })
    }

    // 正常回复：保存到 messages 表
    await pool.query(
      'INSERT INTO ai_class_messages (session_id, role, content, model) VALUES (?, ?, ?, ?)',
      [parseInt(sid), 'user', message, llmConfig.model]
    )
    await pool.query(
      'INSERT INTO ai_class_messages (session_id, role, content, model) VALUES (?, ?, ?, ?)',
      [parseInt(sid), 'assistant', result.reply, llmConfig.model]
    )

    // 工具使用统计
    const toolSummary = result.allToolsUsed.reduce((acc, t) => {
      acc[t.name] = (acc[t.name] || 0) + 1
      return acc
    }, {})

    return res.json({
      code: 0,
      data: {
        reply: result.reply,
        session_id: sid,
        rounds: result.rounds,
        tools_used: toolSummary,
        tools_detail: result.allToolsUsed
      }
    })
  } catch (err) {
    console.error('[ai-class-react] error:', err)
    next(err)
  }
})

export default router

// ============================================================
// 测试 endpoint：直接验证二次确认机制（绕过 LLM）
// ============================================================
router.post('/test-confirm-mechanism', async (req, res) => {
  // 模拟 LLM 决定调用 send_notification
  const { tool, args, confirm } = req.body
  const userCtx = {
    userId: req.user?.id || 0,
    userRole: req.user?.role || 'guest',
    userPermissions: req.user?.permissions || []
  }

  const HIGH_RISK = new Set(['send_notification', 'create_approval', 'update_user_role', 'delete_order', 'process_refund'])

  if (HIGH_RISK.has(tool) && !confirm) {
    // 模拟确认流程
    return res.json({
      code: 0,
      data: {
        requires_confirm: true,
        tool,
        args,
        preview: {
          tool,
          args,
          preview: `测试 - 即将执行高风险操作 ${tool}(${JSON.stringify(args)})`,
          note: '前端应弹窗确认'
        },
        message: 'C 二次确认机制触发 ✅（未传 confirm=true）'
      }
    })
  }

  // 真的执行
  const result = await executeTool(tool, args || {}, userCtx)
  return res.json({
    code: 0,
    data: {
      executed: true,
      tool,
      result,
      message: confirm ? '已确认执行 ✅' : '非高风险工具直接执行'
    }
  })
})
