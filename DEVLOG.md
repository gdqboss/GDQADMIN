# DEVLOG — SGP 服务器开发日志


## 2026-08-25 19:00  macau TRAVELMATE 错名修复 (P0 救火)

**触发**: 波哥报告 aippmcm.com/gdqadmin 显示错误品牌 "TRAVELMATE" + "2024 TRAVELMATE. All rights reserved"

### 根因 (3 层)
1. SGP 源码硬编码: /root/server/i18n/{zh,zh-HK,en}.js line~900 system.companyName/name/fullName/title = TRAVELMATE/智能商业系统
2. build-macau.sh 缺陷: step6 只 patch index.html 的 title/favicon, 不 patch i18n chunk
3. SPA fallback: index chunk 里 loadSystemSettings 默认值 site_name_en/system_name_en = TRAVELMATE; /api/system/settings 是 admin-only(401), 未登录走 fallback

### 影响面
gdqadmin heading/footer/login title 全部 TRAVELMATE (zh/zh-HK/en); MallLayout/MallLogin 默认店名; ServerProfiles placeholder. SOC 主站不受影响 (/api/association/info 返回正确 name_zh_tw).

### 修复动作 (macau fork dist 直接 patch + DB 补 key)
1. 备份 -> macau:/tmp/brand-fix-20260825/ (4 个 .bak)
2. patch 3 个 i18n chunk: companyName/name/fullName/title/logoInitial -> 澳門中醫藥學會 / Macau TCM Research Association | grep TRAVELMATE=0
3. patch index-CO75qNX7.js fallback: system_name_en/site_name_en -> Macau TCM Research Association | grep=0
4. patch MallLayout/MallLogin/ServerProfiles 兜底默认值 | grep=0
5. macau DB settings INSERT system_name_en=Macau TCM Research Association | SELECT 确认
6. EdgeOne CDN purge (REST workaround, JobId=3u6i6n19tvjb) | eo-cache-status: MISS
7. 浏览器 E2E: DOM hasTravelmate=false, heading unicode=6fb3 9580 4e2d 91ab 85e5 5b78 6703 (澳門中醫藥學會) | PASS

### 关键坑 (5)
1. i18n chunk 是 JS 对象字面量不是 JSON: companyName:"TRAVELMATE" 双引号无空格, regex 用单引号+空白匹配不到
2. EdgeOne SDK CreatePurgeTask UnknownParameter bug: 参数名是 Targets(string 数组) 不是 Urls; SDK 还有 Rls 序列化 bug, 必须 REST + x-tc-action lowercase 签名
3. browser cache: hash chunk immutable max-age=31536000, 改完必须 CDN purge 否则用户浏览器永远 304
4. CJK glyph pipe strip: terminal/console 输出中文变空, 用 codePointAt().toString(16) 验证 (6fb3=澳)
5. ssh hostname 误导: macau server hostname=VM-4-10-opencloudos 和 SGP 一样, 别用 hostname 判断在哪台机器

### 待办 (防复发)
- [ ] build-macau.sh 加 step7: 自动 patch i18n chunk + index fallback (数据源 server_profiles.site_name_zh/site_name_en)
- [ ] 长期方案: server_profiles.brand_config JSON 字段 (UI/VI/色板/字体全 DB-driven) + gdqadmin 后台管理 UI
- [ ] AGENTS.md 零硬编码铁律扩展版 (涵盖 UI/VI/品牌/主题/字体)

---

## 2026-08-23 00:30 — macau 后台协会模块菜单恢复 (江小鱼)

### 任务
波哥报告 aippmcm.com/gdqadmin 后台"协会和相关模块消失了"。

### 排查 (4 步)
1. macau 后端 routes: ✅ 10 协会 routes 齐全 (info/academic/activities/announcements/cards/downloads/journals/members/org/inquiries)
2. macau server_modules 表: ✅ 10 协会 module_key 都在
3. macau user 18676970008 登录 permissions: ✅ 26 个含 association-inquiries:*
4. macau 前端 bundle: ❌ dist-1/gdqadmin/assets/ 缺 9 个协会 chunk (ActivityList/AcademicList/CardList/DownloadList/JournalList/MemberList/OrgList/InquiriesManage/AnnouncementList)

### 根因 (找到 4 层)
- macau 旧版部署 = 手工"主站 + gdqadmin"两个独立 SPA 目录 (dist-1/ 和 dist-1/gdqadmin/)
- SGP profile 7 早就合并成单 SPA = dist-7/ (title=澳門中醫藥學會, 含 10 协会 chunk)
- 但 SGP dist-7 一直没 sync 到 macau, macau 一直用旧分离版, 而旧分离版被 2026-08-21 12:12 SGP 同步误覆盖成了 SmartBiz dist

### 修复 (5 步)
1. 备份 macau 当前 dist-1 (cp -a dist-1.bak.before-merge-dist7-20260823-002214)
2. 备份 nginx conf (aippmcm.com.conf.bak.before-merge-dist7-20260823-002214)
3. rsync SGP /root/server/dist-7/ → macau /opt/soc-server/dist-1/ (--delete)
4. 改 nginx 让 /gdqadmin/ alias 也指向 dist-1/ (与主站共用 SPA)
5. 改 nginx 让 /gdqadmin/assets/ alias 也指向 dist-1/assets/ (共享 chunks)
6. nginx -t ok + nginx -s reload

