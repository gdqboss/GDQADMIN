# Plan: AI-HR 招聘模块（MVP）

**日期**: 2026-08-27
**作者**: 江小鱼
**目标**: 给 minip（应聘者端）和 gdqadmin（HR 端）加一个 AI 招聘对话能力

---

## 1. 范围

**做**:
- 应聘者在前端跟 AI 多轮对话，AI 主动挖掘信息
- 评价 = 预设条件达标 + AI 软素质整理 + 红黄牌
- 报告存库，HR 在后台看
- 预设条件由 HR 在 gdqadmin 配置

**不做**（v1）:
- 简历上传（PDF/图片）
- 多公司/多租户
- 通知（微信/邮件）
- 面试日程/offer
- 自动追问机制（v2 加）

---

## 2. 复用 vs 新建

| 资源 | 状态 | 用途 |
|---|---|---|
| `minip_hr_recruit` 表 | 已存在 | 存岗位信息（title/requirement/description/salary_range） |
| `minip_hr_applications` 表 | 已存在 | 存候选人投递记录（user_id/name/phone/status） |
| `ai_config` 表 | 已存在 | LLM 配置（provider/model/key），SGP 独立 |
| `minip-ai-hr.js` 旧路由 | 已存在 | 旧版对内 HR 评分，**不**复用，本模块独立 |

---

## 3. 数据流

```
[应聘者打开 minip]
     ↓
[选岗位（如果多岗位）/ 直接进入对话]
     ↓
POST /api/ai-hr/sessions
  → INSERT ai_hr_conversations (session_id, job_id, status='active')
  → 返回 session_id
     ↓
[多轮对话]
POST /api/ai-hr/chat {session_id, user_message}
  → LLM (prompt = 系统角色 + 预设条件清单 + 历史 + user_msg)
  → SSE 流式返回
  → 客户端 message 落地
  → 服务端消息入库 (ai_hr_conversations.messages JSON)
     ↓
[应聘者主动结束 / AI 判断信息够了]
POST /api/ai-hr/evaluate {session_id}
  → LLM (prompt = 评价模板 + 完整对话 + 预设条件)
  → 返回结构化 JSON
  → INSERT ai_hr_evaluations
  → UPDATE conversation.status='completed'
     ↓
[HR 在 gdqadmin 看报告]
GET /api/ai-hr/evaluations?job_id=&status=
GET /api/ai-hr/evaluations/:id
  → 含完整对话记录 + 软素质评分（每项带证据引用）
```

---

## 4. 数据库设计

### 4.1 `ai_hr_jobs`（岗位扩展表，**非新建**——复用 minip_hr_recruit）
但因为旧表的 requirement 是 text，不够灵活，所以新建一张轻量"条件配置"表：

