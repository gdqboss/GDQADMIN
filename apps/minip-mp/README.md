# 彩美特微信小程序 (minip-mp)

> uni-app 编译目标 / 从 H5 `.vue` 自动同步 / 0 后端改动
> 跟 H5、`/root/server/` 后端共享同一接口体系

---

## 🎯 设计目标

**H5 = 真理源**，本目录是编译产物源。改 H5 → 一行命令同步过来，**后端 0 改动**。

```
H5 .vue (单一事实源)                       后端 Node
/root/src/views/minip/*.vue   ─→  转译  →  pages/*.vue  ─→  /api/*  ─→  /root/server/
                              sync
                          (npm run sync)
```

**为什么这样设计**：
- 1 套 H5 `.vue` 真理源，H5 改 → 小程序自动同步
- 组成员无需关心小程序，H5 写什么小程序就有什么
- 后端 API 100% 复用 `wecom.gdqshop.cn/api/*`，零后端工作量
- 加新模块 = `views/<m>/_meta.js` + 后端 route + `npm run sync`

---

## 📁 目录结构

```
minip-mp/
├── package.json              # npm 命令（sync / inspect / lint:syntax）
├── README.md                 # 本文档
├── scripts/
│   ├── sync-h5-source.mjs    # 🔧 H5 → minip-mp 转译器（核心）
│   ├── inspect.mjs           # 📊 现状盘点（H5/mp/配置一致性）
│   └── check-syntax.mjs      # ✅ vue script 语法检查
└── src/
    ├── App.vue               # 应用入口
    ├── main.js               # Vue/uni-app 初始化
    ├── pages.json            # 34 页面注册（sync 自动生成）
    ├── manifest.json         # appid + 编译配置（含 H5 fallback）
    ├── uni.scss              # 全局 SCSS 变量
    ├── utils/api.js          # 🔧 axios → uni.request shim（0 改动兼容）
    └── pages/                # 34 page（sync 自动生成）
        ├── Finance.vue
        ├── Hr.vue
        └── ... 32 others
```

---

## 🚀 快速上手（4 步发布）

### 步骤 1：H5 改了 → 同步（必须）

```bash
cd /root/src/apps/minip-mp
npm run sync                # 同步 H5 → 本目录（覆盖 34 page + pages.json）
npm run sync:dry            # 看会改哪些，不动文件
npm run inspect             # 现状盘点
npm run lint:syntax         # 检查 vue script 语法（CI 用）
```

### 步骤 2：填 appid（一次性，必须）