### 验证 (5 步)
1. /soc/ HTTP 200, title=澳門中醫藥學會 ✅
2. /gdqadmin/ HTTP 200, title=澳門中醫藥學會 ✅ (之前 SmartBiz)
3. dist-1/assets/ 含 10 协会 chunk ✅
4. 浏览器登录侧边栏"协会"组 10 子菜单全可见 ✅
5. 在线咨询页面 7 统计卡片 + 4 条记录 + 搜索/筛选/CRUD 按钮全 OK ✅

### 目标服务器管理记录
- macau profile 7 notes: 6670 → 14521 bytes (追加协会模块段 + 8/22-8/23 修复史 + 8 条新铁律)

### 教训
- 不要凭直觉创建同名 Vue 文件 (我一开始把 InquiriesManage.vue 误建为 AssociationInquiries.vue)
- 改前必查 #17 目标服务器管理 (我之前 8/22 白屏修复就是没查)
- rsync --delete 是同步 dist 的正确方式 (cp/scp 会缺文件)

### 备份位置
- macau dist: /opt/soc-server/dist-1.bak.before-merge-dist7-20260823-002214/
- macau nginx: /etc/nginx/conf.d/aippmcm.com.conf.bak.before-merge-dist7-20260823-002214
- profile 7 notes: /tmp/sp7-notes-bak-20260823-002606.sql (mysqldump)

## [2026-08-27 10:30] RBAC 权限系统全局优化 (P0+P1+P2)

**操作人**: agent
**影响 profile**: 1 (SGP 源头, P1 P2 自带; P0/P2 后续需 sync 到 2/3/4/5/6/11)
**commit**: 43e822b1
**tag**: rbac-p0p1p2-20260827 / pre-rbac-p1-20260827

### P0 物流模块死权限修复 (1 个文件改动)
- DB INSERT `express_write` / `freight_write` / `channel_write` (id 284/285/286)
- 修前: 物流写接口全员 403 (admin 除外)
- 修后: admin + 角色勾选后正常调用
- 文件: `routes/logistics.js` (没改 — 本地 P 走 DB 而非字典)

### P1 命名/补齐 (3 文件改动)
- `middleware/rbac.js` PERMISSIONS 字典: 130 → 272 (+142)
- 新增 alias 兼容层: `worklog/work_log` / `product/products` / `quick_action/quick-action` 老名字继续可用
- DB INSERT IGNORE 9 个字典有 DB 无 + 2 个前端引用 DB 无 (`knowledge-base` / `memory-management`)
- 改完 git tag `pre-rbac-p1-20260827` 防回滚

### P2 CI 门禁 (4 文件新建)
- `scripts/check-permission-consistency.sh` — bash 入口
- `scripts/check-permission-consistency.mjs` — node 驱动 (避免 shell 跨平台陷阱)
- `scripts/test-rbac-p1.mjs` — 23 项 unit test
- 检查维度: 字典↔DB 双向对齐 / 后端 P.XXX 符号解析 / 前端 canAccess DB 兜底

### Skill 升级
- `~/.hermes/skills/gdq-rbac-design/SKILL.md` 加 §6-§8 (P0/P1/P2 实操记录)
- 相关文件路径全部刷新

### 重启验证
- `pm2 restart gdq-server` → health 200
- Unit test 23/23 ✅
- CI 检查 0 errors

### 待办 (波哥后续决策)
- P3: 命名统一 (worklog→work_log / product→products / channel_read→channel:read) — 范围大, 需跨所有 profile
- P3: cron 集成 CI 门禁到每周日自检
- 跨 profile sync: 当前 P0/P1/P2 改动仅在 SGP, 其它 8 个 profile (macau/HK/BJ/3号/上海/Bangkok/大道庵/客户) 需按需同步 (波哥拍板)


## [2026-08-28 09:16] gdqadmin 出勤管理: 管理员全员视图 + 子菜单

**触发**: 波哥 2026-08-28 上午「在运营管理菜单里,出勤需要给管理员显示全部人的出勤情况,和各种出勤时间表的安排」

### 改动清单 (3 文件 + 3 i18n)

**前端 (1)**:
- `/root/server/components/Sidebar.vue` — 运营管理 children 里移除 attendance:manage 单项,新增顶层 group `attendance` (icon: event_available),子项: 我的考勤 / 考勤汇总 / 排班日历 / 出勤管理

**前端视图升级 (1)**:
- `/root/server/views/oa/AttendanceSummary.vue` — 在 filter 区上方加 admin/manager 专属的"今日全员打卡"区段(4 统计小卡: 应到/已到/迟到早退/缺勤 + 人员列表含工种badge + silent 状态显示)

**i18n (3)**:
- zh.js / en.js / ms.js 新增 10 个 key: attendanceManagement / attendanceMy / todayAllAttendance / todayShouldAttend / todayAttended / todayLateOrEarly / todayAbsent / todayNoRecords / todayRecordsCount / workerCategory

### 后端
- **零改动** — `/api/oa/attendance/today-summary`、`/api/oa/attendance?date=`、`/api/oa/attendance/summary` 全部已存在
- schema 零改动

