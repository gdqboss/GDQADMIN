## [2026-07-14 13:00] minip: 批量去除 demo fallback 假数据 (24 view)

**操作人**: agent
**影响 profile**: 1 (SGP dev)
**commit**: 本次未 commit (待波哥确认后 push)

### 改动文件
- `/root/src/views/minip/*.vue` — 24 view 去掉 demo fallback 假数据 (catch 时改为 ElMessage.error + list=[])
- `/root/.hermes/scripts/clean_minip_demo_fallback_v2.py` — 新增智能清理脚本 (AST 解析 + esbuild syntax check, 防 v1 bug)
- `/root/.hermes/backups/minip-demo-cleanup-20260714/` — 备份 (34 .vue)

### minip 真实状态 (清理后)

| 状态 | 数量 | view |
|---|---|---|
| ✅ 真接 API + error toast | 28 | 全部 catch 失败时 ElMessage.error |
| ⚠️  仍 demo fallback | 2 | MeProfile/MiscFeedback (submit 成功降级, 非 list fallback) |
| ❌ 静态/无 API | 4 | MeCoupons/MeLogin/MinipLayout/MiscAbout |

### v1 bug 教训 (重要!)
v1 脚本粗暴正则替换 → FinanceReceipt.vue 残留 `]`, vite build 报错
v2 修复:
- AST 解析 catch 块 (平衡 {} 匹配)
- esbuild syntax check 改前验证, 失败立即跳过
- 备份到 .hermes/backups/ 不动 git

### 验证

| 项 | 结果 |
|---|---|
| esbuild syntax check | ✅ 23 文件全过 |
| `npm run build` | ✅ 2779 modules / 2m26s / no error |
| dist minip chunks | ✅ EnterpriseHome-B7rNO-Ub.js 等生成 |
| `curl /minip/` | ✅ 200 OK |
| 备份可回滚 | ✅ 34 .vue in .hermes/backups/minip-demo-cleanup-20260714/ |

### 影响范围
- 24 个 minip view 失败时不再静默显示假数据, 用户能看到 error toast
- 业务场景真实数据, 不再误导
- 数据真依赖后端 API, 测出后端问题更容易

---

## [2026-07-14 12:35] 波哥点破: caimeite-mall = 统一后端的 1 个前端表现层

**操作人**: agent
**影响 profile**: 无
**commit**: 无

### 认知修正 (重要!)

**caimeite-mall / minip / h5 / hqh5 / portal / adorder / labor / admin 都是同一个后端 (`/root/server/` + MySQL `gdq`) 的不同前端表现层** — 不是独立项目, 不需要新写后端。

| 前端 | 路径 | 后端 |
|---|---|---|
| 后台 | `/root/src/` | `/api/*` |
| H5 | `/root/src/views/h5/` | `/api/*` |
| **mall (caimeite-mall)** | `/root/caimeite-mall/` | **`/api/*` 复用现成** |
| minip | `/root/src/views/minip/` | `/api/*` (含 `/api/minip/*`) |
| hqh5 / portal / adorder / labor | 同上 | `/api/*` |

### 沉淀

写了 skill: `/root/.hermes/skills/unified-backend-multi-frontend/SKILL.md`
- 加新前端表现层 = 0 后端工作量
- 加新模块 = 后端 4 处 + 各前端 views/<新模块>/
- 常见误区: 不要把 mall 当独立项目, 不要为 minip 转小程序写新后端

### caimeite-mall 真实工作量

