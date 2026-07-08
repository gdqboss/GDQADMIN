# P4 准备：切换到 Rollup native 模块化（不动手，仅评估）

> **本文档是 P3.5 之后的"下一步"评估**。**当前不切**，等波哥手动决策。
> 创建时间：2026-07-09
> 触发条件：本目录其他文件 + `caimeite-sync-verify-pitfalls` skill + `scripts/e2e-profile-build.sh` 都准备好后

---

## 一句话总结

**当前架构**：vite-plugin 用 regex 后处理（post-process hack）把 entry chunk 里的 import 字符串替换为 stub + 删 disabled chunk 文件。

**目标架构**：用 Rollup 的 `manualChunks` + `entryPoints` 真正**让 vite 在 build 时就不生成 disabled chunk**。**不再需要 module-filter 删文件**。

**不切的原因**（AGENTS.md 自承）：
> 模块化 build 当前是摆设，**白屏风险太高没敢切**（**严禁自行切换**）

---

## 当前 P0-P3 状态（已完成）

| 阶段 | commit | 内容 |
|---|---|---|
| **P0** | `5b8d0ec5` | `verify-sync.sh` 全文件 md5 覆盖 + EXTRA 严格 + favicon 修复 |
| **P1** | `a4a0a93f` | `check-profile-config.mjs` 校验脚本 (3 模式) |
| **P1.5** | `674323d6` | FILE_MAP 补全 14 个新模块 (117 orphan → 0) |
| **P2.1-2.3** | `638c5fdf` | `verify dry-run` + JSON 报告 + 修计数 bug |
| **P3.5** | `93566e1a` | `e2e-profile-build.sh` 离线审计 (PASS 11/11) |

**未做**：P3 浏览器端 puppeteer 跑 SPA（**动生产，超 AGENTS.md 红线**）

---

## 当前 module-filter 架构的问题

### 1. 删文件是"表演性的"（验证过程中发现）

**现状**：
- vite build --emptyOutDir 先生成完整 dist (169 js + 81 css)
- module-filter closeBundle hook 删 8 个文件 (5 js + 3 css)
- **结果**：净剩 163 js + 78 css，少 8 个

**问题**：
- 如果某次 module-filter 因为 bug 没删成功，dist 里有被禁 chunk 但 entry 不引用 → **vite 重新生成完整 dist 时，删过的文件又回来**（这正是 dry-run verify 模式测出来的真问题）
- **真正起作用的是 entry chunk 改写**（5 imports + 8 mapDeps replaced）— 这部分 dry-run 也会真改（实际是 module-filter 写文件前就把 dryRun 状态透传给 plugin）

### 2. regex 后处理脆弱

当前 module-filter 用 2 个 regex：
- `IMPORT_RE = /import\(\s*["']\.\/(.+?)["']\s*\)/g` — 匹配 `import("./X-HASH.js")`
- `MAPDEPS_RE = /"\s*(assets\/[A-Za-z0-9_-]+?-[A-Za-z0-9_-]{6,}\.(?:js|css))"/g` — 匹配 `__vite__mapDeps` 数组

**已知风险**：
- Vite 升级可能改 `__vite__mapDeps` 的字符串格式（**Vite 6 → 7 已经改过一次**，当前是 Vite 7 重写版）
- 任何 vendor 库生成的 `import('./X-HASH.js')` 也会被误匹配（如果 vendor 也用动态 import）
- chunk hash 长度变化会让 MAPDEPS_RE 失配

### 3. AGENTS.md 担心的"白屏"具体是什么

切到 profile build 时，**如果 module-filter 漏删某个 disabled chunk 的引用**，用户访问那条路由会：
- 旧行为（没切）：vite 生成的 dist 里有完整 chunk，**能用**
- 新行为（切了）：vite 生成的 dist 缺 chunk，访问 → **404 chunk load error → SPA 白屏**

**根因**：module-filter 是个 100% 必须 match 的 filter（漏一个 = 白屏），但 vite 生成的 dist 是 "all or nothing"。

---

## Rollup native 方案（PoC 思路）

### 核心：用 Rollup `manualChunks` + `entryPoints` 控制产出