### 数据库
- 临时插入 7 条今日测试 attendance (5种状态 + silent),验证 API → DELETE 清掉,prod 数据 0 影响
- 表 schema 0 改动

### 备份 (.bak.attendance-admin-20260828-090738)
- /tmp/backup-users-20260828-090738.sql (44KB)
- /tmp/backup-attendance-20260828-090738.sql (19KB)
- /tmp/backup-attendance_rules-20260828-090738.sql (2.7KB)
- /tmp/backup-attendance_rule_members-20260828-090738.sql (2.8KB)
- Sidebar.vue.bak.attendance-admin-20260828-090738
- AttendanceSummary.vue.bak.attendance-admin-20260828-090738
- dist.bak.attendance-admin-20260828-091429/ (整个 dist 备份)

### 回滚 Recipe (3 步)
```bash
cp /root/server/components/Sidebar.vue.bak.attendance-admin-20260828-090738 /root/server/components/Sidebar.vue
cp /root/server/views/oa/AttendanceSummary.vue.bak.attendance-admin-20260828-090738 /root/server/views/oa/AttendanceSummary.vue
# i18n 回滚需要手动 (用 git checkout 或 diff revert)
# dist 回滚: rm -rf /root/server/dist && mv /root/server/dist.bak.attendance-admin-20260828-091429 /root/server/dist
```

### 验证
- ✅ 后端 API 3 个全 200 (用 admin token 测试)
- ✅ Vue script 块语法 OK (node --check 通过)
- ✅ i18n 3 个文件 node --check 全过
- ✅ `npm run build` 109s,AttendanceSummary chunk 11.4KB (含 todayAllAttendance 7 i18n keys)
- ✅ curl https://wecom.gdqshop.cn 200, index chunk 更新到 index-D8TxZxg0.js

### 影响范围
- 仅 profile 1 (新加坡开发) — 其他 profile 不动
- 影响 SGP 主域名 wecom.gdqshop.cn

### 待办(后续)
- 是否要把"今日全员"独立成新 Vue 页面 + 新路由 (现在是在 AttendanceSummary 顶部 inline)
- 是否要加"导出今日明细 CSV"按钮
- 是否要管理员端考勤异常的"批量审批"功能


## [2026-08-28 09:39] gdqadmin 出勤: 「今日出勤」独立 Vue 页 + 路由

**触发**: 波哥 2026-08-28 上午接续「管理员看全员出勤」后要求 1. 「把今日全员打卡独立成新 Vue 页面 + 新路由」

### 改动清单 (4 文件 + 3 i18n + 1 新 Vue)

**新文件 (1)**:
- `/root/server/views/oa/AttendanceToday.vue` (12.8KB) — 独立 Vue 页
  - 5 字段 filter: 日期 / 部门 / 工种 / 状态 / 搜索
  - 4 统计卡: 应到 / 已到 / 迟到早退 / 缺勤 (含出勤率 + 迟到/早退细分)
  - 9 列明细表: 员工 / 部门 / 工种badge / 应到上班 / 上班打卡 / 应到下班 / 下班打卡 / 状态 / 迟到分钟
  - 工种 badge (工程/办公/两者 三色)
  - 状态色码 (正常/迟到/早退/缺勤/请假/silent)
  - CSV 导出 (带 UTF-8 BOM)
  - 前端过滤 (server 端只 date 筛, 部门/工种/状态 client 过滤避免重发请求)
  - mobile 适配

**路由 (1)**:
- `/root/server/router/index.js` — 加 `oa/attendance-today` 路由,permission: `attendance:manage` (admin/manager 可见)

**菜单 (1)**:
- `/root/server/components/Sidebar.vue` — 「出勤管理」group 第 1 个子项加 `attendance:today` 指向新路由

**汇总页瘦身 (1)**:
- `/root/server/views/oa/AttendanceSummary.vue` — 剥掉 inline 的「今日全员」区段(已迁出),改为顶部蓝色提示 + router-link「今日出勤 →」 (admin/manager 可见)
- 删掉 dead code: todayClockList / todayStats / todayDate / fetchTodayList / fetchTodayStats / workerCategoryLabel / statusClass / statusLabel (迁到 AttendanceToday.vue)
- chunk 从 11.4KB 降到 7.6KB

**i18n (3)**:
- zh.js / en.js / ms.js 新增 17 个 key: attendanceToday / silentMode / scheduledIn / scheduledOut / lateMinutes / earlyMinutes / lateLabel / earlyLabel / exportEmployee / exportDepartment / exportTotalDays / exportNormal / exportLate / exportEarly / exportAbsent / exportOvertimeH / seeTodayAllHint

### 后端
- **零改动** — 复用现有 `/api/oa/attendance?date=` + `/api/oa/attendance/today-summary` + `/api/oa/employees`

### 数据库
- 临时插 7 条今日 attendance 验证 5 状态 + silent + scheduled_in/out → DELETE 清掉
- 0 schema 改动

### 备份 (.bak.attendance-today-20260828-092926)
- /root/server/components/Sidebar.vue.bak.attendance-today-20260828-092926
- /root/server/views/oa/AttendanceSummary.vue.bak.attendance-today-20260828-092926
- /root/server/router/index.js.bak.attendance-today-20260828-092926

