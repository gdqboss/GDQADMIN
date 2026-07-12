# SmartBiz 后端开发约束 (AGENTS.md)

> 本文件由 Claude Code / Hermes Agent 启动时自动加载 — 修改它 = 改未来的 AI 行为
> 最后更新: 2026-07-12 (波哥与 minimax 共同确立)

---

## 一、项目大图

| 项目 | 路径 / 地址 |
|---|---|
| 后端入口 | `/root/server/index.js` (Node ESM, 端口 3200) |
| 路由目录 | `/root/server/routes/` (132+ 个 routes) |
| 中间件 | `/root/server/middleware/` (auth / rbac / rateLimit 等) |
| 数据库 | MySQL `127.0.0.1`, db=`gdq` |
| 本机角色 | 新加坡开发服务器 (profile 1 永远全选) |
| 别名 (symlink) | `/home/gdq/server` → `/root/server` |

**这台机 = 新加坡开发服务器 = 唯一开发源头。**

---

## 二、目标服务器管理铁律 (与前端 AGENTS.md 一致)

### 加新模块强制流程 (不能少任何一步)
1. 在 `/root/server/routes/` (或 `/home/gdq/server/routes/`, symlink) 写新 `.js`, export router
2. 在 `index.js` 顶部 `import` + `app.use('/api/xxx', auth, ...)` mount
3. **MySQL INSERT `rbac_permissions`** 三件套 (read/write/delete) + 描述
4. **`/root/server/middleware/rbac.js`** (或 `/home/gdq/server/middleware/rbac.js`) PERMISSIONS 字典加常量
5. 每个 endpoint 挂 `requirePermission('xxx:action')`
6. **MySQL INSERT `server_modules`** 到目标 profile:
   - profile 1 (新加坡开发): 自动 INSERT, 因为永远全选
   - profile 5 (labor): 看 labor 前端要不要用, 要用就 INSERT
   - 其他 profile: 不动 (除非波哥明确说同步到生产)
7. 重启 server (`pm2 restart gdq-server` 或 `kill <PID>`, manager 自动 respawn)
8. `curl` 验证新路由 200 + 401 (无 token) + 403 (无权限)
9. 不准私自决定 "这个模块要不要同步到哪个 profile" — 必须问波哥

### 命名约定
- module_key: `lowercase-with-dash` (例 `labor-ai-agent`, `finance-simple`)
- 权限点: `module:action` (例 `jobsite:read`, `worker:write`)
- 三件套: `read / write / delete`
- **`write` 包含 POST (新增) + PATCH (编辑)**, 不再单独设 `create`

### 模块路由别名映射
`MODULE_KEY_ALIASES` 在 `/root/server/routes/server-profiles.js`,
新加 module_key 必须同步加 alias (前后端 module_key 一致).

---

## 三、权限铁律

### 后端 middleware
- 每个 route 必须挂 `requirePermission('xxx:action')`
- **admin 例外**: `req.user.role === 'admin'` 自动放行, 不查权限
- 其他所有人查 `rbac_role_permissions` 表

### 不能跳过的事
- 写了 route 不 mount → 死代码 (前端 404)
- mount 了不 INSERT `rbac_permissions` → 角色配置里看不到 (前端无法授权)
- INSERT 了不挂 `requirePermission` → 任何人都能调 (无权限隔离)
- 不 INSERT `server_modules` → 该前端用户调不到 (部署铁律违反)

---

## 四、模块化铁律

1. 改 A 不影响 B (波哥最强诉求)
2. JXY 需求在原模块开发 — 现有路由优先复用, 不另写
3. 功能相同的模块统一通用 — 多个 count/聚合 用同一接口, 不造 N 个并行查询
4. 现有日志/财务/考勤/任务 模块要复用 — 可以改造, 不能另起灶

---

## 五、不允许做的事

- 改 routes 文件后不 mount
- mount 后不 INSERT `rbac_permissions`
- INSERT 后不挂 `requirePermission`
- 不 INSERT `server_modules` 就部署
- 重启 server 不验证
- 用 `[REDACTED]` 占位密码 (要从 .env 读真值)
- 改 `dist/` 静态产物 (那是 vite build 产物)

---

## 六、当前所有 profile 状态 (2026-07-12)

```
profile 1 新加坡开发  (43.134.31.232, wecom.gdqshop.cn)        — 54 modules, 全选
profile 2 北京彩美特  (81.70.199.64, claw.gdqshop.cn)          — 28 modules
profile 3 3号仓库    (43.160.238.201, mywh3.com)              — 25 modules
profile 4 上海智慧家园 (111.229.144.150, gdqshop.cn)          — 30 modules
profile 5 SmartBiz   (100.64.122.98, wecom.gdqshop.cn/labor/) — 13 modules
```

任何改动必须先 `mysql SELECT * FROM server_modules WHERE server_profile_id = ?`
确认目标 profile 已勾选, 再动手.