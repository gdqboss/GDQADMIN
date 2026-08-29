# WorkBuddy 接入指南 — REST + MCP 双通道

> 版本: v2.0 / 2026-08-29  
> 适用: SmartBiz WorkBuddy API Gateway（SGP 新加坡，profile 1）  
> 覆盖: REST 57 接口 + MCP 46 工具，全部可用

---

## 一、系统架构

```
┌──────────────────────────────────────────────────────────────┐
│  WorkBuddy 用户 (OPC 老板/经理/员工)                          │
│  自然语言: "今天该处理什么" "库存有预警吗" "帮我发条企微"     │
└──────────────┬───────────────────────────────────────────────┘
               │ 方式A: HTTP 请求                    方式B: LLM tool_use
               ↓                                              ↓
┌─────────────────────────────┐          ┌──────────────────────────────┐
│  WorkBuddy 前端 SPA          │          │  AI Agent (GLM/智能体)       │
│  127.0.0.1:8089             │          │  tool name + args            │
└──────────────┬──────────────┘          └──────────────┬───────────────┘
               │  /api/workbuddy/*                       │  /api/workbuddy/mcp/execute
               ↓                                          ↓
┌──────────────────────────────┐         ┌──────────────────────────────┐
│  nginx _workbuddy.conf       │         │                              │
│  proxy_pass → :3200           │         │                              │
│  proxy_set_header Authorization:        │                              │
│    'Bearer eyJhbG...' (365d  │         │                              │
│     service JWT, 自动注入)    │         │                              │
└──────────────┬──────────────┘         └──────────────┬───────────────┘
               ↓ proxy to SGP                          ↓ 同一后端
┌──────────────────────────────────────────────────────↓───────────────┐
│  Express :3200  /api/workbuddy/*                                     │
│  auth(jwt) → requirePermission('workbuddy:read'/'write')            │
│  ┌───────────┬───────────┬───────────┬───────────┬──────────────┐   │
│  │ wb-inv    │ wb-orders │ wb-prod   │ wb-ware   │ wb-approv    │   │
│  │ wb-fin    │ wb-actions│ wb-attend │ wb-tasks  │ wb-worklogs  │   │
│  │ wb-train  │ wb-wecom  │ wb-mcp    │           │              │   │
│  └───────────┴───────────┴───────────┴───────────┴──────────────┘   │
│  底层: MySQL (gdq 库, 复用真实业务表)                                │
└───────────────────────────────────────────────────────────────────┘
```

**鉴权模型（关键）**：
- WorkBuddy 前端**无需登录**。nginx `_workbuddy.conf` 已用 `proxy_set_header Authorization 'Bearer <service-jwt>'` 注入 365 天 service token（admin 角色，id=1）。
- 所以前端发起 `/api/workbuddy/*` 请求即自动带上有效 JWT，后端 `auth` 中间件通过。
- **外部系统**（非 nginx 代理）调用需**手动携带**同一个 Bearer token（token 文件: `/root/jxy-os/workbuddy/.wb-service-token`）。
- 无 token / 失效 token → 401 `{"code":401,"message":"token 无效或已过期"}`。

---

## 二、方式 A：REST 直调（57 接口）

**Base URL**: `http://<host>:3200/api/workbuddy` （生产走 nginx `/api/workbuddy`）

**鉴权**: `Authorization: Bearer <token>`

### 2.1 经营总览
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/health` | 健康检查（免 auth） |
| GET | `/stats` | 仪表盘核心指标（库存/订单/商品/员工） |
| GET | `/action/priorities` | **老板今日待办聚合**（逾期任务/待审日志/待审批/低库存/未交日志） |
| GET | `/action/suggestions` | AI 智能建议 |

### 2.2 库存
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/inventory/summary` | 库存总览（3 仓） |
| GET | `/inventory/alerts` | 库存预警（低于预警线） |
| GET | `/inventory/check?q=` | SKU/品名模糊搜索 |
| GET | `/inventory/warehouse/:id` | 指定仓库明细 |

### 2.3 订单
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/orders/summary` | 今日/本周/本月订单统计 |
| GET | `/orders/recent` | 最新订单 |
| GET | `/orders/:id` | 订单详情（含明细） |
| GET | `/orders/search?q=` | 搜索订单 |

### 2.4 商品
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/products/summary` | 总数/上架/预警 |
| GET | `/products/list?page=&limit=` | 分页列表 |
| GET | `/products/search?q=` | 搜索 |
| GET | `/products/:id` | **商品详情+各仓库库存分布** |