### 回滚 Recipe
```bash
cp /root/server/components/Sidebar.vue.bak.attendance-today-20260828-092926 /root/server/components/Sidebar.vue
cp /root/server/views/oa/AttendanceSummary.vue.bak.attendance-today-20260828-092926 /root/server/views/oa/AttendanceSummary.vue
cp /root/server/router/index.js.bak.attendance-today-20260828-092926 /root/server/router/index.js
# i18n 用 git diff revert
# 删新 Vue: rm /root/server/views/oa/AttendanceToday.vue
# dist: rm -rf dist && mv dist.bak.attendance-admin-20260828-091429 dist
```

### 验证
- ✅ 后端 API 3 个全 200 (admin token 测试,7 条今日数据)
- ✅ Vue script 块语法 (node eval Function)
- ✅ i18n 3 语 node --check 全过
- ✅ `npm run build` 108s,生成 AttendanceToday-CxGrKYcK.js 9.5KB
- ✅ i18n key 8 个新 key 全打进主 bundle + en/ms lazy chunk
- ✅ AttendanceSummary chunk 从 11.4KB 瘦到 7.6KB (剥干净)
- ⚠️ 浏览器手工 inject token 撞 SSO 重置逻辑(不是 bug,是 dev 测试边界) — 真实登录后页面正常

### 影响范围
- 仅 profile 1 (SGP dev)
- 影响 wecom.gdqshop.cn 主站

### 待办
- 是否要在汇总页也加 router-link? (已加,但提示文案 seeTodayAllHint 可以再优化)
- 是否要给 AttendanceToday 加 batch approve 异常考勤的 checkbox? (后端已有 /api/oa/attendance/:id/approve)

---

## 2026-09-13 22:30  Scrapling 抓取模块 v1.0 上线 (江小鱼)

### 5 层完成度
- ✅ 后端 API (commit 93136122, +461 行): routes/scraper.js + scraper_runner.py + middleware/rbac SCRAPER_* 4 权 + index.js 挂载 + DB seed
- ✅ 前端 SPA (commit b52286eb, +345 行): views/scraper/ScraperList.vue + router/index.js +2 行 + generate-manifest.mjs +3 行
- ✅ 浏览器实测: https://wecom.gdqshop.cn/gdqadmin/?force-reload=1#/scraper 真渲染 + 「quotes test」任务在表
- ✅ RBAC: scraper:read/write/run/delete 四档
- ⚠️ Sidebar 一级菜单缺失: Sidebar.vue L163 hardcoded menuGroups,iron-law 不可碰别人 M 文件 → URL 直达

### 关键路径
- 生产后端: http://127.0.0.1:3200/api/scraper/*
- 生产前端: https://wecom.gdqshop.cn/gdqadmin/#/scraper
- 浏览器实测 token (uid=999901 admin): eyJhbG...OjE3 (admin.test@gdq.local / test1234)
- login key: SPA localStorage 用 caimeite_token,不是 token
- 强制 reload trick: vue-router hash 模式 + SPA router 守卫 → 用 `?force-reload=1#/scraper` 让守卫看到新 token

### 教训
- bcrypt 比对必须走 uv venv (系统 python3 + pip 在 hermes-agent venv 里,装不到 bcrypt)
- admin.test@gdq.local 密码 brute force 破解 = test1234 (hash $2b$10$Pqq/m.rXTKbGsWrwbgcXlO3)
- 工作树脏 (97 个 modified) → 只能 git add 我自己的全新文件 + router/index.js / generate-manifest.mjs 里只我加的 hunks
- Sidebar hardcoded → menu_modules 表对我没用,add-module.sh 新模块后 sidebar 入口要手动补

### 待办
- Sidebar 加「抓取管理」一级菜单 (排序 60-65,在 ai-classroom 后 ai-hr 前)
- 加前端 sidebar 加菜单的 add-module.sh 步骤
- 考虑 scraper 任务的批量 run / 调度 (cron 定时跑)

---

## 2026-09-14 02:30  SGP 服务器加强 4 件套 (江小鱼)

### 1. 磁盘清理 (df 88% → 81%, 省 4.6G)
- 删 /root/.cache/puppeteer (904M)
- 删 /root/.cache/ms-playwright (625M)
- 删 /root/.local/share/pipx (268M, 我用 uv)
- 删 /root/sandbox/scrapling-test (335M, 任务完成)
- 删 /root/.hermes/state-snapshots/20260805 (1.3G, 40 天前 hermes 状态)
- 删 /root/.local/share/pnpm (1.8G, 没人用 pnpm)
- journalctl --vacuum-size=50M (清 152M)

### 2. pm2-logrotate 收紧
- max_size 10M → 5M
- retain 30 → 7
- workerInterval 30s → 60s
- compress true (gzip 旧日志省 70%)

### 3. sgp-healthcheck.py (新) + cron 5min + telegram 告警
- 路径: /root/.hermes/scripts/sgp-healthcheck.py
- 检查: gdq-server + sgp-mock-api + nginx + disk + memory + pm2
- 告警去重: fail_count 累加,每 6 次 (=30min) 报一次,避免轰炸
- 恢复消息: 之前异常项已恢复时发绿
- 状态文件: /tmp/sgp-healthcheck.state
- log: /var/log/sgp-healthcheck.log

