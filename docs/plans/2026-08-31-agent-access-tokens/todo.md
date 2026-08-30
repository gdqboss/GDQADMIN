# Agent Access Tokens - todo 清单

## 阶段 0：准备 ✅
- [x] plan.md 落地（设计 + 薄片分解）
- [x] todo.md 落地（本文件）

## 薄片 1: 表 + middleware
- [ ] MySQL `CREATE TABLE user_api_tokens`（含 index_hash）
- [ ] INSERT `rbac_permissions`: `agent_access:read`
- [ ] middleware/rbac.js PERMISSIONS 加 `'agent_access:read'`
- [ ] 新文件 `middleware/apiTokenAuth.js`
- [ ] 验证：`curl https://wecom.gdqshop.cn/api/workbuddy/health -H "Authorization: Bearer sbk_fake"` 返 401
- [ ] commit: `feat(agent-access): add user_api_tokens table + middleware`

## 薄片 2: 5 个 CRUD 端点
- [ ] 新文件 `routes/agent-access.js`（list / create / rotate / patch / delete）
- [ ] `index.js` mount: `app.use('/api/agent-access', auth, apiLimiter, requireRole('admin'), agentAccessRoutes)`
- [ ] curl 5 端点 401 (无 token) / 403 (非 admin) / 200 (admin)
- [ ] commit: `feat(agent-access): add /api/agent-access/* 5 endpoints`

## 薄片 3: 前端按钮 + 弹窗骨架
- [ ] SystemSettings.vue 用户列表行加 "🔑 Agent Token" 按钮（line 1140 旁）
- [ ] 新 dialog 组件 `AgentTokensDialog.vue`（list 段 + new 段骨架）
- [ ] 浏览器实测：按钮可见、弹窗打开、空白不报错
- [ ] commit: `feat(agent-access-ui): add Agent Token button + dialog skeleton`

## 薄片 4: 前端完整流程
- [ ] create 流程：表单 → POST → 一次性明文展示层 → 复制按钮
- [ ] list 流程：表格 + 前缀 + 备注 + 状态 + 操作
- [ ] revoke 流程：DELETE + 二次确认
- [ ] rotate 流程：弹新明文
- [ ] curl 三态：curl 新生成的 token 调 `/api/workbuddy/health` 返 200
- [ ] commit: `feat(agent-access-ui): full create/copy/list/revoke/scope flow`

## 薄片 5: 双写 + git commit
- [ ] DEV_LOG.md 末尾加一条（按 AGENTS.md 格式）
- [ ] ai_class_knowledge INSERT 一条（标题 + 3 段 lessons + 5 个 reference）
- [ ] pm2 restart gdq-server
- [ ] git add + commit: `docs(agent-access): log + knowledge + commit`

## 完成标准
- [ ] admin 能在系统设置 / 用户列表点 🔑 Agent Token → 看某用户已开 token
- [ ] admin 能开新 token → 一次性看到完整 token → 复制 → curl 验证可用
- [ ] admin 能 revoke / rotate / 改 scopes
- [ ] 非 admin 调任何端点 403
- [ ] DEV_LOG + ai_class_knowledge 双写完成
