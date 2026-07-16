## [2026-07-15 13:50] Excel 报告 — 门店明细打印页改造（A 方案）

**操作人**: agent
**影响 profile**: 1（新加坡本机验证）
**commit**: pending

### 需求（波哥截图，Excel 报告管理页 → 门店明细 → 打印预览红色字）

1. consolidate all the same designs（合并同 design 的不同 model 行）
2. Include stock #（保留 SKU 编号）
3. Add total quantity per color（每行颜色合计）
4. Add overall total quantity sold（整店总销量）

### 选型（A 方案确认）

取消 model 维度，整店一张 color×size 大表，cell 内多 SKU 摞一起；加"合计"列 + tfoot 整店总销量。

### 改动文件

- `views/import/ImportDetail.vue` line 535-555 `printStoreDetailFor` 重写
  - 取消 `model` 强制参数（兼容旧调用，但默认走整店）
  - `entry.byModel.flatMap(m => m.skus || [])` 合并所有 model SKU
  - `buildMatrix(allSkus)` 一次性构建整店 matrix
  - 标题 `门店明细 · ${storeName} · ${N} designs consolidated`
  - subtitle 含 designs/color/size/SKU + 总销量 PCS
  - `showTotals: true` 启用合计列 + tfoot
- `views/import/ImportDetail.vue` line 691-754 `buildColorSizeTableHTML`
  - 返回值从 `string` 改为 `{ html, grandTotal, colorTotals }`
  - 新增 `showTotals` 选项：表头加"合计"列、行末加 rowTotal 单元格、tfoot 加 grandTotal
- `views/import/ImportDetail.vue` line 545 / 559 / 758 / 771 — 5 个调用点改 `const { html } = ...` 解构
- `views/import/ImportDetail.vue` line 682-686 — CSS 加 `.color-total` `.color-total-cell` `tfoot td` 琥珀色（#fff7e6 / #fef3c7 / #d97706）

### 构建 + 部署

- `npm run build` → ImportDetail-CkbAkEi6.js（34045 bytes）
- `mv /home/gdq/dist /home/gdq/dist.bak.HHMMSS && cp -r /root/src/dist /home/gdq/dist`（**注意：nginx root 是 `/home/gdq/dist` 不是 `/home/gdq/server/dist`**，AGENTS.md 路径陷阱再踩一次）
- `curl -I https://wecom.gdqshop.cn/assets/ImportDetail-CkbAkEi6.js` → 200 OK

### 验证

- ✅ curl 直接拉 nginx 返回的 chunk，grep 命中：`designs consolidated`(1) `showTotals`(2) `color-total`(5) `grandTotal`(3) `门店明细`(1) `tfoot`(9) `flatMap`(1) `合计`(4) `总销量`(3)
- ✅ 反 minify 后源码片段可见 `d.byModel.flatMap(f=>f.skus||[])` + `entry.byModel[0].model` 兼容逻辑
- ⚠️ **puppeteer 实测失败** —— OpenClaw 共享 Chrome 实例（PID 1028643）命中 immutable 缓存，仍返回旧 chunk；用 curl 直接拉 nginx 字符串验证替代 ✅
- ⏳ **波哥浏览器实测** —— `Ctrl+Shift+R` 硬刷 → `https://wecom.gdqshop.cn/#/import-detail/27` → 展开 SM STORE - MALL OF ASIA → 点任意 model 的 🖨

### 影响范围

- 只动 `views/import/ImportDetail.vue` 单文件
- 不影响其他页面（其他 5 个 buildColorSizeTableHTML 调用点行为不变）
- 旧"按 model 单独打印"逻辑保留（兼容旧按钮，但默认走整店）
- 待波哥验证 → sync-sgp-to-bj.sh（**未跑，等波哥明确**）
## [2026-07-15 20:42] feat(log): 钉钉式已阅 + 头像 (4 类日志通用 + oa 同步)

**操作人**: agent
**影响 profile**: 1 (开发) → 2 (BJ, 待同步)
**commit**: <待 commit>

### 改动文件

#### 后端
- `routes/log-interactions.js` — 重写支持 log_type (work_log/visit_log/share_log/feedback) + visibility 4 档 + MySQL CTE 递归上级链 + UNIQUE 去重(钉钉模式)
- `routes/settings.js` — 加 GET/PUT /api/settings/module/:key 通用 handler
- `index.js` — 拆 /api/settings mount (menu-* admin only / 通用 auth only)

#### 前端
- `components/ReaderAvatars.vue` — 新建通用钉钉式已阅头像堆叠组件 (props: logType/logId/max/size; events: open-detail)
- `views/oa/WorkLogManage.vue` — oa 端同步: 卡片底部加 ReaderAvatars + readers 弹窗 + handleReaderDetail/avatarUrl/avatarColor 函数
- `views/logs/WorkLogManage.vue` — logs 端集成 (列表行 + 4 按钮组后 + readers 弹窗)
- `views/logs/VisitLogManage.vue` — logs 端集成 (卡片底部 + readers 弹窗)
- `views/logs/ShareLogManage.vue` — logs 端集成 (表格列 + readers 弹窗)
- `views/logs/FeedbackManage.vue` — logs 端集成 (表格列 + readers 弹窗)