1. 去 [微信公众平台](https://mp.weixin.qq.com/) 注册小程序
2. 拿到 AppID（`wx...`）
3. 替换 `src/manifest.json`：

```json
{
  "appid": "wx1234567890abcdef",
  "mp-weixin": {
    "appid": "wx1234567890abcdef"
  }
}
```

### 步骤 3：用 HBuilderX 打开本目录

⚠️ **本仓库不带 uni-app 编译器**（独立子项目设计，避免污染主项目）

| 方式 | 操作 |
|---|---|
| **HBuilderX** | 下载 → "打开目录" 选本目录 → 顶部菜单 "发行 → 小程序-微信" |
| **uni-app CLI** | `npx @dcloudio/uvm --package @dcloudio/uni-app && npm run dev:mp-weixin` |

### 步骤 4：发布

HBuilderX 编译后输出到 `unpackage/dist/dev/mp-weixin/`，导入微信开发者工具 → 上传 → 发布

---

## 🔧 转译器覆盖范围（sync 转什么）

| H5 写法 | 转译后 |
|---|---|
| `import api from '@/api/request'` | `import api from '@/utils/api'` |
| `ElMessage.error('x')` | `uni.showToast({ title: 'x', icon: 'none' })` |
| `ElMessage.success('x')` | `uni.showToast({ title: 'x', icon: 'success' })` |
| `ElMessageBox.confirm('x')` | `uni.showModal({ content: 'x' }).then(r => r.confirm)` |
| `import { ElMessage } from 'element-plus'` | **整行删** |
| `<router-link to="/x">` | `<navigator url="/x">` |
| `$router.push('/x')` | `uni.navigateTo({ url: '/x' })` |
| `$router.push(r.path)` (变量) | `uni.navigateTo({ url: r.path })` |
| `$router.back()` | `uni.navigateBack()` |
| `$router.go(-1)` | `uni.navigateBack({ delta: -1 })` |
| `$route.query.id` | `getCurrentPages().slice(-1)[0].options?.id` |
| `localStorage.getItem('x')` | `uni.getStorageSync('x')` |
| `localStorage.setItem('x', v)` | `uni.setStorageSync('x', v)` |
| `<el-button>` | `<button>` |
| `<el-input>` | `<input>` |
| `<img src=>` | `<image src=>` |
| `v-html=` | `v-text=` |
| `window.location.href = 'x'` | `uni.navigateTo({ url: 'x' })` |
| `document.title = 'x'` | `uni.setNavigationBarTitle({ title: 'x' })` |

**不转译**（保留原样，让业务自行处理）：
- `alert()` / `confirm()` 含模板字符串嵌套括号，regex 难处理。**少数场景手改即可**（目前仅 HrSalary.vue）
- `window.open()` 已注释（小程序不支持外部跳转，用 `<navigator open-type="launchWebView">` 替代）

---

## 🛠 已知坑

| 坑 | 现象 | 修复 |
|---|---|---|
| Element Plus 弹窗组件 `<el-dialog>` v-model | 转译成 `<view>` 后 v-model 失效 | 已转成 `<view>`，用 `uni.showModal` 替代（手动） |
| v3 脚本留下的双重 `v-if="loading"` | 转译前自动 dedupe | `patchKnownBugs()` 内置 |
| H5 后端 API base URL 不一致 | 默认走 `wecom.gdqshop.cn/api`，**0 改动复用** | 同源设计 |
| minip-mp 是独立子项目 | `node_modules` 隔离，不污染主项目 | 当前无 npm 依赖，3 个内置脚本 |
| `transformPx: false` 在 manifest | 避免 rpx 误转换 | 默认 false |
| 微信小程序最大 2MB 主包 | 当前 34 page 预估 < 1.5MB（vite 自动分包） | 监控 |

---

## ❓ 常见问题

### Q: H5 改了，怎么传到小程序？
A: 改完 `npm run sync` → 用 HBuilderX 重新发行。

### Q: 小程序要单独维护 API 路径？
A: **不需要**。`utils/api.js` shim 把 H5 的 axios 调用转成 uni.request，baseURL 继承自 `request.js`。改后端 = 小程序无感知。

### Q: HBuilderX 编译报 `appid` 错误？
A: 改 `src/manifest.json` 里的 `appid` 和 `mp-weixin.appid`。

### Q: 怎么加新页面？
A: 在 H5 加 `views/minip/NewPage.vue` → `npm run sync`。**不要手动加 page 文件**。

### Q: 怎么调试？
A: 微信开发者工具 → 导入 `unpackage/dist/dev/mp-weixin/` → 真机预览。

### Q: 后端要不要为小程序改 API？
A: **不要**。H5 的 `/api/*` 已经覆盖小程序所有需求。

---

## 📊 当前状态（自动刷新 via `npm run inspect`）

```
📁 H5 真理源:        34 个 view
📁 minip-mp pages:    34 个 page
✅ 一致性:           100%
✅ 转译残留:         0
✅ Syntax:           34/34 OK
⚠️  appid:          待填（从微信公众平台）
⚠️  编译器:          待装（HBuilderX 或 uni-app CLI）
```

---

## 🔄 CI / 自动化建议

```yaml
# 在主仓库 CI 加（todo）
- name: minip-mp sync
  run: |
    cd apps/minip-mp
    npm run sync
    npm run lint:syntax
- name: diff check
  run: |
    cd apps/minip-mp
    git diff --exit-code  # 防止意外飘移
```

---

> **设计原则**：H5 改 → 1 命令同步 → 0 后端改动 → 组成员同步零工作量
> 出问题看：`npm run inspect`（自动诊断）