### 2.5 仓库
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/warehouses` | 仓库列表 |
| GET | `/warehouses/:id` | 仓库详情（含库存汇总） |

### 2.6 审批
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/approvals/pending` | 待审批 |
| GET | `/approvals/recent` | 最近审批记录 |
| POST | `/approvals/:id/approve` | **审批通过**（需 `{"confirm":true}`，二次确认） |
| POST | `/approvals/:id/reject` | **审批拒绝**（需 `{"confirm":true}`） |

### 2.7 财务
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/finance/overview` | 财务总览+提醒 |
| GET | `/finance/recent?limit=` | **最新收支（费用+应收+应付）** |
| GET | `/finance/reminders` | 财务提醒 |
| GET | `/reports/sales` | 销售报表（近7日） |
| GET | `/reports/daily?date=` | **日报**（销售/日志/考勤/任务/知识） |
| GET | `/reports/weekly` | **周报**（近7日趋势） |

### 2.8 考勤
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/attendance/summary` | 出勤/迟到/早退/请假/加班 |
| GET | `/attendance/pending` | 待审批请假/加班/异常 |
| GET | `/attendance/my?limit=` | 我的考勤 |

### 2.9 任务
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/tasks/summary` | 任务统计 |
| GET | `/tasks/pending` | 待办任务 |
| GET | `/tasks/overdue` | **逾期任务** |
| GET | `/tasks/:id` | 任务详情 |

### 2.10 工作日志
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/logs/summary` | 今日提交/待审核/未提交 |
| GET | `/logs/today` | 今日日志 |
| GET | `/logs/pending` | 待审核日志 |

### 2.11 AI 课堂 / 培训
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/training/summary` | 知识库规模/闪卡/缺口 |
| GET | `/training/kb?q=&doc_type=` | 知识库检索 |
| GET | `/training/kb/:id` | 知识条目全文 |

### 2.12 企业微信互通
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/wecom/unread` | **未读消息+最近来信** |
| GET | `/wecom/contacts?q=` | 联系人 |
| GET | `/wecom/conversations` | 最近会话 |
| GET | `/wecom/messages/:convId` | 某会话消息 |
| POST | `/wecom/send` | **发送企微消息** |

### 2.13 快捷操作
| 方法 | 路径 | 说明 |
|---|---|---|
| POST | `/action/stock-count` | 发起库存盘点（写） |
| POST | `/action/cleanup-low-stock` | **生成补货建议**（只读） |

**权限表**: 读接口 `workbuddy:read`；写/发/审批/盘点 `workbuddy:write`。

---

## 三、方式 B：MCP tool_use（46 工具，推荐给 AI Agent）

### 3.1 原理
`/api/workbuddy/mcp/execute` 是 MCP 执行器。Agent（如 WorkBuddy 的 GLM/智能体）用 **tool name + args** 调用，网关内部转发到对应 REST 接口，复用调用者的 Authorization。

### 3.2 注册表
```
GET /api/workbuddy/mcp/tools
```
返回全部 46 个工具的标准 MCP schema（name/description/inputSchema），供 Agent 预注册。

### 3.3 执行
```
POST /api/workbuddy/mcp/execute
Content-Type: application/json
Authorization: Bearer <token>

{
  "tool": "get_boss_todo_priorities",
  "args": {}
}
```
响应: `{ tool, status, ok, result }` — `result` 为对应 REST 接口的 data。

### 3.4 46 工具全清单

**经营总览 (3)**
| tool | description | args |
|---|---|---|
| `get_dashboard_stats` | 仪表盘核心指标 | {} |
| `get_boss_todo_priorities` | 老板今日待办聚合 | {} |
| `get_ai_suggestions` | AI 智能建议 | {} |

**库存 (4)**
| tool | args |
|---|---|
| `get_inventory_summary` | {} |
| `get_inventory_alerts` | {} |
| `search_inventory` | **q** (必填) |
| `get_warehouse_inventory` | **id** (必填) |

**订单 (5)**
`get_orders_summary` {}  
`get_recent_orders` {limit?}  
`get_order_detail` {**id**}  
`search_orders` {**q**}

**商品 (4)**
`get_products_summary` {}  
`list_products` {page?, limit?}  
`search_products` {**q**}  
`get_product_detail` {**id**}

**仓库 (2)**
`list_warehouses` {}  
`get_warehouse_detail` {**id**}

**审批 (4)**
`get_pending_approvals` {}  
`get_approval_history` {limit?}  
`approve_request` {**id**, **confirm=true**}  
`reject_request` {**id**, **confirm=true**}