### MySQL 改动

```sql
-- log_reads 表加 log_type 列
ALTER TABLE log_reads ADD COLUMN log_type VARCHAR(20) NOT NULL DEFAULT 'work_log' AFTER log_id;
ALTER TABLE log_reads DROP INDEX uk_log_user;
ALTER TABLE log_reads ADD UNIQUE KEY uk_log_type_id_user (log_type, log_id, user_id);

-- settings 表 4 个 visibility 默认值
INSERT INTO settings (`key`, value) VALUES
  ('work_log:read_visibility', 'supervisor_and_recipients'),
  ('visit_log:read_visibility', 'supervisor_and_recipients'),
  ('share_log:read_visibility', 'supervisor_and_recipients'),
  ('feedback:read_visibility', 'supervisor_and_recipients');
```

### 重启验证

- `pm2 restart gdq-server` → "GDQ server running on port 3200"
- curl POST `/api/log-interactions/read` 4 类 × 3 ID = 12 calls 全 200
- curl GET `/api/log-interactions/readers/work_log/6` 返带头像列表 (江清波 avatar / 测试员工 null)
- 401 未登录拦截 / 400 log_type 缺失 / 403 visibility 拒绝 — 全部验证通过
- npm run build → "oa: 7 chunk(s)" (从 6 升 7) + ReaderAvatars-mB1vnFaN.js (2.9KB) 新 chunk
- rsync dist → /home/gdq/dist/
- curl https://wecom.gdqshop.cn/assets/WorkLogManage-DQ2frxpt.js → 200 / 57KB
- curl https://wecom.gdqshop.cn/assets/ReaderAvatars-mB1vnFaN.js → 200 / 2.9KB

### 设计原则 (zero-hardcode)

- visibility 4 档存 settings 表 (supervisor_only/recipients_only/supervisor_and_recipients/all) — 不硬编
- log type 配置在 LOG_TYPE_CONFIG 字典 — 加新日志类型只动 1 处
- MySQL CTE 递归上级链到 admin — 自动判断
- UNIQUE KEY (log_type, log_id, user_id) — 自动去重

### 影响范围

- 后端: 4 类日志 API 全通用, 加新类型零代码改动
- 前端: 5 个 view 加已阅头像
- BJ profile 2 server_modules 勾的是 oa 不是 logs → 同步到 BJ 时需 oa/WorkLogManage.vue 也在内
- puppeteer 实测未跑 — 等波哥浏览器实测或开 headless Chrome
- BJ 同步未跑 — 等波哥批准 (按 sync-sgp-to-bj.sh 流程)

## [2026-07-16 19:15] 工作日志 — 列表显示作者 + 修复"我的"分类

**操作人**: agent
**影响 profile**: 1 (SGP 本机验证); 2 (BJ 待 push)
**commit**: pending

### 背景

波哥反馈"日志列表看不出是谁写的 + 分类到我的好像也不对"。

### 根因

1. **后端 SELECT 已返 `creator_name`** (work-logs.js line 399-407 LEFT JOIN users),但**前端 `WorkLogManage.vue` list 卡片 (line 836-909) 完全没渲染这个字段**。
2. **tab 名映射错**:前端 `activeTab = 'my' | 'received' | 'all' | 'templates'`, 后端 `logType = 'mine' | 'received' | 'all'`, 前端发 `'my'` → 后端收到 `'my'` 不匹配 `'mine'` → fallback 到 `isAdmin ? 'all' : 'mine'`, admin 用户点"我的"看到的也是 ALL。
3. **headerTitle 兜底不对**: `WorkLogManage.vue:99` 取 `user_name || name`, 实际字段是 `creator_name`。

### 改动文件

- `views/logs/WorkLogManage.vue` line 99: headerTitle 增加 `creator_name` 优先
- `views/logs/WorkLogManage.vue` line 170-177: 新增 `tabToBackend = { my: 'mine', ... }` 映射
- `views/logs/WorkLogManage.vue` line 836-846: list 卡片插入作者行 (头像 + 姓名 + 部门)
- `i18n/zh.js` / `i18n/en.js` / `i18n/ms.js`: 新增 `logs.unknownAuthor`
- `routes/work-logs.js` line 478-481: parsedLogs 兜底, JOIN miss 时显示 `用户#${user_id}`

### 重启验证

- `pm2 restart gdq-server`
- `curl GET /api/work-logs?type=mine` 返回 creator_name 字段

### 影响范围

- BJ profile 2 server_modules 勾选 logs → 同步到 BJ 时 WorkLogManage.vue 在内 (3 处 + 后端 1 处 + 3 个 i18n)
- 不用改 server_modules, 也不需要 INSERT rbac_permissions (没加新权限)