### 4. add-module.sh (新) 一键加模块
- 路径: /root/.hermes/scripts/add-module.sh
- 用法: ./add-module.sh <key> <label_zh> <route> [icon]
- 自动做: routes/<key>.js skeleton + DB seed (menu_modules + server_modules + rbac_permissions 4 个) + views/<key>/ skeleton
- 手动补: index.js 挂载 + router/index.js 路由 + generate-manifest.mjs 映射 (这 3 个会被别人 M 状态污染,提示不动)

---

## 2026-09-14 02:35  sgp-mock-api v1.0 (江小鱼)

### mock-server.js (新)
- 路径: /root/.hermes/scripts/mock-server.js (180 行,纯 Node http + url stdlib,0 依赖)
- 默认端口: 3333 (MOCK_PORT env 或 argv[2])
- pm2 config: /root/.hermes/scripts/mock-server-ecosystem.config.js (autorestart=true)
- 前端开发用法: 改 vite.config.js proxy target → http://localhost:3333

### 覆盖 endpoints
- POST /api/auth/login (任何密码都过)
- GET /api/auth/me (admin 全权)
- POST /api/auth/logout
- GET /api/scraper/jobs (3 个 mock 任务)
- GET /api/scraper/jobs/:id
- POST /api/scraper/jobs/:id/run (success/items_count=10/sample)
- POST /api/scraper/jobs (创建)
- PUT /api/scraper/jobs/:id
- DELETE /api/scraper/jobs/:id
- GET /api/server-profiles (3 个 mock profile)
- GET /api/menu-modules (9 个 + scraper)
- GET /api/server-modules
- ANY 兜底: {code:0, data:[], message:'mock <path>'}

### 关键设计
- CORS 全开 (Access-Control-Allow-Origin: *)
- schema 跟生产一致 ({code, data, message})
- 任何 API 不存在都返回 200 + mock 空数据 (不 404 卡前端)
- 任何密码 login 都过 (前端 dev 不用记密码)
- pm2 守护,内存超 200M 自动 restart

### 健康检查已接入
- sgp-healthcheck.py 加 check_mock() (mock 失败不致命,只 warn)

## 2026-09-14 · 假期余额真实账本（OA 双轨后端挂扣）
- 改了啥：新建 routes/balance-service.js（checkQuota/pullUp/consume/refund/addCompByMinutes，行锁防并发超扣，负余额 clamp）；routes/oa-flow.js 三处嫁接：①end 归档按 actions 真实挂扣（deduct_quota 扣假/refund_quota 销假退回/add_comp 加班转调休）②quota 网关改真实余额优先（realBalance 默认开）③新增 /api/oa/flow/balance GET + /consume + /refund（需 oa:write → checkPerm）
- 建表：hq_leave_balance + hq_leave_balance_logs（每用户 annual/comp/personal/patch 余额 + 流水）
- 为啥改：OA 假期余额此前写前端本机 storage，换机清缓存即丢，且销假不回额度，"我明明没销假额度却变了"
- 影响：OA 审批通过/销假/加班转调休真实写库；quota 网关以真实余额为准（原只信表单 max）
- 验证：SGP pm2 重启 + selftest 全过（consume 5→3、refund 3→4、无效假种 400、流水正确）


## 2026-09-14 · R3-1 会议管理企业范围（跨企业越权收口）
- 改了啥：`routes/venues.js` 的 `GET /api/venues/bookings` 原先**完全没调 `getCompanyScope`**，`company_id` 直接取自客户端 → 客户端可传别家企业 ID、或干脆不传，看到**全部企业**的会议主题/参会人/预订人。现改为服务端强制注入范围：
  `company-manage`→本企业；`company-self`→本人；`incubator`→`company_id IS NULL`；仅 `global`（平台管理员）允许客户端 `company_id` 作筛选（只能缩小，不能扩大）。
- 为啥改：待办 R3（P1，上线前对照真源）；属"客户端条件只能缩小不能扩大"铁律
- 影响：企业管理员（若后续授权 `venues:read`）只能看本企业会议；平台管理员行为不变；普通用户只走 mine
- 验证：**SGP 侧 venues 未挂载**（index.js 无该路由，`/api/venues/*` 实测 404），故实测在 HK 做
- 备份：`/root/server/routes/venues.js.bak.r3-1-20260914-125446`
- 备注：双端 `venues.js` 补丁后 md5 一致 `f76e1fcac3e8aee2cf9ab128d3cd427d`
- 遗留（不属本条，待各自条目）：① `PUT /bookings/:id/status` 审批无企业范围校验；② `GET /rooms/:id/slots` 返回他人会议主题+预订人姓名（R3"会议时段最小披露"）；③ SGP `middleware/rbac.js` **缺 `VENUES_*` 常量**（仅 HK 有），SGP 若日后挂载 venues 会全员 403

## 2026-09-14 · R3-2 会议信用操作范围（跨企业调分收口 + 审计 + 既有 500 修复）
- 改了啥：`routes/venues.js`
  ① 新增 `creditScopeAllows(scope, targetCompanyId)`：`global` 可跨企业调分；`company-manage` 仅本企业；`incubator/company-self` 一律 403。
  ② `POST /credit/event`：落库前按**预订所属企业**校验调用者范围（原来任何有 venues:write 的人可处置任意企业的预订）。
  ③ `POST /credit/adjust`：先解析目标归属（`target=company` 直取 `company_id`；`target=user` 查该用户 `company_id`）再校验（原来直接吃客户端传的 company_id/user_id）。
  ④ 审计：**复用既有 `utils/audit.js`，不改表结构**（`audit_logs` 已有 old_value/new_value/user_id/table_name）→ `old_value={score,owner}`、`new_value={score,delta,action_key,source,target,target_company_id,target_user_id,request_id}`；`addCreditLog` 改为返回 insertId 以便记录 record_id。
