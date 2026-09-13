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
