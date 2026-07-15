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