| 项 | 工作量 |
|---|---|
| 后端 API | **0** (复用) |
| nginx + profile | 小 |
| uni-app pages.json | 中 (6-10 页面) |
| uni-app pages/*.vue | 中 (复用 admin 模板) |
| build:h5 + build:mp-weixin | 小 |
| mp-weixin appid | **外部依赖** (波哥去微信公众平台申请) |
| git | 立即 |

---

## [2026-07-14 12:30] 波哥决策: caimeite-mall 移到 backlog, 当前重点在 minip

**操作人**: agent
**影响 profile**: 无
**commit**: 无

### 决策
- **caimeite-mall** 暂时不动 (移到 backlog, 当前不是重点)
- **minip** = 当前主线推进项目

### minip 摸底真相 (本轮新发现)

| 层 | 状态 |
|---|---|
| 后端 minip.js | ✅ 816 行 / 50+ 路由完整 |
| 前端 34 views | ⚠️ 几乎都走 `@/api/request` 通用 axios, 没直接打 `/api/minip/*` |
| demo mode | ❗ 全部 catch fallback 假数据 (前端实际未真依赖) |
| mp-weixin 真编译 | ❌ 当前是 H5 模式跑 `/minip/` |
| git 仓库 | ❌ |

### minip 转小程序真实工作量 (待 JXY spec / 波哥决策)

1. **改 34 views** 把通用 API 换成 `/api/minip/*` (架构对了才转) - 大改
2. **去掉 demo mode fallback** - 中改
3. **配 mp-weixin appid + 编译** (`build:mp-weixin`) - 小改
4. **小程序登录 wx.login + 后端 auth 改造** - 中改
5. **加 git** - 立即做 (低垂果实)

### 下一步建议 (等波哥拍板)
- 最低成本: `cd /root/src/views/minip && git init + .gitignore + 首次 commit` 保护现有代码
- 中等: 改 1 个 view (EnterpriseHome) 试点改用 `/api/minip/*` + 去 demo fallback
- 高 ROI: 等 JXY spec 推过来

---

## [2026-07-14 12:15] P2 server_modules 同步补全工具就绪 + JXY 全景报告推送

**操作人**: agent
**影响 profile**: 1, 2, 3, 4, 5 (5 个 profile 全分析)
**commit**: 无 (只是工具脚本)

### 改动文件
- `/root/.hermes/scripts/sync_profile_modules.py` — 新增, 一键 sync profile 模块勾选
- `/root/jxy-os/shared-knowledge/hermes_to_jxy.md` — 推 JXY 全景报告

### P2 真实数据 (从 DB `server_modules` 表查)

| profile | name | 已勾 | SGP 缺多少 |
|---|---|---|---|
| 1 | 新加坡开发 | 63 | - |
| 2 | 北京彩美特 | 28 | **35 个缺** |
| 3 | 3号仓库 | 25 | **38 个缺** |
| 4 | 上海智慧家园 | 30 | **33 个缺** |
| 5 | SmartBiz Labor | 20 | **43 个缺** |

**AGENTS.md 写的 54 modules 已过时, 实际 63**.

### sync_profile_modules.py 工具
- `python3 sync_profile_modules.py --src 1 --dst 2 --dry-run` (默认安全)
- `python3 sync_profile_modules.py --src 1 --dst 2 --apply` (实际 INSERT)
- `--only <module>` / `--skip <module>` 单选或排除

### bj 缺的 35 个模块 (按字母序)
ai-upload, article, attendance, banners, cashier, collage, coupon, diypage, edu, h5, home, hotel, kefu, labor-ai, labor-appeals, labor-hr, labor-worker, logistics, mall, mp, online-order, pickup, portal-clone, queue, referral, rental, restaurant, score_shop, server_profiles, takeaway, temple, theme, translations, wxapp, yuyue

### 测试
| 项 | 结果 |
|---|---|
| `python3 sync_profile_modules.py --src 1 --dst 2 --dry-run` | ✅ 列出 35 个待补, 0 个多余 |
| TG 推波哥 | ✅ status=sent |
| JXY 全景报告推 .md | ✅ (cursor 推进) |

### P3 摸底 (caimeite-mall)
- 1 个 index 页面, 无 git, 无 dist
- 未真正推进, 只是项目初始化

### 影响范围
- bj/3hk/SH profile 模块勾选 = **业务决策, 不是技术决策**, 等波哥拍板
- 工具就绪, 一行命令即可 sync

### 已知尾
- 没 commit (sync_profile_modules.py 是工具, 不入业务 repo, 已落到 .hermes/scripts/)
- AGENTS.md 数据过时 (54 → 63), 待波哥拍板要不要更新
## [2026-07-14 13:14] P1 minip loading 态 修复 + P2 JXY 全景文件输出

**操作人**: agent
**影响 profile**: 1

### P1 做了什么

- v3 脚本 bug：5 个 view 注入错误 → 修了（见下）
- `npm run build`：239 chunks / 11M / **0 error**
- `/minip/` 实测：**200 OK**
- commit `9153e085` push 远端 `feat/online-order`

#### v3 脚本 bug 详情

| Bug | 影响 | 修复 |
|---|---|---|
| `try { loading.value = true const r` 单行粘死 | 5 个 view: MeAddress/MeFavorites/MeReviews/MeProfile/MiscFeedback | 加换行 + 把 `const loading = ref(false)` 从 try 内提到外层 |
| `const loading = ref(false)` 被插到 try 内 | 2 个 view: MeProfile/MiscFeedback | 提回外层（紧跟最后一个 `ref()` 声明后） |

**自验**: 29 view 全部 `node --check` 通过

#### 加了 loading 态的 25 个 view

Finance 系 5 / Hr 系 5 / Marketing 系 5 / Me 系 7 / Oa 系 5 / EnterpriseHome / MiscFeedback  → 共 29 (FinanceExpense 之前 v2 已加)

### P2 输出

- 文件 `/root/.hermes/cron/output/hermes_to_jxy.md` (5KB)
- 内容：SGP 5 前端项目状态 + minip 完善进度 + minip-mp 暂停原因 + 6 项技术决策
- cron `hermes_to_jxy_poll` 每分钟自动推

### P3 选项（待波哥拍板）

- a) 申请小程序 appid 继续 minip-mp 编译（被 appid + 编译器卡住）
- b) P3 caimeite-mall = 另一个 uni-app 表现层（0 后端工作量，待启动）
- c) 修 bj SSH 后再推 module 同步