### 4.2 `ai_hr_job_presets`（新建）——岗位的预设招聘条件
```sql
CREATE TABLE ai_hr_job_presets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,                          -- FK minip_hr_recruit.id
  preset_questions JSON NOT NULL,               -- 硬性条件问题清单
  -- 例：[
  --   {key:'experience_years', question:'几年经验？', requirement:'≥2', weight: 1, required:true},
  --   {key:'education', question:'学历？', requirement:'大专以上', weight: 0.8, required:true},
  --   {key:'tech_stack', question:'熟悉哪些技术？', requirement:'Vue/React', weight: 1, required:true},
  --   {key:'available_at', question:'可到岗时间？', requirement:'两周内', weight: 0.5, required:false},
  -- ]
  soft_traits JSON,                              -- 软素质评分维度
  -- 例：[
  --   {key:'communication', label:'表达能力', weight: 0.2},
  --   {key:'logic', label:'逻辑思维', weight: 0.3},
  --   {key:'learning', label:'学习意愿', weight: 0.2},
  --   {key:'teamwork', label:'团队协作', weight: 0.15},
  --   {key:'stability', label:'稳定性', weight: 0.15}
  -- ]
  max_questions INT DEFAULT 12,                 -- AI 主动问的最多个数
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.3 `ai_hr_conversations`（新建）——对话 session
```sql
CREATE TABLE ai_hr_conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(64) NOT NULL UNIQUE,        -- 前端 UUID
  job_id INT NOT NULL,
  candidate_name VARCHAR(50),
  candidate_phone VARCHAR(20),
  messages JSON NOT NULL,                        -- [{role, content, ts, preset_key?}]
  -- preset_key: 如果这条 AI 问题是触发预设条件，标记对应 key
  preset_answers JSON,                           -- {experience_years: '3', education:'本科', ...}
  status ENUM('active','completed','abandoned') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_job_status (job_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.4 `ai_hr_evaluations`（新建）——最终评价
```sql
CREATE TABLE ai_hr_evaluations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL UNIQUE,
  job_id INT NOT NULL,
  candidate_name VARCHAR(50),
  overall_score TINYINT,                         -- 0-100 综合分
  preset_results JSON,                           -- [{key, question, answer, passed, weight, requirement}]
  soft_scores JSON,                              -- [{key, label, score, evidence, weight}]
  -- evidence: 对话引用片段（让 HR 能回看验证）
  red_flags JSON,                                -- ["近 2 次跳槽均<1年", ...]
  highlights JSON,                               -- ["Vue 熟练", "有管理经验", ...]
  recommendation ENUM('strong','consider','reject') NOT NULL,
  ai_summary TEXT,                               -- AI 给 HR 的一句话总结
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_job (job_id),
  KEY idx_rec (recommendation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 5. 后端 API（routes/ai-hr.js）

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET | /api/ai-hr/jobs | public | 列出开放岗位（status='open'） |
| GET | /api/ai-hr/jobs/:id/presets | public | 取某岗位的预设条件 |
| POST | /api/ai-hr/sessions | public | 创建对话 session |
| POST | /api/ai-hr/chat | public | SSE 流式对话 |
| POST | /api/ai-hr/evaluate | public | 触发 AI 生成最终评价 |
| GET | /api/ai-hr/evaluations | gdqadmin | HR 看报告列表 |
| GET | /api/ai-hr/evaluations/:id | gdqadmin | HR 看报告详情 |
| CRUD | /api/ai-hr/admin/jobs/:id/presets | gdqadmin | HR 配预设条件 |

**权限**:
- 应聘者端（minip）= public（不登录，纯访客对话）
- HR 端（gdqadmin）= `requirePermission('ai_hr:read'/'ai_hr:write')` 加 RBAC 2 个新 permission

---

## 6. LLM Prompt 设计（核心）

### 6.1 对话 Prompt（System）
```
你是 [公司名] 的 AI 招聘助手，正在跟应聘者 [岗位名] 对话。

【岗位要求】
[minip_hr_recruit.description + salary_range]

【你必须主动问的硬性条件】（按优先级，问完为止）
[preset_questions 数组]

【软素质观察清单】（自然观察，不直接问）
[soft_traits 数组]

【对话规则】
1. 一次只问 1 个问题，不要连珠炮
2. 候选人答非所问 → 礼貌拉回
3. 信息矛盾 → 委婉追问确认
4. 候选人主动结束 → 回复"好的，感谢你的时间，我们会在 3 个工作日内联系你"
5. 最多问 max_questions 轮，超过就结束
6. 你是 HR 不是技术专家，不要解释技术细节

【输出格式】
普通对话：自然中文
当候选人回答了一个预设条件：在回复末尾追加一行
<<PRESET_ANSWER key={key} value={提取的值}>>
```

### 6.2 评价 Prompt（最后一次性）
```
你是 HR 评估专家。基于以下完整对话，给出结构化评价。

【岗位】 [title]
【预设条件】 [preset_questions]
【候选人原始回答】 [preset_answers]
【完整对话】 [messages]

【输出 JSON】
{
  "overall_score": 0-100,
  "preset_results": [
    {"key":"experience_years","answer":"3年","passed":true,"weight":1}
  ],
  "soft_scores": [
    {"key":"communication","label":"表达能力","score":8,"evidence":"回答有条理...","weight":0.2}
  ],
  "red_flags": [...],
  "highlights": [...],
  "recommendation": "strong|consider|reject",
  "summary": "一句话总结"
}

【评分原则】
- overall_score = preset_results 加权分 * 0.6 + soft_scores 加权分 * 0.4
- soft_scores 每项 score 1-10，evidence 必须引用对话原文
- red_flags 必须有对话依据，不是凭空捏造
- 矛盾信息 → 扣分并入 red_flags
```

---

## 7. 前端

### 7.1 minip 端（应聘者）

**`views/ai-hr/AiHrChat.vue`**
- 顶部：岗位名 + 进度（已答 3/8）
- 中间：聊天气泡列表（用户右、AI 左，自动滚到底）
- 底部：输入框 + 发送按钮 + "结束对话"按钮
- 结束对话后：跳转评价报告预览页（minip 上可看自己的分数）

**`views/ai-hr/AiHrJobSelect.vue`**（可选，v1 如果只一个岗位可跳过）

### 7.2 gdqadmin 端（HR）

**`views/ai-hr/AiHrReportList.vue`**
- 表格：候选人 / 岗位 / 综合分 / 推荐 / 红黄牌数 / 时间
- 筛选：按岗位 / 按分数段 / 按推荐

**`views/ai-hr/AiHrReportDetail.vue`**
- 顶部：候选人基本信息 + 综合分（数字卡片）
- 中部：预设条件达标（绿✓红✗）
- 中部：软素质雷达图（5 维，每维 1-10）
- 下部：红黄牌 + 高亮 + AI 一句话总结
- 最下：完整对话记录（可展开）

**`views/ai-hr/AiHrJobPreset.vue`**
- 选择岗位
- 配预设问题列表（增删改：key/问题/要求/权重/必填）
- 配软素质维度列表
- 保存

**Sidebar** 加 `AI 招聘报告` 入口（gdqadmin）

---

## 8. 文件改动清单

### 后端
- `routes/ai-hr.js`（新建）
- `index.js`（mount `/api/ai-hr`）
- `middleware/rbac.js`（加 `AI_HR_READ`/`AI_HR_WRITE` 权限常量）
- `db/migration-ai-hr-20260827.sql`（新建）

### minip 前端
- `views/ai-hr/AiHrChat.vue`（新建）
- `router/index.js`（加路由 `/ai-hr/chat`）

### gdqadmin 前端
- `views/ai-hr/AiHrReportList.vue`（新建）
- `views/ai-hr/AiHrReportDetail.vue`（新建）
- `views/ai-hr/AiHrJobPreset.vue`（新建）
- `views/Sidebar.vue`（加菜单项）
- `router/index.js`（加路由）

### profile-config.js（**禁止碰**——零硬编码铁律）
- ai-hr 模块通过 `server_modules` 表 + 模块自动发现机制加载
- 测试阶段自动 INSERT 到 profile 1（开发环境）；其它 profile 不动

### middleware/rbac.js（加 3 个 PERMISSIONS 常量）
```js
AI_HR_READ: 'ai_hr:read',
AI_HR_WRITE: 'ai_hr:write',
AI_HR_DELETE: 'ai_hr:delete'
```

### rbac_permissions 表（INSERT 三件套）
```sql
INSERT INTO rbac_permissions (perm_key, description) VALUES
  ('ai_hr:read', 'AI HR 招聘 - 查看报告'),
  ('ai_hr:write', 'AI HR 招聘 - 配置岗位预设条件'),
  ('ai_hr:delete', 'AI HR 招聘 - 删除对话/报告');
```

### server_modules 表（自动 INSERT 到 profile 1）
```sql
INSERT INTO server_modules (server_profile_id, module_key, enabled)
  VALUES (1, 'ai-hr', 1)
  ON DUPLICATE KEY UPDATE enabled = 1;
```
（其它 profile 不动 — 波哥拍板才同步）

---

## 9. 验证（每片必做）

1. `node -e "import('./routes/ai-hr.js').then(r => console.log('ok'))"` — 语法
2. `curl` POST /api/ai-hr/sessions → 拿到 session_id
3. `curl` POST /api/ai-hr/chat → 看到 SSE 流
4. 跑 3 轮对话后 POST /evaluate → 拿 JSON
5. gdqadmin 浏览器看列表 + 详情（puppeteer 或真浏览器）

---

## 10. 风险与回退

| 风险 | 回退 |
|---|---|
| LLM 返回格式不稳定（不返 <<PRESET_ANSWER>>） | prompt 加 1-shot example；再不通过则降级为对话结束后一次性提取 |
| SSE 在 nginx 上断流 | 加 `X-Accel-Buffering: no` 头 |
| 对话无限增长 | 前端 max_questions 兜底 + 后端按 preset 数量判断 |
| LLM key 失效 | ai_config 表已支持多 provider；fallback 到下一个 |
| 评价幻觉 | evidence 必填 + red_flags 必有对话依据；HR 看报告时附"回看对话"按钮 |

---

## 11. 落地顺序（5 片交付）

| # | 内容 | 验证 |
|---|---|---|
| 1 | DB 3 张表 + migration 跑通 | `SHOW TABLES` + `DESC` |
| 2 | 后端骨架（jobs 列表 + sessions 创建 + 不含 LLM 的 echo chat） | curl 通 |
| 3 | 后端 LLM 接入（SSE chat + preset answer 提取 + evaluate） | curl 看到 AI 回复 + 评价 JSON |
| 4 | minip 端聊天页（Vue3 SSE 客户端） | 浏览器跑完整对话 |
| 5 | gdqadmin 端（报告列表 + 详情 + 岗位配置） | 浏览器跑完整 HR 流程 |