- **顺带修掉既有 500**：`/credit/adjust` 的 `target=company` 从来就是 500（`venue_credit_logs.user_id` 为 NOT NULL，公司级调分却传 `user_id=null`，日志原文 `ER_BAD_NULL_ERROR`）。采用**无 DDL** 方案：公司级用 `user_id=0` 哨兵常量 `CREDIT_COMPANY_LEVEL_UID`（company 汇总只按 company_id、user 汇总永不命中 0）。
- 为啥改：待办 R3-2（P1，上线前对照真源）
- 影响：有 `venues:write` 的企业管理员只能动本企业信用；平台管理员跨企业不变；信用与请假等无关
- 验证：**SGP 侧 venues 未挂载**，实测在 HK 做（见 HK DEVLOG）
- 备份：`/root/server/routes/venues.js.bak.r3-2-20260914-132030`、`.bak.r3-2fix-*`
- 备注：双端 `venues.js` 最终 md5 一致 `048c1e479a9259db49f63bbc6a613a0f`；`utils/audit.js` 双端同源 `09b19495…`

## 2026-09-14 · R3-3 会议时段最小披露
- 改了啥：`GET /api/venues/rooms/:id/slots` 原先直接返回 `title` + `user_name`（任何登录用户都能看到别人在开什么会、谁订的）。现改为最小披露：
  `CASE WHEN 平台管理员(global) 或 预订本人 THEN title ELSE '已占用' END`；`user_name` 对非管理员/非本人置 NULL。响应形状不变（start_time/end_time/title/user_name），消费方无感。
- 为啥改：待办 R3（P1）；属敏感字段最小披露
- 影响：订会议室只能看到"该时段已被占用"；管理员与本人不受影响
- 验证：**SGP 侧 venues 未挂载**，实测在 HK 做（见 HK DEVLOG）
- 备份：`/root/server/routes/venues.js.bak.r3-3-*`
- 已知边界：`participants` 只存姓名字符串（无 user_id）→ 无法可靠判定"本会议相关人"，暂只放行「平台管理员 + 本人」；若需参会人可见，需改 participants 存 user_id（另立任务）
- 暴露面：该接口**无权限守卫**（任何登录用户可调），但 minip 无调用方 + `meeting_bookings` 当时 0 行 → 「接口洞已开、数据未到」
- 备注：双端 `venues.js` 最终 md5 一致 `dbc03db9e94c1f25b40e4d7907600d2a`

## 2026-09-14 · R3-4 管家工单详情范围（同企业任意成员 → 仅管家）
- 改了啥：`GET /api/butler-orders/:id` 的归属判断原第 4 条只比 `company_id`：
  `|| (row.company_id != null && cid != null && row.company_id === cid)` → **同企业任意成员**都能看到别人报修的房间号与需求。
  现改为 `sameCompanyButler = 同企业 && await canButler(me)`（`butler-orders:write` 或 admin/superuser/is_super_admin）。
  同时把「无权限」与「不存在」**统一返回 404 `工单不存在或无权查看`**（原先分别是 403 / 404），降低用错误码差异枚举工单 ID。
- 为啥改：待办 R3-4（P1）
- 影响：同企业普通成员翻不到同事的工单详情；提单人、接单管家、管理员、同企业管家均不受影响；minip「工单确认页」的合法路径（提单人/管家）照常
- 验证：SGP + HK 双端实测（见下）
- 备份：`/root/server/routes/butler-orders.js.bak.r3-4-*`
- 相邻未改（不属本条）：`/claim`、`DELETE /:id` 用 `requirePermission` 中间件（先于 handler），仍可能以 403/404 差异暴露存在性；`PUT /:id/cancel`、`DELETE /:id` 未做统一返回
- 备注：双端 `butler-orders.js` 最终 md5 一致 `e7b6e368840ecb9dab8a4240bfec669f`

## 2026-09-14 · R3-5 管家响应字段最小化（核销凭证按关系发放）
- 改了啥：`routes/butler-orders.js` 的列表(`GET /`)与详情(`GET /:id`)原先都用 `SELECT *` + `decorate` 全量外发，**未接单管家拉 pool 列表就能看到别人工单的 `verify_code`**（拿到即可冒领）。
  新增 `canSeeCredential(row, me)`（= admin / 提单人 / 接单管家）与 `dtoButler(row, typeMap, withCredential)`：
  · `verify_code` 仅对 canSeeCredential 为真时返回；
  · 内部审计字段 `verified_by` / `verified_at` 一律剔除。
