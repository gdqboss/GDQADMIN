# Agent Access Tokens (用户接入 token)

> 2026-08-31 江小鱼 — 波哥立: "完善开发 API，给各种 agent 可以根据不同用户的 token 一句话接人；admin 在用户列表复制接入信息"

## 1. 现状实查

### SGP 后端现状
- JWT 认证：`/api/auth/login` 返 JWT → 放 `Authorization: Bearer <jwt>`（`/root/server/middleware/auth.js`）
- JWT 改密码后失效（`auth.js` line 40-50 `password_changed_at` 检查）
- rbac：admin 永真 / superuser 全权限 / 其他走 `rbac_role_permissions` + `user.permissions`
- workbuddy 已经走 JWT 模式做 agent 入口（`/api/workbuddy/*`），但鉴权复用同一套

### 系统设置页现状
- `/root/server/views/settings/SystemSettings.vue` line 690-697：6 个 tab（users / departments / job-levels / customers / payment / ai-config）
- 用户列表在 line 1091-1164，操作按钮在 line 1140-1156（编辑 / 启用-禁用 / 删除）
- 没有 "Agent Token" 相关入口

### 缺什么
- ❌ 没有 "给员工开接入 token" 通道（agent 想调系统只能 ask "把你密码给我"）
- ❌ 没有长期 API Key（JWT 改密码失效，agent 频繁 401）
- ❌ 没有 "agent-friendly 的 token 复制 UI"

## 2. 设计

### 2.1 token 形态

- 前缀：`sbk_`（SmartBiz Key，类比 `sk-` / `ghp_` / `xoxb-`）
- 长度：32 字节随机 → base64url → 总长 50+ 字符
- 存储：**SHA256(token) 只存 hash**，永不存明文（与现有 JWT 设计 `auth.js` 中 `hashToken` 一致）
- 前缀展示：仅显示 `sbk_xxx...yyy` 12 字符（前 4 后 4），用户能区分不同 token
- 一次性明文：创建时**仅此一次**返完整 token 给前端展示 + 复制，**之后不可再查**

### 2.2 鉴权路径

新 middleware `apiTokenAuth`，**与 JWT 完全独立**：
- 前端：`Authorization: Bearer sbk_xxxx`
- middleware 查 `user_api_tokens.token_hash = SHA256(token)` → 取 user_id
- 走 **用户身份**（不是 admin 永真），权限点合并：rbac_role_permissions ∪ token.scopes（JSON 数组）
- admin 创建的 token **默认继承用户全部权限**，scope 字段为空 = 全部
- scope 不空 = **降权**（intersection with user's effective perms）

### 2.3 DB schema

```sql
CREATE TABLE user_api_tokens (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id       BIGINT NOT NULL,                    -- 给哪个用户开
  created_by    BIGINT NOT NULL,                    -- 谁创建的（admin 用户的 id）
  name          VARCHAR(100) NOT NULL,              -- 备注如 "波哥专属 agent"
  token_prefix  VARCHAR(20) NOT NULL,               -- sbk_abc1 (前 8 字符, 用于列表展示)
  token_hash    CHAR(64) NOT NULL UNIQUE,           -- SHA256 hex
  scopes        JSON NULL,                          -- NULL=继承用户全部, [...] 数组=降权
  expires_at    DATETIME NULL,                      -- NULL=永不过期
  revoked_at    DATETIME NULL,                      -- 作废时间
  last_used_at  DATETIME NULL,
  use_count     BIGINT DEFAULT 0,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id, revoked_at),
  INDEX idx_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.4 API 设计 (5 端点)

| Method | Path | 权限 | 说明 |
|---|---|---|---|
| `GET`    | `/api/agent-access/tokens?user_id=X` | `admin` 角色 | 列出某用户的所有 token（**不含 hash**） |
| `POST`   | `/api/agent-access/tokens` | `admin` 角色 | 创建 token，body: `{user_id, name, scopes?, expires_in_days?}`，**返一次性明文** |
| `POST`   | `/api/agent-access/tokens/:id/rotate` | `admin` 角色 | 换新 token（旧作废，新明文返一次） |
| `PATCH`  | `/api/agent-access/tokens/:id` | `admin` 角色 | 改 name / scopes / expires_at |
| `DELETE` | `/api/agent-access/tokens/:id` | `admin` 角色 | 作废（soft delete，revoked_at=NOW()） |

rbac 新增 1 个点：`agent_access:read`（list/rotate/patch 都用 read 包含，admin 永真所以不细拆 write）

### 2.5 前端设计

- 入口：用户列表行操作区（line 1140 旁）加 "🔑 Agent Token" 按钮
- 弹窗：3 段
  1. **已开 token 列表**（前缀 + 备注 + 创建人 + 创建时间 + 最后使用 + 状态 + 操作 [复制(若有明文)/ 换新 / 改权限 / 作废]）
  2. **新建 token**：备注名 + 有效期下拉（永不过期/30天/90天/365天）+ 可选 scopes 勾选（默认空=全权限）+ [生成] 按钮
  3. **一次性明文展示**（创建后浮层，显示完整 token + 复制按钮 + curl 示例）

### 2.6 同步策略

- **仅 SGP 开发**，暂不同步 HK（HK 工作量巨大，AGENTS.md 铁律 #20 严格遵守）
- HK 同步时机：SGP 稳定 + 波哥明确说"同步 HK"再走 `gdq-module-verify-then-sync` recipe
- **当前任务不做 HK 同步**

## 3. 薄片分解

| # | 目标 | 独立验证 | commit |
|---|------|----------|--------|
| 1 | 建表 + apiTokenAuth middleware | `curl 401 (无 token)` / `curl 200 (admin 持 token)` | `feat(agent-access): add user_api_tokens table + middleware` |
| 2 | 5 个 CRUD 端点 | curl 5 端点 200/401/403 | `feat(agent-access): add /api/agent-access/* 5 endpoints` |
| 3 | 用户列表加按钮 + 弹窗骨架 | 浏览器看到按钮 + 点开弹窗 | `feat(agent-access-ui): add Agent Token button + dialog skeleton` |
| 4 | 前端完整流程（创建/复制/列表/作废/作用域）+ curl 三态 | 全流程浏览器实测 | `feat(agent-access-ui): full create/copy/list/revoke/scope flow` |
| 5 | DEV_LOG + ai_class_knowledge + git commit | 双写完成 | `docs(agent-access): log + knowledge + commit` |

## 4. 风险与缓解

| 风险 | 缓解 |
|------|------|
| Token 泄露后没法 revoke | 已有 DELETE 端点 + revoked_at 字段，middleware 检查 revoked_at IS NULL |
| Token 跟 JWT 冲突 | middleware 优先级：先看 `sbk_` 前缀走 tokenAuth，否则 JWT |
| Admin 误改 scopes 导致用户调不通 | PATCH 端点要求 `scopes` 是 user 已有权限的子集（validate on write） |
| 大表 token_hash 查询性能 | `INDEX idx_hash` + SHA256 是 O(1) 查找 |
| HK 端不同步导致用户复制 token 在 HK 不能用 | 文档明示"仅 SGP 有效"；HK sync 决策待波哥拍板 |

## 5. 同步策略（SGP → 消费端）

- **不做**任何消费端同步
- HK / DDA / macau 当前阶段不需要这个能力
- 文档明确：本功能**仅 profile 1（SGP）有效**
- 后续同步时机：等波哥明确指令
