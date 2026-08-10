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

## 模块化铁律

1. 改 A 不影响 B (波哥最强诉求)
2. JXY 需求在原模块开发 — 现有路由优先复用, 不另写
3. 功能相同的模块统一通用 — 多个 count/聚合 用同一接口, 不造 N 个并行查询
4. 现有日志/财务/考勤/任务 模块要复用 — 可以改造, 不能另起灶

### 零硬编码铁律（2026-08-12 波哥原话"未来可能还有其它模块和前端，所以这些都不要硬编"）

详见 skill: `zero-hardcode-modular-architecture`. 核心 4 条:
1. **模块清单 = 自动发现**（扫 views/ 不写死 PROFILE_MODULES）
2. **前端类型 = 多入口自动注册**（apps/ 目录扫描,不硬编 vite.config）
3. **模块路由 = 单点声明**（views/<m>/_meta.js 一处声明,不分散 3 处）
4. **前端/服务器关系 = DB 表**（server_profiles.frontend_type,代码不分支）

加新模块: 只动 4 处 — views/ + routes/ + rbac_permissions + server_modules（**禁止碰 profile-config.js**）
加新前端: 只动 3 处 — apps/ + server_profiles + nginx（**禁止碰 vite.config.js**）

### 主图 / 轮播图 / 跳转铁律（2026-08-12 波哥原话"还有主图、轮播图、附带的点击跳转等"）

详见 skill: `banner-carousel-cms`. 核心 4 条:
1. **统一后端** = 单 `routes/banners.js`（禁 portal/mall/hqh5/wxmp 4 套重复 API）
2. **统一表** = banners 表（禁 settings JSON 散落）
3. **统一组件** = `<UiBanner position="home_top">`（禁每个 view 写一份轮播）
4. **统一跳转** = link_type 枚举（internal/external/product/category/article/activity/minip/wechat/phone）

加新轮播位: 只动 2 处 — 后端 banners.position 加新值 + 前端 `<UiBanner position="新位置">`（**禁止重写组件**）

### 多租户隔离 + 客户自管铁律（2026-08-12 波哥原话"这些内容可以独立存在客户服务器中,在数据库里读取,而且在客户的后端可以由管理员自己修改"）

详见 skill: `multi-tenant-client-self-manage`. 核心 5 条:
1. **数据物理隔离** — 所有配置表 (banners/theme/translations/...) 必须有 server_profile_id
2. **配置存 DB** — 不存 .js / .vue / .json 文件
3. **客户后台 = 必须有配置入口** — BannerManage / ThemeConfig / ModulesManage / LanguageManage 后台 UI
4. **代码只有 1 套** — 不 fork 客户项目
5. **customer_admin vs super_admin** — 客户管理员只能动自己数据,super_admin 才能跨客户

部署新客户 = server_profiles 加 1 行 + 客户表数据勾选 = 完事,代码 0 改动
每个可配置项都必须有客户可改的后台 UI（不是改代码）

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

## 六、当前所有 profile 状态 (2026-07-21 更新,profile 6/7 上线)

```
profile 1 新加坡开发          (43.134.31.232,  wecom.gdqshop.cn)        — 54 modules, 全选, dev
profile 2 北京彩美特          (81.70.199.64,   claw.gdqshop.cn)         — 28 modules, prod
profile 3 3号仓库             (43.160.238.201, mywh3.com)               — 25 modules, staging
profile 4 上海智慧家园商城    (111.229.144.150, gdqshop.cn)             — 30 modules, staging
profile 5 SmartBiz / Labor    (100.64.122.98,  wecom.gdqshop.cn/labor/) — 13 modules, dev
profile 6 Bangkok-CMT         (43.152.237.77,  caimeite.com)            — TBD modules, prod
profile 6 横琴港澳科技孵化器  (43.128.47.254,  hatch.gdqshop.cn)        — 64 modules, prod (HK)
profile 7 澳門中醫藥學會 (macau) (101.33.32.177,  aippmcm.com)        — 27 modules, prod (fork)
profile 11 海丰大道庵 (寺庙)  (124.156.180.188, dda.gdqshop.cn)        — 85 modules, prod (temple)
```

### Profile 7 (HK 横琴港澳科技孵化器) — 2026-07-21 接入