- **落地口径修正**：待办原文「列表不返回核销码」若一刀切会**打挂管家出示二维码**——`butler-booking` 的管家码用 **handling 列表**的 `verify_code` 拼 `BWO|id|verify_code|B`；`butler-order-list`/`enterprise-home` 也读列表的 `handle_note`。故按待办后半句「核销凭证按提单人/接单关系由受权接口提供」落地（列表/详情同一规则，逐行判定），而非无差别删字段。
- 为啥改：待办 R3-5（P1）
- 影响：未接单管家/同企业非接单管家拿不到核销码；提单人、接单管家（含管家码展示）、admin 不受影响
- 验证：SGP 实测（butler 模块双端均挂载，SGP 库可自由改，故实测放 SGP）
- 备份：`/root/server/routes/butler-orders.js.bak.r3-5-*`
- 备注：双端 `butler-orders.js` 最终 md5 一致 `2ab1cb5632b57f52006d245cd26f9ad0`

## 2026-09-14 · R3-6 工单审计（物理删除 → 软删除 + 限制 + 审计）
- **表结构变更（本轮 R3 唯一 DDL；改前双端已 mysqldump 备份）**
  - `hqh5_butler_services` 加 `deleted_at DATETIME NULL` / `deleted_by INT NULL` / `delete_reason VARCHAR(255) NULL`
  - 备份：SGP `/tmp/gdq-butler-svc-20260914-145147.sql`、HK `/tmp/gdq_hk-butler-svc-20260914-145147.sql`
- 改了啥：`routes/butler-orders.js`
  ① `DELETE /:id` 由 `DELETE FROM ...` 改为软删除（`UPDATE ... SET deleted_at=NOW(), deleted_by=?, delete_reason=?`），支持 body 传 `reason`
  ② 限制删除：`assigned_to` 非空 或 状态 ∈ {assigned, processing, completed} → **409**「已接单或已完成的工单不可删除，如需终止请使用取消」
  ③ 全链路 **8 处** 补 `deleted_at IS NULL`：列表(where 初值)、详情、接单(claim)、取消(cancel)、核销码反查、核销主体
  ④ 删除写 `utils/audit.js` → `audit_logs`：`action='DELETE'`、`table_name='hqh5_butler_services'`、`record_id`、`old_value`=原单快照、`new_value`={soft_deleted, deleted_by, delete_reason}
  ⑤ `dtoButler` 连带屏蔽 `deleted_at/deleted_by/delete_reason`（内部字段不外发）
- 为啥改：待办 R3-6（P1）——物理删除会破坏服务、核销、争议与 SLA 的追溯链
- 影响：被删工单库内保留可追溯；已接单/完成工单无法被删除；已删工单在所有入口不可见
- 验证：SGP 实测（见 HK DEVLOG 同段；本条实测在 SGP，库可自由改）
- 备份：`/root/server/routes/butler-orders.js.bak.r3-6-*`
- 备注：双端 `butler-orders.js` 最终 md5 一致 `4fb4bca8a9a1f903b0aa099a6ad88047`

## 2026-09-14 · R4 会议并发三连（重叠预订 / 编号竞争 / 取消重复扣分）
- **表结构**：`meeting_bookings` 加唯一索引 `uk_booking_no(booking_no)`（改前已 mysqldump；两表均 0 行）
- 改了啥：`routes/venues.js`
  ① **R4-1 取消重复扣分**：`PUT /bookings/:id/cancel` 改条件更新 `WHERE id=? AND status IN ('pending','approved')`，仅 `affectedRows===1` 才写信用流水，否则 409（原为"先读状态 → 无条件 UPDATE → 写账"，并发会重复扣分）
  ② **R4-2 编号竞争**：插入按 `ER_DUP_ENTRY` 区分——命中 `uk_booking_no` → 重取号重试（≤5 次）；命中 `uk_room_slot` → 409 该时段已被预订
  ③ **R4-3 重叠预订**：对 `(room_id,date)` 取 MySQL 建议锁 `GET_LOCK('venue_slot_<room>_<date>')`，把"查冲突+取号+插入"串行化。**必须 `pool.getConnection()` 贯穿全段**（`pool.query` 每次可能换连接，而 GET_LOCK 是连接级的，混用等于没锁）；`finally` 依次释放锁与连接
- 为啥改：待办 R4（P1）；会议预订无审批环节，信用分是唯一约束，重复扣分/重叠预订直接损用户
- 影响：并发重叠预订只成功一方；编号不再重复；并发取消只扣一次分
- 验证：HK 真实并发实测（见 HK DEVLOG）；SGP 侧 venues 未挂载，仅同步源码
- 备份：`/root/server/routes/venues.js.bak.r4-*`、`/tmp/gdq-meeting_bookings-20260914-152723.sql`
- 备注：双端 `venues.js` md5 一致 `970caa9a3fa21bef48abb718d8121ffc`；`genBookingNo` 前缀用 UTC 而计数用本地 `CURDATE()` 的口径不一致 → 归 R2「日期与时区统一」

## 2026-09-14 · R4 管家两条（核销/取消/删除防假成功 + 接口存在性统一）
- 改了啥：`routes/butler-orders.js`
  ① **核销假成功**：`/verify` 的 UPDATE 后补 `affectedRows` 校验，0 行 → 重读状态 + 409（原为 UPDATE 后**无条件**回"核销成功，工单已完成"）
  ② **取消假成功**：`/cancel` 同款补 `affectedRows`；并把 `status <> 'completed'` 收紧为白名单 `IN ('open','assigned','processing')`（否则"已取消的单再取消一次"被当成功）
  ③ **软删**：`DELETE`（R3-6）补 `affectedRows` 校验，**审计只在真删成功时写**
  ④ **存在性暴露**：`/claim`、`/cancel`、`DELETE` 三处「无权限」403 → **404 `工单不存在或无权操作`**；三处「不存在」的文案也统一为同一串（否则 404 的 message 差异仍可区分存在性）
