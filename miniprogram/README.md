# 彩美特 微信小程序

## 📁 项目结构

```
miniprogram/
├── src/                    # uni-app 源码
│   ├── pages/              # 5 个页面
│   │   ├── index/         # 首页（tabBar）
│   │   ├── dashboard/     # 工作台（tabBar）
│   │   ├── products/      # 商品管理
│   │   ├── orders/        # 订单管理
│   │   └── member/        # 会员中心（tabBar）
│   ├── static/            # 静态资源
│   │   ├── css/common.css # 全局样式
│   │   └── tabbar/        # tabBar 图标（6 个 png）
│   ├── stores/user.js     # Pinia 状态管理
│   ├── utils/request.js   # HTTP 请求工具
│   ├── App.vue            # 应用入口
│   ├── main.js            # JS 入口
│   ├── pages.json         # 路由配置
│   └── manifest.json      # 应用配置（含 AppID）
├── generate-icons.js      # tabBar 图标生成脚本
├── package.json           # 依赖配置
└── vite.config.js         # Vite 配置
```

## 🛠️ 本地开发步骤（Windows）

### 第 1 步：安装依赖
在 `miniprogram` 目录下打开 PowerShell 或 CMD，运行：

```powershell
cd d:\mydev\mp26\miniprogram
npm install
```

如果安装慢，可以切换到国内镜像：
```powershell
npm config set registry https://registry.npmmirror.com
npm install
```

### 第 2 步：生成 tabBar 图标（首次必做）

```powershell
cd d:\mydev\mp26\miniprogram
node generate-icons.js
```

会自动在 `src/static/tabbar/` 目录生成 6 个图标文件。

### 第 3 步：构建微信小程序

```powershell
npm run build:mp-weixin
```

构建成功后，产物在：
```
miniprogram/dist/build/mp-weixin/
```

### 第 4 步：用微信开发者工具打开

1. 打开「微信开发者工具」
2. 点击「导入项目」或「+」→「导入项目」
3. 项目目录选择：`d:\mydev\mp26\miniprogram\dist\build\mp-weixin`
4. AppID：填你自己的小程序 AppID（或选择"测试号"先预览）
5. 项目名称：随便填，比如"彩美特"
6. 点击「导入」即可看到小程序运行

### 第 5 步：填入你的小程序 AppID

打开 `src/manifest.json`，找到第 28 行的 `appid`，填入你的小程序 AppID，然后**重新构建**：

```powershell
npm run build:mp-weixin
```

## 🔁 日常开发

```powershell
# 构建微信小程序
npm run build:mp-weixin

# 构建 H5 版本
npm run build:h5
```

每次修改源码后，需要重新构建，然后在微信开发者工具中会自动刷新（或按 Ctrl+B 重新编译）。

## ⚙️ 对接后端接口

编辑 `src/utils/request.js`，把 `BASE_URL` 改成你的后端地址：

```js
const BASE_URL = 'https://api.example.com'  // ← 改成你的后端
```

## 🎨 替换 tabBar 图标

当前的图标是纯色占位块。想替换成精美的图标：

1. 准备 6 张 48×48 或 81×81 的 PNG 图片
2. 按同名覆盖到 `src/static/tabbar/` 目录：
   - `home.png` / `home-active.png`
   - `dashboard.png` / `dashboard-active.png`
   - `member.png` / `member-active.png`
3. 重新构建即可

## 📊 项目统计

- 当前包体大小：约 232KB（远小于 2MB 上传限制）
- 页面数量：5 个
- TabBar 数量：3 个
- 技术栈：uni-app (Vue 3) + Pinia + Vite