**财务 (6)**
`get_finance_overview` {}  
`get_finance_recent` {limit?}  
`get_finance_reminders` {}  
`get_sales_report` {}  
`get_daily_report` {date?}  
`get_weekly_report` {}

**考勤 (3)**
`get_attendance_summary` {}  
`get_attendance_pending` {}  
`get_my_attendance` {limit?}

**任务 (4)**
`get_tasks_summary` {}  
`get_pending_tasks` {}  
`get_overdue_tasks` {}  
`get_task_detail` {**id**}

**工作日志 (3)**
`get_logs_summary` {}  
`get_today_logs` {}  
`get_pending_logs` {}

**培训 (3)**
`get_training_summary` {}  
`search_training_kb` {**q**, doc_type?}  
`get_training_kb_item` {**id**}

**企业微信 (4)**
`get_wecom_unread` {}  
`list_wecom_contacts` {q?}  
`list_wecom_conversations` {}  
`send_wecom_message` {**content**, conversation_id? / conversation_name?, sender?}

**快捷操作 (2)**
`create_stock_count` {**warehouse_id**, note?}  
`generate_restock_suggestions` {limit?}

> **`* = 必填`**

---

## 四、典型场景示例（给 AI Agent 的自然语言映射）

| 老板说 | Agent 调用的 tool | 返回后的回答模板 |
|---|---|---|
| "今天有什么要处理的？" | `get_boss_todo_priorities` | "有 **29** 个任务逾期、**6** 个待审批（¥3580）、**24** 个商品低库存…" |
| "库存有预警吗" | `get_inventory_alerts` | "有 **24** 个商品低于预警线，最缺的是 **X**（库存 Y/预警 Z）" |
| "帮我看看任务进度" | `get_overdue_tasks` + `get_pending_tasks` | "**X** 个逾期（最长 40 天）、**Y** 个待办…" |
| "今天的考勤" | `get_attendance_summary` | "今日 **X** 人出勤，**Y** 人迟到…" |
| "谁还没交日志" | `get_logs_summary` | "**48** 名员工今日未提交" |
| "哪些商品要补货" | `generate_restock_suggestions` | "建议补货 **X** 个，预估成本 ¥Y" |
| "给我培训下考勤功能" | `search_training_kb` {q:"考勤"} | 返回知识库 5 条考勤培训内容 |
| "有企微消息吗" | `get_wecom_unread` | "**[风云]** 发来：'今晚加班吗？'" |
| "回条消息给风云" | `send_wecom_message` {conversation_name:"风云", content:"..."} | "已发送 ✅" |
| "通过这笔审批" | `approve_request` {id, confirm:true} | "审批 #X 已通过" |

---

## 五、接入步骤（若新客户端接入）

1. **拿 token**: 读 `/root/jxy-os/workbuddy/.wb-service-token`（或在 nginx 里配 `proxy_set_header Authorization 'Bearer <token>'` 自动注入）。
2. **方式 A**: 直接用任意 HTTP 客户端调 `/api/workbuddy/*`。
3. **方式 B (MCP)**:
   - 调 `GET /mcp/tools` 拉取 46 工具 schema，预注册到 Agent。
   - Agent 收到用户自然语言 → 匹配 tool → 调 `POST /mcp/execute`。
4. **写操作确认**: 涉及审批/发消息/盘点等写操作，Agent 必须先向用户二次确认（接口也内置 confirm 校验）。

---

## 六、服务信息

- **后端**: Express :3200 (pm2 `gdq-server`)
- **前端 SPA**: :8089 (nginx `/usr/share/nginx/workbuddy`)
- **配置**: /etc/nginx/conf.d/_workbuddy.conf
- **DB**: MySQL gdq 库（复用真实业务表，无独立 schema）
- **Token file**: /root/jxy-os/workbuddy/.wb-service-token (chmod 600, gitignored)
- **权限点**: workbuddy:read / workbuddy:write / workbuddy:delete

---

## 七、排错

| 现象 | 原因 | 处理 |
|---|---|---|
| 401 token 无效 | 未带 token / nginx 未注入 | 前端走 nginx 代理；外部手动带 token |
| 404 路由不存在 | 打了未实现的路径 | 对照本文档接口清单 |
| 403 无权限 | 账号无 workbuddy 权限点 | 给该 user 关联 workbuddy:* 权限 |
| 502 workbuddy backend down | gdq-server 挂了 | `pm2 restart gdq-server` |
| 数据空但接口 200 | 业务表本就无数据 | 属正常，非 bug |