```js
// vite.config.js (profile 模式)
export default defineConfig({
  build: {
    rollupOptions: {
      // 1. 让 vite 只把 enabled module 的入口打进 entry chunk
      input: {
        main: resolve(__dirname, 'main.js'),
        // ... 只 include enabled modules 的 entry
      },
      // 2. 把 disabled module 标记为 external (vite 不打包)
      external: disabledModuleEntries,
      output: {
        // 3. 代码分割时优先按 module 分
        manualChunks(id) {
          if (id.includes('/views/finance-simple/')) return 'finance-simple'
          // ...
        }
      }
    }
  }
})
```

### 关键差异

| 维度 | 当前 (post-process hack) | Rollup native (PoC) |
|---|---|---|
| 生成完整 dist | ✅ 是 | ❌ 否，只生成 enabled 部分 |
| 删 disabled chunk | ✅ closeBundle 后删 | ❌ 不需要，vite 不生成 |
| 漏删风险 | ⚠️ 漏一个 = 白屏 | ✅ 没生成就不存在 |
| regex 依赖 | ⚠️ Vite 升级会坏 | ✅ 不依赖 regex |
| 配置复杂度 | 🟢 中 (单文件 plugin) | 🔴 高 (vite config + profile 输入) |
| 改造工作量 | 0 (已做) | 1-2 天 (重写 vite.config + 重写 profile-config 映射) |
| 风险 | 中 | 低 (行为可预测) |

### 切换必须解决的 3 件事

1. **profile-config.js 要从 "module → file paths" 升级到 "module → rollup input entries"**
   - 当前：MODULE_FILE_MAP = `{'finance': ['finance/']}` (path 前缀)
   - 目标：MODULE_INPUTS = `{'finance': ['views/finance/FinanceList.vue', ...]}` (具体 entry)

2. **router 引用方式要改** — 当前 router 用 `() => import('@/views/finance/FinanceList.vue')`
   - Rollup 静态分析能看到 `import()` 调用并对应到 entry
   - 但 dynamic route (e.g. `path: '/finance/:id'`) + role-based lazy load 需要标准化

3. **每个 profile 的 vite.config.js 要重写** — 当前 89 行模板字符串硬编码
   - 目标：动态生成，每个 profile 读 `modules/<id>/profile-build.config.js`

### 切换必须满足的 5 个条件

- [ ] **P3.5 已 PASS 11/11**（✅ 已满足）
- [ ] **profile 1/2/3 三个 profile 都跑通 e2e-profile-build.sh**（⏳ 只测了 profile 3，profile 1 是全开应该无脑过，profile 2 中等）
- [ ] **P3 puppeteer 浏览器实测 disabled 路由不白屏**（❌ 未做，动生产风险）
- [ ] **AGENTS.md 那行"严禁自行切换"被波哥显式删除**（⏳ 波哥手动）
- [ ] **生产 sgp.bj.3hk 任意一个先小流量切换 7 天没白屏**（❌ 未做）

---

## 什么时候做 P4

**触发条件**（满足任一）：

1. **波哥说"切吧"** — 最直接
2. **dist size 已经成为 nginx 部署瓶颈**（当前 9.7M 还好，gzip 3M，CDN 友好）
3. **AGENTS.md 那行被显式解除**（最规范）
4. **puppeteer 跑通 P3**（最有信心）

**当前建议**：**等波哥手动**。**5 分钟规则不适用于此**（切换生产是 7 天观察期才能定性，不是 5 分钟决策）。

---

## 不动手 P4 的"安全网"

即使 P4 不做，当前 P0-P3.5 已经把：
- sync-sgp-to-bj 验证覆盖率 1.8% → 100% (P0)
- profile-config 一致性可机器校验 (P1/P1.5)
- profile build 干跑可审计 (P2.1-P2.3)
- 离线审计脚本可重复跑 (P3.5)

**风险**：模块化 build 还是"摆设"（AGENTS.md 写的状态），生产还是全量 dev build（9.7M / 269 chunks）。

**好处**：即使波哥永不切 P4，sync 误推 / profile-config 漂移 / 误删 chunk 都能被前置脚本挡住。

---

## 引用

- `scripts/verify-sync.sh` — P0 全文件 md5 校验
- `scripts/check-profile-config.mjs` — P1 校验脚本
- `scripts/build-for-profile.sh` — P2 verify dry-run
- `scripts/e2e-profile-build.sh` — P3.5 离线审计
- `vite-plugins/module-filter.js` — 当前 post-process 实现
- `modules/profile-config.js` — 当前 3 profile × 51 module 映射
- skill: `caimeite-sync-verify-pitfalls` — sync 经验沉淀