| 项 | 值 |
|---|---|
| SSH | `ssh hk-incubator` (`/root/.ssh/hk-incubator/hk_incubator_v4.pem`) |
| WireGuard | `hk-incubator-wg` (10.99.0.2, 快路径) |
| nginx 路径 | `/var/www/hatch/` |
| web server | **Caddy**(不是 nginx)— /api/* 走 wg 反代 SGP 3200 |
| 后端 | **共享 SGP 后端**(通过 wg 隧道,HK 不跑自己的 Node) |
| site_name_zh / en | 横琴港澳科技孵化器 / HK-GBA Tech Incubator |
| language | `["zh-HK","zh-CN","en"]` (DB 配,代码 zh-HK 待补) |
| currency / industry | HKD / 科技孵化 |
| admin 账号 | id=9 江清波 phone=18676970008 server_profile_id=7 |
| 4 应用入口 | `/` minip · `/labor` labor · `/gdqadmin` admin · `/api/*` → SGP |

**同步逻辑** (`server-profiles.js` line 250): domain=hatch.gdqshop.cn 自动选 `/var/www/hatch/`,useSudo=true(env=production)。

任何改动必须先 `mysql SELECT * FROM server_modules WHERE server_profile_id = ?`
确认目标 profile 已勾选, 再动手.

---

## 七、开发日志规范 (2026-07-13 立, AI 必读)

**铁律**: **所有后端开发活动必须记录**到 `/root/src/docs/DEV_LOG.md` (与前端共用同一文件).

### 何时写日志 (后端特定)

| 触发动作 | 是否写日志 |
|---|---|
| 新增 / 修改 route (`/root/server/routes/*.js`) | ✅ 必须 |
| 修改 middleware (`/root/server/middleware/*.js`) | ✅ 必须 |
| 修改 `index.js` (mount 新路由) | ✅ 必须 |
| MySQL `rbac_permissions` / `server_modules` INSERT | ✅ 必须 |
| 后端 `AGENTS.md` 改动 | ✅ 必须 |
| 跑 `pm2 restart gdq-server` | ✅ 必须 (写明何时重启 + 原因) |
| **仅 read / grep** | ❌ 不写 |

### 后端日志格式 (在通用模板基础上加)

```markdown
## [YYYY-MM-DD HH:MM] <一句话标题>

**操作人**: agent / 波哥
**影响 profile**: 1 / 2 / 3 / 4 / 5
**commit**: <hash>

### 改动文件
- `routes/labor-xxx.js` — 新增 / 修改说明
- `middleware/rbac.js` — 加 PERMISSIONS 常量说明

### MySQL 改动
- `INSERT INTO rbac_permissions ...` (附 SQL)
- `INSERT INTO server_modules ...` (附 SQL)

### 重启验证
- `pm2 restart gdq-server`
- `curl http://localhost:3200/api/xxx` 返回 200

### 影响范围
- 哪些前端模块需要重新编译
- 是否需要 sync 到 profile 2/3/4/5
```

### 命名约定补充

- module_key 路径用**短横线** (例 `labor-ai-agent`), 不用下划线 (错例: `labor_ai_agent`)
- 后端 route 文件名 = module_key + `.js` (例 `labor-ai-agent.js`)
- 例外: 聚合多个 router 的文件可以多 export (例 `labor-worker.js` 4 个 router)

---

> **本节与 `/root/src/AGENTS.md` 第 "📝 开发日志规范" 段并列, 前后端共用 `/root/src/docs/DEV_LOG.md`**

---

## 八、#20 源码模块在 SGP (2026-08-09 波哥原话)

> "原则,所有的模块和源码必须在你本机,其它的服务器模块按目标服务器管理选择的,源代码同样,功能同样,但是各个服务器数据库独立"

### 核心三原则

| # | 原则 | 含义 |
|---|------|------|
| 1 | **模块源码在 SGP** | 所有模块代码、源码、build 产物全在 `/root/server/` |
| 2 | **消费端按 server_profiles 选模块** | 每个 profile 从 SGP 选 N 个 module_key (server_modules 勾选) |
| 3 | **DB 物理独立** | 每个客户服务器有自己的 DB (`gdq_macau` / `gdq_hk` / `gdq_bj` / `gdq_3` / `gdq_sh` / `gdq_bkk` / `gdq_dda`), 不共享表, 不交叉 query |

### 8 个 profile (SGP server_profiles)

| Profile | 服务器 | IP | 域名 | 角色 |
|---------|-------|----|----|------|
| 1 | 新加坡开发 SGP | 43.134.31.232 | wecom.gdqshop.cn | **source** (唯一源码 / build) |
| 2 | 北京彩美特 | 81.70.199.64 | claw.gdqshop.cn | fork (消费端) |
| 3 | 3号仓库 | 43.160.238.201 | mywh3.com | fork (消费端) |
| 4 | 上海智慧家园 | 111.229.144.150 | gdqshop.cn | fork (消费端) |
| 5 | SmartBiz Labor | 43.152.237.77 | wecom.gdqshop.cn/labor/ | shared_via_tunnel |
| 6 | 横琴港澳科技孵化器 | 43.128.47.254 | hatch.gdqshop.cn | fork (HK) |
| **7** | **macau 中医学会** | **101.33.32.177** | **aippmcm.com** | **fork** |
| 11 | 海丰大道庵 | 124.156.180.188 | dda.gdqshop.cn | fork (temple) |

### 加新模块流程 (4 处)

1. `views/<m>/_meta.js` — 前端视图声明
2. `routes/<m>.js` — 后端 API
3. `rbac_permissions` — INSERT 三件套 (read/write/delete)
4. `server_modules` — INSERT 勾选到目标 profile_id

**禁止**:
- ❌ 改 profile-config.js (2026-08-12 零硬编码铁律)
- ❌ 改 SGP modules/ 顶层硬编码
- ❌ 改 views/<other>/ 跨模块引用

### 加新客户流程 (3 步)

1. `server_profiles` 加 1 行 (DB)
2. `server_modules` 勾选 N 个 module_key (DB)
3. nginx / systemd / DB 创建 (一次性部署)

**代码改动**: **0 行**。

### 消费端铁律 (绝对禁止)

- ❌ 消费端不能跑 `vite build`
- ❌ 消费端不能 git push 源码改动
- ❌ 消费端不能改 `views/` / `components/` / `modules/` / `routes/`
- ✅ 消费端只拉 SGP build 产物 (dist-<id>/) + 独立维护 DB
- ✅ 消费端可改 `nginx conf.d/` / `systemd unit` / `my.cnf` / `.env`

### SGP build & sync 工具

| 脚本 | 用途 |
|------|------|
| `scripts/build-for-profile.sh <id>` | 通用 build (profile 1/2/3 走 profile-config.js) |
| **`scripts/build-macau.sh`** | **macau 专用 build (从 server_modules DB 拉 27 modules)** |
| **`scripts/sync-macau.sh`** | **SGP dist-7 → macau /opt/soc-server/dist-1/ 全自动** |

### 经济意义

SGP 集中 = **1 个波哥 + 1 个 agent 维护 8 客户** 的唯一可行路径。
违反 #20 = 8 倍 bug / 8 倍工作量 / 8 倍配置 / 不可持续。