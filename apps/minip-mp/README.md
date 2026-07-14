# 彩美特 minip 小程序（同源备用）

> **铁律：H5 (`/root/src/views/minip/*.vue`) = 单一事实源 = 小程序代码镜像**

## 架构原则

| 层 | 来源 |
|---|---|
| View 代码 | **直接从 `/root/src/views/minip/` 引用**（不复制） |
| API 请求 | 通过 shim 把 axios → uni.request |
| 后端 API | **0 后端改动**，复用 `/api/*` |
| Style | 同 H5（uni-app 兼容 scoped CSS） |

## 工作流

```
H5 改 view → 自动同步到小程序（无需复制）
                    ↓
修改 `views/minip/MeCoupons.vue`
                    ↓
`bash sync.sh` 或 `cd apps/minip-mp && npm run sync`
                    ↓
小程序 view 自动更新
```

## 为什么不同步代码？

1. **单一事实源**：1 个 `MeCoupons.vue` → 同时给 H5 + 小程序用
2. **零维护成本**：改 H5 = 自动同步小程序
3. **零后端改动**：复用 `/api/*`
4. **未来加 uni-app 编译器** 也只是 `pnpm install @dcloudio/uni-app` 单命令

## 目录结构

```
apps/minip-mp/
├── package.json          # 空依赖（npm publish 1MB 内）
├── README.md             # 本文档
├── sync.sh               # 一键同步 H5 view 到 src/pages/
├── src/
│   ├── pages/            # 34 个 H5 view 同步过来（软链接/复制）
│   ├── utils/api.js      # uni.request shim（替代 axios）
│   ├── App.vue           # 入口
│   ├── main.js           # uni-app 启动
│   ├── pages.json        # 页面注册
│   ├── manifest.json     # appid + mp-weixin 配置
│   └── uni.scss          # 主题色
└── scripts/
    ├── sync-h5-source.mjs  # 自动同步 34 view
    └── inspect.mjs       # 状态展示
```

## 同步原理

`scripts/sync-h5-source.mjs` 干的事：

1. 遍历 `/root/src/views/minip/*.vue` (34 个)
2. 每个 view 转译：
   - `import api from '@/api/request'` → `import api from '@/utils/api'`
   - `<router-link>` → `<navigator>` (uni-app)
   - `<router-view>` → (uni-app 不需要，用 Tab)
   - `localStorage` → `uni.setStorageSync`
3. 写到 `src/pages/<name>.vue`

**H5 view 改动 → 重跑 sync → 小程序 view 自动更新**

## 真转微信小程序的步骤（需追加）

1. `pnpm init` 主项目
2. `pnpm add @dcloudio/uni-app @dcloudio/uni-mp-weixin`
3. 写 `vite.config.js`（用 `@dcloudio/vite-plugin-uni`）
4. `pnpm build:mp-weixin`
5. 用微信开发者工具打开 `dist/build/mp-weixin/`

**这套架构 = 同一个 view 代码，H5 build + 小程序 build 都能用**