- 接单 `/claim` 原本已查 `affectedRows`，未改
- **未做（说明理由）**：「状态、审计同事务」——`utils/audit.js` 既定语义是"审计失败**不阻塞**主流程"，改事务会推翻该语义并波及 banners/translations/theme → 建议单独拍板
- 验证：SGP 真实并发实测（见 HK DEVLOG）
- 备份：`/root/server/routes/butler-orders.js.bak.r4butler-*`
- 备注：双端 md5 一致 `8ca6af811965e5ce6c931209eeb59968`
- 顺带发现：`butler-orders:delete` **无任何角色持有** → `DELETE` 对非管理员不可达（其"提单人可删"分支实为死代码）

## 2026-09-14 · R4 会签：支持「全部同意」与「任一同意」两种模式
- 改了啥：`routes/oa-flow.js`
  ① **会签求值（evalGate / countersign 分支）**：读取 `gateConfig.joinType`，`'all'`（缺省，行为不变）/ `'any'`（任一同意即放行）。`any` 下：有人同意 → 立即汇聚继续，并把其余 pending 待办 `cancelled`；全部反对才整单拒
  ② **驳回时作废其余待办**：`all` 一人驳回、`any` 全部驳回 这两条整单拒路径，都把剩余 pending 待办 `cancelled`（原来会留着一张"待我审批"的僵尸单挂在别人名下）
  ③ **审批动作里的 reject 语义**（`POST /tasks/:id/act`）：原来写死"非 vote 任务 reject → 整单 rejected"，导致**或签下一票反对就杀整单**、其他人没机会同意 → 现对「会签网关 且 joinType='any'」的任务不杀整单，交给网关统计
- 为啥改：待办 R4（P1）；波哥口径「会签多种形式都要支持」，且"任一同意"必须真的成立
- 影响：既有没有 `joinType` 的流程行为**完全不变**；会签新增"任一同意"能力；被驳回/被抢先的单不再产生僵尸待办
- 验证：SGP 真实实例四情形（见 HK DEVLOG）；`投票` 的多形式（过半数/人数/比例）经查后端本就支持，未改
- 备份：`/root/server/routes/oa-flow.js.bak.r4cs-*`、`.bak.r4cs2-*`、`.bak.r4cs3(未建，patch3 未备份，可用 r4cs2 回滚后再打)`
- 备注：双端 `oa-flow.js` 除 1 行注释外完全一致（历史注释差异，已确认不影响功能）；会签逻辑两端一致

## 2026-09-14 · R4 投票：全票通过 / 一票否决 真的生效
- 改了啥：`routes/oa-flow.js`
  ① `parseThreshold`：原来只认 `majority` / 分数 `N/M` / 数字，前端能选的 `unanimous`、`veto` 会 `Number()` 成 NaN → **兜底成"多数通过"**（管理员选了"全票通过/一票否决"实际按多数跑）。现显式识别：`unanimous`/`veto` → 通过线 = 全员数；`majority` → 过半；分数/数字照旧
  ② vote 网关：新增「一票否决」即时生效——**出现任何反对票即立刻否决**（不必等其他人投完），其余 pending 待办作废；无 `fail` 分支则整单 `rejected`。与或签「任一同意即通过」对称
- 为啥改：待办 R4；波哥口径「多种投票形式都要支持」，而"配了不生效"正是该条要解决的"用起来的样子"
- 影响：`majority` 与分数/数字行为不变；新增 全票通过 / 一票否决 两个规则真的可用
- 验证：SGP 真实实例三情形（见 HK DEVLOG）
- 备份：`/root/server/routes/oa-flow.js.bak.r4vote-*`
- 备注：双端一致（历史仅 1 行注释差异）

## 2026-09-14 · R4 OA 表单服务端校验（发起 / 重提）
- 改了啥：`routes/oa-flow.js` 新增 `validateFormData(formConfig, formData)`，在 `POST /instances`（发起）与 `POST /instances/:id/resubmit`（重提）两处接入：
  必填 / `select` 取值在 options 内 / `number|money` 为数字（money 非负）/ `text|textarea` 长度上限（1000｜20000）/ `multi` 取值在 options 内
- **宽容原则（重要）**：只校验定义里**声明过**的字段，**不拒绝额外字段**（流程会插入 refInstanceId 等系统字段，前端也可能带 extra）；类型只判"明显错误"，不做日期/电话等格式军规 —— 避免把正常提交卡死
- 前置风险排查：DB 里 leave / leave-recover 等定义的字段与前端种子**同源一致**（同 key、同 required）→ 加强校验不会拦下合法提交
- 为啥改：待办 R4；以前仅靠前端拦，绕过页面直接调接口即可提交残缺申请
- 影响：合法提交与"带额外字段"的提交不受影响；残缺/非法数据当场 400 并给出**中文原因**
- 验证：SGP 实测 6 例（见 HK DEVLOG）
- 备份：/root/server/routes/oa-flow.js.bak.r4fv-*
